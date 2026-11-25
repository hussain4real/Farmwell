<?php

namespace App\Http\Controllers\FarmOperations;

use App\Actions\FarmOperations\CompleteFarmOnboarding;
use App\Actions\Finance\ResolveTeamFinanceCurrency;
use App\Actions\Teams\ResolveInvestorMode;
use App\Enums\CropGrowthStage;
use App\Enums\EnterpriseSubtype;
use App\Enums\FarmType;
use App\Enums\ProductionUnitType;
use App\Http\Controllers\Controller;
use App\Http\Requests\FarmOperations\StoreOnboardingRequest;
use App\Models\Team;
use App\Models\User;
use App\Support\FarmTemplates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function index(
        Request $request,
        Team $currentTeam,
        ResolveTeamFinanceCurrency $resolveCurrency,
        ResolveInvestorMode $resolveInvestorMode,
    ): Response {
        Gate::authorize('manageFarmOperations', $currentTeam);

        return Inertia::render('onboarding/Index', [
            'currency' => $resolveCurrency->handle($currentTeam),
            'investorMode' => $resolveInvestorMode->handle($currentTeam),
            'templates' => FarmTemplates::all(),
            'options' => [
                'farmTypes' => FarmType::options(),
                'productionUnitTypes' => ProductionUnitType::options(),
                'enterpriseSubtypes' => EnterpriseSubtype::options(),
                'cropGrowthStages' => CropGrowthStage::options(),
            ],
        ]);
    }

    public function store(
        StoreOnboardingRequest $request,
        Team $currentTeam,
        CompleteFarmOnboarding $completeFarmOnboarding,
    ): RedirectResponse {
        $user = $request->user();
        assert($user instanceof User);

        $farm = $completeFarmOnboarding->handle($currentTeam, $user, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Farm onboarded and ready for operations.')]);

        return to_route('farms.show', ['current_team' => $currentTeam, 'farm' => $farm]);
    }
}
