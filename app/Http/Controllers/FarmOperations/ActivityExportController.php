<?php

namespace App\Http\Controllers\FarmOperations;

use App\Actions\FarmOperations\ActivityLogExport;
use App\Actions\Finance\ResolveTeamFinanceCurrency;
use App\Http\Controllers\Controller;
use App\Http\Requests\FarmOperations\StoreActivityExportRequest;
use App\Models\ReportArtifact;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as DownloadResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Excel;

class ActivityExportController extends Controller
{
    public function index(Team $currentTeam): Response
    {
        Gate::authorize('manageFarmOperations', $currentTeam);

        return Inertia::render('field-diary/Exports', [
            'farms' => $currentTeam->farms()->orderBy('name')->get(['id', 'name']),
            'commodities' => $currentTeam->commodities()->orderBy('name')->get(['id', 'name']),
            'units' => $currentTeam->productionUnits()->orderBy('name')->get(['id', 'farm_id', 'name']),
            'phases' => $currentTeam->fundingPhases()->orderBy('name')->get(['id', 'farm_id', 'name']),
            'exports' => $this->exports($currentTeam)->latest('id')->paginate(20, ['id', 'farm_id', 'period_start', 'period_end']),
        ]);
    }

    public function store(StoreActivityExportRequest $request, Team $currentTeam, Excel $excel, ResolveTeamFinanceCurrency $currency): RedirectResponse
    {
        $filters = $request->validated();
        $workbook = new ActivityLogExport($currentTeam->id, $filters, $currency->handle($currentTeam));

        ReportArtifact::query()->create([
            'type' => 'activity_log',
            'team_id' => $currentTeam->id,
            'generated_by_id' => auth()->id(),
            'farm_id' => $filters['farm_id'],
            'period_start' => $filters['period_start'],
            'period_end' => $filters['period_end'],
            'filters' => $filters,
            'document' => base64_encode($excel->raw($workbook, Excel::XLSX)),
        ]);

        return to_route('activity-exports.index', $currentTeam);
    }

    public function download(Team $currentTeam, int $reportArtifact): DownloadResponse
    {
        Gate::authorize('manageFarmOperations', $currentTeam);
        $report = $this->exports($currentTeam)->findOrFail($reportArtifact);

        return response(base64_decode($report->document), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="activity-log-'.$report->id.'.xlsx"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** @return Builder<ReportArtifact> */
    private function exports(Team $team): Builder
    {
        return ReportArtifact::query()->where('type', 'activity_log')
            ->where('team_id', $team->id)->where('generated_by_id', auth()->id());
    }
}
