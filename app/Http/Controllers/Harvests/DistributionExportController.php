<?php

namespace App\Http\Controllers\Harvests;

use App\Actions\Harvests\DistributionExport;
use App\Enums\DistributionStatus;
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

class DistributionExportController extends Controller
{
    public function index(Team $currentTeam): Response
    {
        return Inertia::render('harvests/Exports', [
            'farms' => $currentTeam->farms()->orderBy('name')->get(['id', 'name']),
            'agreements' => $currentTeam->investorAgreements()->orderBy('title')->get(['id', 'farm_id', 'title']),
            'distributionStatuses' => DistributionStatus::options(),
            'report' => 'distributions',
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
            'investor_agreement_id' => ['nullable', 'integer', Rule::exists('investor_agreements', 'id')->where('team_id', $currentTeam->id)->where('farm_id', $farmId)],
            'production_cycle_id' => ['nullable', 'integer', Rule::exists('production_cycles', 'id')->where('team_id', $currentTeam->id)->where('farm_id', $farmId)],
            'status' => ['nullable', Rule::enum(DistributionStatus::class)],
        ]);
        $workbook = new DistributionExport($currentTeam->id, $filters);

        ReportArtifact::query()->create([
            'type' => 'distribution_log',
            'team_id' => $currentTeam->id,
            'generated_by_id' => auth()->id(),
            'farm_id' => $filters['farm_id'],
            'production_cycle_id' => $filters['production_cycle_id'] ?? null,
            'period_start' => $filters['period_start'],
            'period_end' => $filters['period_end'],
            'filters' => $filters,
            'document' => base64_encode($excel->raw($workbook, Excel::XLSX)),
        ]);

        return to_route('harvests.distributions.exports.index', $currentTeam);
    }

    public function download(Team $currentTeam, int $reportArtifact): DownloadResponse
    {
        $report = $this->exports($currentTeam)->findOrFail($reportArtifact);

        return response(base64_decode($report->document), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="distribution-log-'.$report->id.'.xlsx"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** @return Builder<ReportArtifact> */
    private function exports(Team $team): Builder
    {
        return ReportArtifact::query()->where('type', 'distribution_log')
            ->where('team_id', $team->id)->where('generated_by_id', auth()->id());
    }
}
