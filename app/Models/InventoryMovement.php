<?php

namespace App\Models;

use App\Enums\InventoryMovementType;
use Database\Factories\InventoryMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $inventory_item_id
 * @property int|null $farm_id
 * @property int|null $production_cycle_id
 * @property int|null $production_log_id
 * @property int|null $expense_id
 * @property int|null $recorded_by_id
 * @property InventoryMovementType $movement_type
 * @property string $quantity
 * @property int|null $unit_cost_minor
 * @property int $total_cost_minor
 * @property string $currency
 * @property Carbon $movement_date
 * @property string|null $reference
 * @property string|null $notes
 * @property-read Team $team
 * @property-read InventoryItem $inventoryItem
 * @property-read Farm|null $farm
 * @property-read ProductionCycle|null $productionCycle
 * @property-read ProductionLog|null $productionLog
 * @property-read Expense|null $expense
 * @property-read User|null $recordedBy
 */
#[Fillable([
    'team_id',
    'inventory_item_id',
    'farm_id',
    'production_cycle_id',
    'production_log_id',
    'expense_id',
    'recorded_by_id',
    'movement_type',
    'quantity',
    'unit_cost_minor',
    'total_cost_minor',
    'currency',
    'movement_date',
    'reference',
    'notes',
])]
class InventoryMovement extends Model
{
    /** @use HasFactory<InventoryMovementFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<InventoryItem, $this>
     */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    /**
     * @return BelongsTo<Farm, $this>
     */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    /**
     * @return BelongsTo<ProductionCycle, $this>
     */
    public function productionCycle(): BelongsTo
    {
        return $this->belongsTo(ProductionCycle::class);
    }

    /**
     * @return BelongsTo<ProductionLog, $this>
     */
    public function productionLog(): BelongsTo
    {
        return $this->belongsTo(ProductionLog::class);
    }

    /**
     * @return BelongsTo<Expense, $this>
     */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'movement_type' => InventoryMovementType::class,
            'quantity' => 'decimal:2',
            'unit_cost_minor' => 'integer',
            'total_cost_minor' => 'integer',
            'movement_date' => 'date',
        ];
    }
}
