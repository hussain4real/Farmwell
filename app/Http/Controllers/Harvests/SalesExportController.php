<?php

namespace App\Http\Controllers\Harvests;

use App\Actions\Harvests\SalesExport;
use App\Enums\SalePaymentStatus;
use App\Http\Controllers\Controller;
use App\Models\ReportArtifact;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as DownloadResponse;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Excel;

class SalesExportController extends Controller
{
    public function index(Team $currentTeam): Response
    {
        return Inertia::render('harvests/Exports', [
            'farms' => $currentTeam->farms()->orderBy('name')->get(['id', 'name']),
            'commodities' => $currentTeam->commodities()->orderBy('name')->get(['id', 'name']),
            'paymentStatuses' => SalePaymentStatus::options(),
            'report' => 'sales',
            'cycles' => $currentTeam->productionCycles()->orderBy('name')->get(['id', 'farm_id', 'name']),
            'exports' => $this->exports($currentTeam)->latest('id')->paginate(20, ['id', 'farm_id', 'period_start', 'period_end']),
        ]);
    }

    public function store(Request $request, Team $currentTeam, Excel $excel): RedirectResponse
    {
        $farmId = $request->integer('farm_id');
        $filters = $request->validate([
            'period_start' => ['required', 'date_format:Y-m-d'],
            'period_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_start'],
            'farm_id' => ['required', 'integer', Rule::exists('farms', 'id')->where('team_id', $currentTeam->id)],
            'commodity_id' => ['nullable', 'integer', Rule::exists('commodities', 'id')->where('team_id', $currentTeam->id)],
            'production_cycle_id' => ['nullable', 'integer', Rule::exists('production_cycles', 'id')->where('team_id', $currentTeam->id)->where('farm_id', $farmId)],
            'payment_status' => ['nullable', Rule::enum(SalePaymentStatus::class)],
        ]);
        $workbook = new SalesExport($currentTeam->id, $filters);

        ReportArtifact::query()->create([
            'type' => 'sales_log',
            'team_id' => $currentTeam->id,
            'generated_by_id' => auth()->id(),
            'farm_id' => $filters['farm_id'],
            'production_cycle_id' => $filters['production_cycle_id'] ?? null,
            'period_start' => $filters['period_start'],
            'period_end' => $filters['period_end'],
            'filters' => $filters,
            'document' => base64_encode($excel->raw($workbook, Excel::XLSX)),
        ]);

        return to_route('harvests.sales.exports.index', $currentTeam);
    }

    public function download(Team $currentTeam, int $reportArtifact): DownloadResponse
    {
        $report = $this->exports($currentTeam)->findOrFail($reportArtifact);

        return response(base64_decode($report->document), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="sales-log-'.$report->id.'.xlsx"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** @return Builder<ReportArtifact> */
    private function exports(Team $team): Builder
    {
        return ReportArtifact::query()->where('type', 'sales_log')
            ->where('team_id', $team->id)->where('generated_by_id', auth()->id());
    }
}
