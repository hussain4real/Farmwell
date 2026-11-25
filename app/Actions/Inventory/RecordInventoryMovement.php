<?php

namespace App\Actions\Inventory;

use App\Actions\Audit\RecordAuditEvent;
use App\Actions\Finance\EnsureDefaultExpenseCategories;
use App\Actions\Finance\RecordExpense;
use App\Enums\ExpenseStatus;
use App\Enums\InventoryMovementType;
use App\Enums\InvestorVisibilityStatus;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordInventoryMovement
{
    public function __construct(
        private RecordAuditEvent $recordAuditEvent,
        private RecordExpense $recordExpense,
        private EnsureDefaultExpenseCategories $ensureDefaultExpenseCategories,
    ) {
        //
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(
        Team $team,
        InventoryItem $item,
        User $actor,
        array $attributes,
        bool $recordAsExpense = false,
    ): InventoryMovement {
        return DB::transaction(function () use ($team, $item, $actor, $attributes, $recordAsExpense) {
            $lockedItem = InventoryItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();

            $movementType = $attributes['movement_type'] instanceof InventoryMovementType
                ? $attributes['movement_type']
                : InventoryMovementType::from((string) $attributes['movement_type']);

            $quantity = round((float) $attributes['quantity'], 2);
            $currentQuantity = (float) $lockedItem->current_quantity;

            if ($movementType->isDeduction()) {
                if ($quantity > $currentQuantity) {
                    throw ValidationException::withMessages([
                        'quantity' => __('Insufficient stock for :item (available: :qty :unit).', [
                            'item' => $lockedItem->name,
                            'qty' => number_format($currentQuantity, 2),
                            'unit' => $lockedItem->unit,
                        ]),
                    ]);
                }

                $nextQuantity = round($currentQuantity - $quantity, 2);
            } elseif ($movementType === InventoryMovementType::Adjustment) {
                $direction = (string) ($attributes['adjustment_direction'] ?? 'in');

                if ($direction === 'out') {
                    if ($quantity > $currentQuantity) {
                        throw ValidationException::withMessages([
                            'quantity' => __('Adjustment exceeds available stock.'),
                        ]);
                    }

                    $nextQuantity = round($currentQuantity - $quantity, 2);
                } else {
                    $nextQuantity = round($currentQuantity + $quantity, 2);
                }
            } else {
                $nextQuantity = round($currentQuantity + $quantity, 2);
            }

            $unitCostMinor = isset($attributes['unit_cost_minor']) && $attributes['unit_cost_minor'] !== null
                ? (int) $attributes['unit_cost_minor']
                : $lockedItem->unit_cost_minor;

            $totalCostMinor = (int) round($quantity * $unitCostMinor);

            if ($movementType === InventoryMovementType::Purchase && $unitCostMinor > 0) {
                $lockedItem->unit_cost_minor = $unitCostMinor;
            }

            $lockedItem->current_quantity = number_format($nextQuantity, 2, '.', '');
            $lockedItem->save();

            $farmId = $attributes['farm_id'] ?? $lockedItem->farm_id;
            $expenseId = null;

            if (
                $recordAsExpense
                && $movementType === InventoryMovementType::Purchase
                && $farmId !== null
                && $totalCostMinor > 0
                && $actor->can('manageFinance', $team)
            ) {
                $this->ensureDefaultExpenseCategories->handle($team);
                $category = $team->expenseCategories()
                    ->where('slug', $lockedItem->category->expenseCategorySlug())
                    ->firstOrFail();

                $expense = $this->recordExpense->handle($team, $actor, [
                    'farm_id' => (int) $farmId,
                    'production_cycle_id' => $attributes['production_cycle_id'] ?? null,
                    'expense_category_id' => $category->id,
                    'incurred_on' => $attributes['movement_date'] ?? now()->toDateString(),
                    'vendor' => $lockedItem->supplier_name,
                    'description' => sprintf('Stock purchase: %s (%s %s)', $lockedItem->name, number_format($quantity, 2, '.', ''), $lockedItem->unit),
                    'amount_minor' => $totalCostMinor,
                    'status' => ExpenseStatus::Approved->value,
                    'investor_visibility_status' => InvestorVisibilityStatus::Private->value,
                ]);

                $expenseId = $expense->id;
            }

            $movement = InventoryMovement::create([
                'team_id' => $team->id,
                'inventory_item_id' => $lockedItem->id,
                'farm_id' => $farmId,
                'production_cycle_id' => $attributes['production_cycle_id'] ?? null,
                'production_log_id' => $attributes['production_log_id'] ?? null,
                'expense_id' => $expenseId,
                'recorded_by_id' => $actor->id,
                'movement_type' => $movementType,
                'quantity' => number_format($quantity, 2, '.', ''),
                'unit_cost_minor' => $unitCostMinor,
                'total_cost_minor' => $totalCostMinor,
                'currency' => $lockedItem->currency,
                'movement_date' => $attributes['movement_date'] ?? now()->toDateString(),
                'reference' => $attributes['reference'] ?? null,
                'notes' => $attributes['notes'] ?? null,
            ]);

            $this->recordAuditEvent->handle(
                team: $team,
                actor: $actor,
                action: 'inventory_movement.recorded',
                subject: $movement,
                newValues: [
                    'inventory_item_id' => $lockedItem->id,
                    'movement_type' => $movementType->value,
                    'quantity' => $movement->quantity,
                    'total_cost_minor' => $totalCostMinor,
                    'new_balance' => $lockedItem->current_quantity,
                ],
            );

            return $movement;
        });
    }
}
