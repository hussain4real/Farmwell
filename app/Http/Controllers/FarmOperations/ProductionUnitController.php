<?php

namespace App\Http\Controllers\FarmOperations;

use App\Actions\FarmOperations\CreateProductionUnit;
use App\Actions\FarmOperations\UpdateProductionUnit;
use App\Http\Controllers\Controller;
use App\Http\Requests\FarmOperations\StoreProductionUnitRequest;
use App\Http\Requests\FarmOperations\UpdateProductionUnitRequest;
use App\Models\Farm;
use App\Models\ProductionUnit;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ProductionUnitController extends Controller
{
    /**
     * Store a newly created production unit for a farm.
     */
    public function store(
        StoreProductionUnitRequest $request,
        Team $currentTeam,
        Farm $farm,
        CreateProductionUnit $createProductionUnit,
    ): RedirectResponse {
        $user = $request->user();
        assert($user instanceof User);

        $createProductionUnit->handle($currentTeam, $farm, $user, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Production unit created.')]);

        if ($request->input('return_to') === 'farm') {
            return to_route('farms.show', ['current_team' => $currentTeam, 'farm' => $farm]);
        }

        return to_route('farms.index', ['current_team' => $currentTeam]);
    }

    /**
     * Update an existing production unit.
     */
    public function update(
        UpdateProductionUnitRequest $request,
        Team $currentTeam,
        Farm $farm,
        ProductionUnit $productionUnit,
        UpdateProductionUnit $updateProductionUnit,
    ): RedirectResponse {
        $user = $request->user();
        assert($user instanceof User);

        $updateProductionUnit->handle($currentTeam, $productionUnit, $user, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Production unit updated.')]);

        return to_route('farms.show', ['current_team' => $currentTeam, 'farm' => $farm]);
    }
}
