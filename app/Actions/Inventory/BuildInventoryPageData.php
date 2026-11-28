<?php

namespace App\Actions\Inventory;

use App\Actions\Finance\ResolveTeamFinanceCurrency;
use App\Enums\InventoryCategory;
use App\Enums\InventoryMovementType;
use App\Models\Farm;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\ProductionCycle;
use App\Models\Team;
use App\Models\User;
use App\Support\Money;

class BuildInventoryPageData
{
    public function __construct(private ResolveTeamFinanceCurrency $resolveTeamFinanceCurrency)
    {
        //
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(Team $team, User $user): array
    {
        $currency = $this->resolveTeamFinanceCurrency->handle($team);
        $items = $team->inventoryItems()
            ->with('farm')
            ->orderBy('name')
            ->get();

        $movements = $team->inventoryMovements()
            ->with(['inventoryItem', 'farm', 'productionCycle', 'recordedBy'])
            ->latest('movement_date')
            ->latest()
            ->limit(30)
            ->get();

        $totalValueMinor = (int) $items
            ->where('is_active', true)
            ->sum(fn (InventoryItem $item): int => $item->stockValueMinor());

        $lowStockItems = $items->filter(fn (InventoryItem $item): bool => $item->isLowStock());

        $recentUsageCostMinor = (int) $team->inventoryMovements()
            ->where('movement_type', InventoryMovementType::Usage->value)
            ->where('movement_date', '>=', now()->subDays(30)->toDateString())
            ->sum('total_cost_minor');

        return [
            'permissions' => $user->toTeamPermissions($team),
            'currency' => $currency,
            'summary' => [
                'totalItems' => $items->where('is_active', true)->count(),
                'lowStockCount' => $lowStockItems->count(),
                'totalStockValueMinor' => $totalValueMinor,
                'totalStockValue' => Money::toDecimal($totalValueMinor),
                'recentUsageCostMinor' => $recentUsageCostMinor,
                'recentUsageCost' => Money::toDecimal($recentUsageCostMinor),
            ],
            'items' => $items->map(fn (InventoryItem $item): array => $this->itemPayload($item))->values(),
            'movements' => $movements->map(fn (InventoryMovement $movement): array => $this->movementPayload($movement))->values(),
            'farms' => $team->farms()
                ->with(['productionCycles' => fn ($query) => $query->latest()])
                ->orderBy('name')
                ->get()
                ->map(fn (Farm $farm) => [
                    'id' => $farm->id,
                    'name' => $farm->name,
                    'productionCycles' => $farm->productionCycles->map(fn (ProductionCycle $cycle) => [
                        'id' => $cycle->id,
                        'name' => $cycle->name,
                    ])->values()->all(),
                ])->values()->all(),
            'options' => [
                'categories' => InventoryCategory::options(),
                'movementTypes' => InventoryMovementType::options(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function itemPayload(InventoryItem $item): array
    {
        return [
            'id' => $item->id,
            'farmId' => $item->farm_id,
            'farmName' => $item->farm?->name,
            'name' => $item->name,
            'category' => $item->category->value,
            'categoryLabel' => $item->category->label(),
            'sku' => $item->sku,
            'unit' => $item->unit,
            'unitWeightKg' => $item->unit_weight_kg,
            'currentQuantity' => $item->current_quantity,
            'reorderLevel' => $item->reorder_level,
            'isLowStock' => $item->isLowStock(),
            'unitCostMinor' => $item->unit_cost_minor,
            'unitCost' => Money::toDecimal($item->unit_cost_minor),
            'stockValueMinor' => $item->stockValueMinor(),
            'stockValue' => Money::toDecimal($item->stockValueMinor()),
            'currency' => $item->currency,
            'supplierName' => $item->supplier_name,
            'notes' => $item->notes,
            'isActive' => $item->is_active,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function movementPayload(InventoryMovement $movement): array
    {
        return [
            'id' => $movement->id,
            'inventoryItemId' => $movement->inventory_item_id,
            'itemName' => $movement->inventoryItem->name,
            'itemUnit' => $movement->inventoryItem->unit,
            'farmId' => $movement->farm_id,
            'farmName' => $movement->farm?->name,
            'productionCycleId' => $movement->production_cycle_id,
            'productionCycleName' => $movement->productionCycle?->name,
            'movementType' => $movement->movement_type->value,
            'movementTypeLabel' => $movement->movement_type->label(),
            'isDeduction' => $movement->movement_type->isDeduction(),
            'quantity' => $movement->quantity,
            'totalCostMinor' => $movement->total_cost_minor,
            'totalCost' => Money::toDecimal($movement->total_cost_minor),
            'currency' => $movement->currency,
            'movementDate' => $movement->movement_date->toDateString(),
            'reference' => $movement->reference,
            'notes' => $movement->notes,
            'recordedBy' => $movement->recordedBy?->name,
        ];
    }
}
