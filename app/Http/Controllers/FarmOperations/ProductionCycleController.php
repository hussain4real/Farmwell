<?php

namespace App\Http\Controllers\FarmOperations;

use App\Actions\FarmOperations\CreateProductionCycle;
use App\Http\Controllers\Controller;
use App\Http\Requests\FarmOperations\StoreProductionCycleRequest;
use App\Models\Farm;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ProductionCycleController extends Controller
{
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

        return to_route('farms.index', ['current_team' => $currentTeam]);
    }
}
