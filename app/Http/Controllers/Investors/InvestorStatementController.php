<?php

namespace App\Http\Controllers\Investors;

use App\Actions\Investors\BuildInvestorStatement;
use App\Enums\InvestorAgreementStatus;
use App\Http\Controllers\Controller;
use App\Models\InvestorAgreement;
use App\Models\ReportArtifact;
use App\Models\Team;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Spatie\LaravelPdf\Facades\Pdf;

class InvestorStatementController extends Controller
{
    public function index(Team $currentTeam, InvestorAgreement $investorAgreement): InertiaResponse
    {
        $statements = $this->statements($currentTeam, $investorAgreement)->latest('id')
            ->paginate(20, ['id', 'period_start', 'period_end', 'created_at']);

        return Inertia::render('investor-portal/Statements', [
            'agreement' => $investorAgreement->only('id', 'title', 'currency'),
            'statements' => $statements,
        ]);
    }

    public function store(Request $request, Team $currentTeam, InvestorAgreement $investorAgreement, BuildInvestorStatement $builder): RedirectResponse
    {
        $statements = $this->statements($currentTeam, $investorAgreement);
        $period = $request->validate([
            'period_start' => ['required', 'date_format:Y-m-d'],
            'period_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_start'],
        ]);
        $statements->create([
            ...$period,
            'team_id' => $currentTeam->id, 'generated_by_id' => $investorAgreement->investor_id,
            'farm_id' => $investorAgreement->farm_id, 'production_cycle_id' => $investorAgreement->production_cycle_id,
            'type' => 'investor_statement',
            'document' => Pdf::html($builder->render($investorAgreement, $period['period_start'], $period['period_end']))->driver('dompdf')->base64(),
        ]);

        return to_route('investor-statements.index', [$currentTeam, $investorAgreement]);
    }

    public function download(Team $currentTeam, InvestorAgreement $investorAgreement, int $reportArtifact): Response
    {
        $artifact = $this->statements($currentTeam, $investorAgreement)->findOrFail($reportArtifact);

        return response(base64_decode($artifact->document), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="investor-statement-'.$artifact->id.'.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** @return HasMany<ReportArtifact, InvestorAgreement> */
    private function statements(Team $team, InvestorAgreement $agreement): HasMany
    {
        Gate::authorize('viewInvestorPortal', $team);
        abort_unless($agreement->team_id === $team->id && $agreement->investor_id === auth()->id()
            && in_array($agreement->status, [InvestorAgreementStatus::Active, InvestorAgreementStatus::Completed], true), 404);

        return $agreement->reportArtifacts()->where([
            'team_id' => $team->id, 'generated_by_id' => $agreement->investor_id,
            'farm_id' => $agreement->farm_id, 'production_cycle_id' => $agreement->production_cycle_id,
            'type' => 'investor_statement',
        ]);
    }
}
