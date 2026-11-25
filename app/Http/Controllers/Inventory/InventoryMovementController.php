<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\RecordInventoryMovement;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreInventoryMovementRequest;
use App\Models\InventoryItem;
use App\Models\Team;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class InventoryMovementController extends Controller
{
    public function store(
        StoreInventoryMovementRequest $request,
        Team $currentTeam,
        InventoryItem $inventoryItem,
        RecordInventoryMovement $recordInventoryMovement,
    ): RedirectResponse {
        $user = $request->user();
        assert($user instanceof User);

        $validated = $request->validated();
        $unitCost = $validated['unit_cost'] ?? null;
        unset($validated['unit_cost']);

        $validated['unit_cost_minor'] = $unitCost !== null && $unitCost !== ''
            ? Money::toMinorUnit($unitCost)
            : null;

        $recordAsExpense = (bool) ($validated['record_as_expense'] ?? false);
        unset($validated['record_as_expense']);

        $recordInventoryMovement->handle($currentTeam, $inventoryItem, $user, $validated, $recordAsExpense);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Stock movement recorded.')]);

        return back();
    }
}
