<?php

namespace App\Http\Controllers\FarmOperations;

use App\Actions\FarmOperations\RecordProductionPlanChange;
use App\Http\Controllers\Controller;
use App\Http\Requests\FarmOperations\StoreProductionPlanChangeRequest;
use App\Models\Farm;
use App\Models\ProductionCycle;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ProductionPlanChangeController extends Controller
{
    /**
     * Store a reasoned plan change for a production cycle.
     */
    public function store(
        StoreProductionPlanChangeRequest $request,
        Team $currentTeam,
        Farm $farm,
        ProductionCycle $productionCycle,
        RecordProductionPlanChange $recordProductionPlanChange,
    ): RedirectResponse {
        $user = $request->user();
        assert($user instanceof User);

        $recordProductionPlanChange->handle(
            team: $currentTeam,
            productionCycle: $productionCycle,
            actor: $user,
            attributes: $request->validated(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Plan change recorded.')]);

        return to_route('farms.index', ['current_team' => $currentTeam]);
    }
}
