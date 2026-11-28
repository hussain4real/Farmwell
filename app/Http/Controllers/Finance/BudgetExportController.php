<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use App\Models\ReportArtifact;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class BudgetExportController extends Controller
{
    public function index(Team $currentTeam, Budget $budget): InertiaResponse
    {
        return Inertia::render('finance/BudgetExports', [
            'budget' => ['id' => $budget->id, 'name' => $budget->name, 'currency' => $budget->currency,
                'start' => $budget->period_start_on?->toDateString(), 'end' => $budget->period_end_on?->toDateString()],
            'exports' => $this->exports($currentTeam, $budget)->latest('id')->paginate(20, ['status', 'failure_message', 'id', 'period_start', 'period_end', 'created_at']),
        ]);
    }

    public function store(Request $request, Team $currentTeam, Budget $budget): RedirectResponse
    {
        $period = $request->validate([
            'period_start' => ['required', 'date_format:Y-m-d'],
            'period_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_start'],
        ]);
        ReportArtifact::enqueue([
            ...$period, 'team_id' => $currentTeam->id, 'farm_id' => $budget->farm_id, 'production_cycle_id' => $budget->production_cycle_id,
            'generated_by_id' => $request->user()->getAuthIdentifier(), 'type' => 'budget_variance',
            'filters' => ['budget_id' => $budget->id, 'currency' => $budget->currency],
        ]);

        return to_route('finance.budget-exports.index', [$currentTeam, $budget]);
    }

    public function download(Team $currentTeam, Budget $budget, int $reportArtifact): Response
    {
        $report = $this->exports($currentTeam, $budget)->where('status', 'ready')->findOrFail($reportArtifact);

        return response(base64_decode($report->document), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="budget-variance-'.$report->id.'.xlsx"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** @return Builder<ReportArtifact> */
    private function exports(Team $team, Budget $budget): Builder
    {
        return ReportArtifact::query()->where('type', 'budget_variance')->where('team_id', $team->id)
            ->where('generated_by_id', auth()->id())->where('filters->budget_id', $budget->id);
    }
}
