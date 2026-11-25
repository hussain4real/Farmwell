<?php

namespace App\Http\Controllers\FarmOperations;

use App\Actions\FarmOperations\TransferGradedFish;
use App\Http\Controllers\Controller;
use App\Http\Requests\FarmOperations\StoreGradeTransferRequest;
use App\Models\Farm;
use App\Models\ProductionCycle;
use App\Models\ProductionUnit;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class AquacultureGradeTransferController extends Controller
{
    public function store(
        StoreGradeTransferRequest $request,
        Team $currentTeam,
        Farm $farm,
        ProductionCycle $productionCycle,
        TransferGradedFish $transferGradedFish,
    ): RedirectResponse {
        $user = $request->user();
        assert($user instanceof User);

        $validated = $request->validated();
        $targetUnit = ProductionUnit::query()->whereKey((int) $validated['target_unit_id'])->firstOrFail();
        $targetCycle = ProductionCycle::query()->whereKey((int) $validated['target_cycle_id'])->firstOrFail();

        $transferGradedFish->handle($currentTeam, $productionCycle, $targetUnit, $targetCycle, $user, $validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Graded fish transferred.')]);

        return back();
    }
}
