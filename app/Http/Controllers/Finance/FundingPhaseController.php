<?php

namespace App\Http\Controllers\Finance;

use App\Actions\Finance\BuildFinancePageData;
use App\Actions\Finance\CreateFundingPhase;
use App\Http\Controllers\Controller;
use App\Http\Requests\Finance\StoreFundingPhaseRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class FundingPhaseController extends Controller
{
    public function index(Request $request, Team $currentTeam, BuildFinancePageData $pageData): Response
    {
        Gate::authorize('viewFinance', $currentTeam);

        $user = $request->user();
        assert($user instanceof User);

        return Inertia::render('finance/FundingPhases', $pageData->fundingPhases($currentTeam, $user));
    }

    public function store(StoreFundingPhaseRequest $request, Team $currentTeam, CreateFundingPhase $createFundingPhase): RedirectResponse
    {
        $user = $request->user();
        assert($user instanceof User);

        $createFundingPhase->handle($currentTeam, $user, $request->phaseAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Funding phase created.')]);

        return to_route('finance.funding-phases.index', ['current_team' => $currentTeam]);
    }
}
