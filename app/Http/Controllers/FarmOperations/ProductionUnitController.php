<?php

namespace App\Http\Controllers\FarmOperations;

use App\Actions\FarmOperations\CreateProductionUnit;
use App\Http\Controllers\Controller;
use App\Http\Requests\FarmOperations\StoreProductionUnitRequest;
use App\Models\Farm;
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

        return to_route('farms.index', ['current_team' => $currentTeam]);
    }
}
