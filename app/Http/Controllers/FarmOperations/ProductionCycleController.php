<?php

namespace App\Http\Controllers\FarmOperations;

use App\Actions\FarmOperations\BuildCycleDetailPageData;
use App\Actions\FarmOperations\CreateProductionCycle;
use App\Actions\FarmOperations\UpdateProductionCycle;
use App\Http\Controllers\Controller;
use App\Http\Requests\FarmOperations\StoreProductionCycleRequest;
use App\Http\Requests\FarmOperations\UpdateProductionCycleRequest;
use App\Models\Farm;
use App\Models\ProductionCycle;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ProductionCycleController extends Controller
{
    /**
     * Display the production cycle command center.
     */
    public function show(
        Request $request,
        Team $currentTeam,
        Farm $farm,
        ProductionCycle $productionCycle,
        BuildCycleDetailPageData $pageData,
    ): Response {
        Gate::authorize('viewFieldWork', $currentTeam);

        $user = $request->user();
        assert($user instanceof User);

        return Inertia::render('cycles/Show', $pageData->handle($currentTeam, $farm, $productionCycle, $user));
    }

    /**
     * Store a newly created production cycle.
     */
    public function store(
        StoreProductionCycleRequest $request,
        Team $currentTeam,
        Farm $farm,
        CreateProductionCycle $createProductionCycle,
    ): RedirectResponse {
        $user = $request->user();
        assert($user instanceof User);

        $createProductionCycle->handle(
            team: $currentTeam,
            farm: $farm,
            actor: $user,
            attributes: $request->cycleAttributes(),
            productionUnitIds: $request->productionUnitIds(),
            commodities: $request->commodities(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Production cycle created.')]);

        if ($request->input('return_to') === 'farm') {
            return to_route('farms.show', ['current_team' => $currentTeam, 'farm' => $farm]);
        }

        return to_route('farms.index', ['current_team' => $currentTeam]);
    }

    /**
     * Update an existing production cycle.
     */
    public function update(
        UpdateProductionCycleRequest $request,
        Team $currentTeam,
        Farm $farm,
        ProductionCycle $productionCycle,
        UpdateProductionCycle $updateProductionCycle,
    ): RedirectResponse {
        $user = $request->user();
        assert($user instanceof User);

        $updateProductionCycle->handle(
            team: $currentTeam,
            productionCycle: $productionCycle,
            actor: $user,
            attributes: $request->cycleAttributes(),
            productionUnitIds: $request->productionUnitIds(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Production cycle updated.')]);

        return to_route('farms.production-cycles.show', [
            'current_team' => $currentTeam,
            'farm' => $farm,
            'production_cycle' => $productionCycle,
        ]);
    }
}
