<?php

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\BuildInventoryPageData;
use App\Actions\Inventory\CreateInventoryItem;
use App\Actions\Inventory\UpdateInventoryItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreInventoryItemRequest;
use App\Http\Requests\Inventory\UpdateInventoryItemRequest;
use App\Models\InventoryItem;
use App\Models\Team;
use App\Models\User;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class InventoryItemController extends Controller
{
    public function index(Request $request, Team $currentTeam, BuildInventoryPageData $pageData): Response
    {
        Gate::authorize('viewFieldWork', $currentTeam);

        $user = $request->user();
        assert($user instanceof User);

        return Inertia::render('inventory/Index', $pageData->handle($currentTeam, $user));
    }

    public function store(
        StoreInventoryItemRequest $request,
        Team $currentTeam,
        CreateInventoryItem $createInventoryItem,
    ): RedirectResponse {
        $user = $request->user();
        assert($user instanceof User);

        $validated = $request->validated();
        $unitCost = $validated['unit_cost'] ?? null;
        unset($validated['unit_cost']);

        $validated['unit_cost_minor'] = $unitCost !== null && $unitCost !== ''
            ? Money::toMinorUnit($unitCost)
            : 0;
        $validated['current_quantity'] = $validated['current_quantity'] ?? 0;
        $validated['reorder_level'] = $validated['reorder_level'] ?? 0;

        $createInventoryItem->handle($currentTeam, $user, $validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Inventory item added.')]);

        return back();
    }

    public function update(
        UpdateInventoryItemRequest $request,
        Team $currentTeam,
        InventoryItem $inventoryItem,
        UpdateInventoryItem $updateInventoryItem,
    ): RedirectResponse {
        $user = $request->user();
        assert($user instanceof User);

        $validated = $request->validated();
        if (array_key_exists('unit_cost', $validated)) {
            $unitCost = $validated['unit_cost'];
            unset($validated['unit_cost']);
            $validated['unit_cost_minor'] = $unitCost !== null && $unitCost !== ''
                ? Money::toMinorUnit($unitCost)
                : 0;
        }

        $updateInventoryItem->handle($currentTeam, $inventoryItem, $user, $validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Inventory item updated.')]);

        return back();
    }
}
