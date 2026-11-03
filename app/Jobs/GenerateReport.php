<?php

namespace App\Jobs;

use App\Actions\AiSummaries\InvestorSummaryEligibility;
use App\Actions\FarmOperations\ActivityLogExport;
use App\Actions\Finance\BudgetVarianceExport;
use App\Actions\Harvests\DistributionExport;
use App\Actions\Harvests\HarvestExport;
use App\Actions\Harvests\SalesExport;
use App\Actions\Investors\BuildInvestorStatement;
use App\Enums\InvestorAgreementStatus;
use App\Models\Budget;
use App\Models\InvestorAgreement;
use App\Models\ReportArtifact;
use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Excel;
use Spatie\LaravelPdf\Facades\Pdf;
use Throwable;

class GenerateReport implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 90;

    /** @var list<int> */
    public array $backoff = [10, 30];

    public function __construct(public int $reportId) {}

    public function handle(Excel $excel): void
    {
        DB::transaction(function () use ($excel): void {
            $report = ReportArtifact::query()->whereKey($this->reportId)->where('status', 'queued')->lockForUpdate()->first();
            if ($report === null) {
                return;
            }

            try {
                $document = $this->render($report, $excel);
            } catch (AuthorizationException|ModelNotFoundException) {
                $report->update(['status' => 'failed', 'failure_message' => 'The report is no longer available for this account or scope.']);

                return;
            } catch (ValidationException $exception) {
                $report->update(['status' => 'failed', 'failure_message' => $exception->getMessage()]);

                return;
            }

            $report->update(['status' => 'ready', 'generated_at' => now(), 'document' => $document]);
        });
    }

    private function render(ReportArtifact $report, Excel $excel): string
    {
        $ability = match ($report->type) {
            'activity_log' => 'manageFarmOperations',
            'harvest_log' => 'manageHarvests',
            'investor_statement' => 'viewInvestorPortal',
            default => 'manageFinance',
        };
        Gate::forUser(User::findOrFail($report->generated_by_id))->authorize($ability, Team::findOrFail($report->team_id));

        $scope = ['team_id' => $report->team_id, 'farm_id' => $report->farm_id, 'production_cycle_id' => $report->production_cycle_id];
        if ($report->type === 'investor_statement') {
            $agreement = InvestorAgreement::query()->where($scope)->where('investor_id', $report->generated_by_id)
                ->whereIn('status', [InvestorAgreementStatus::Active, InvestorAgreementStatus::Completed])->findOrFail($report->investor_agreement_id);
            $summary = ($report->filters['include_summary'] ?? false)
                ? app(InvestorSummaryEligibility::class)->forStatement($agreement, $report->period_start, $report->period_end)
                : null;
            if ($summary !== null) {
                $report->filters = [...$report->filters, 'summary_id' => $summary->id, 'summary_hash' => hash('sha256', $summary->final_text)];
            }
            $html = app(BuildInvestorStatement::class)->render($agreement, $report->period_start, $report->period_end, $summary?->final_text);

            return Pdf::html($html)->driver('dompdf')->base64();
        }

        $filters = $report->filters;
        $workbook = match ($report->type) {
            'activity_log' => new ActivityLogExport($report->team_id, $filters, $filters['currency']),
            'budget_variance' => new BudgetVarianceExport(
                Budget::query()->where($scope)->where('currency', $filters['currency'])->findOrFail($filters['budget_id']),
                $report->period_start, $report->period_end),
            'harvest_log' => new HarvestExport($report->team_id, $filters),
            'sales_log' => new SalesExport($report->team_id, $filters),
            'distribution_log' => new DistributionExport($report->team_id, $filters),
        };

        return base64_encode($excel->raw($workbook, Excel::XLSX));
    }

    public function failed(?Throwable $exception): void
    {
        ReportArtifact::query()->whereKey($this->reportId)->where('status', 'queued')->update([
            'status' => 'failed', 'failure_message' => 'Generation failed. Please submit the report again.',
        ]);
    }
}
