<?php

namespace App\Models;

use App\Enums\HealthEventStatus;
use App\Enums\HealthEventType;
use Carbon\CarbonInterface;
use Database\Factories\HealthEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $team_id
 * @property int $farm_id
 * @property int|null $production_cycle_id
 * @property int|null $production_unit_id
 * @property int|null $inventory_item_id
 * @property int|null $recorded_by_id
 * @property HealthEventType $event_type
 * @property string $title
 * @property string|null $product_name
 * @property string|null $dosage
 * @property string|null $administration_route
 * @property CarbonInterface $scheduled_on
 * @property CarbonInterface|null $completed_on
 * @property int|null $withdrawal_days
 * @property HealthEventStatus $status
 * @property int|null $affected_count
 * @property int $cost_minor
 * @property string $currency
 * @property string|null $notes
 * @property string|null $outcome_notes
 * @property-read Team $team
 * @property-read Farm $farm
 * @property-read ProductionCycle|null $productionCycle
 * @property-read ProductionUnit|null $productionUnit
 * @property-read InventoryItem|null $inventoryItem
 * @property-read User|null $recordedBy
 */
#[Fillable([
    'team_id',
    'farm_id',
    'production_cycle_id',
    'production_unit_id',
    'inventory_item_id',
    'recorded_by_id',
    'event_type',
    'title',
    'product_name',
    'dosage',
    'administration_route',
    'scheduled_on',
    'completed_on',
    'withdrawal_days',
    'status',
    'affected_count',
    'cost_minor',
    'currency',
    'notes',
    'outcome_notes',
])]
class HealthEvent extends Model
{
    /** @use HasFactory<HealthEventFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
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
     * @return BelongsTo<ProductionUnit, $this>
     */
    public function productionUnit(): BelongsTo
    {
        return $this->belongsTo(ProductionUnit::class);
    }

    /**
     * @return BelongsTo<InventoryItem, $this>
     */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }

    public function withdrawalEndsOn(): ?CarbonInterface
    {
        if ($this->completed_on === null || $this->withdrawal_days === null || $this->withdrawal_days <= 0) {
            return null;
        }

        return $this->completed_on->copy()->addDays($this->withdrawal_days);
    }

    public function isUnderWithdrawal(): bool
    {
        $endsOn = $this->withdrawalEndsOn();

        return $this->status === HealthEventStatus::Completed
            && $endsOn !== null
            && $endsOn->isFuture();
    }

    public function isOverdue(): bool
    {
        return $this->status === HealthEventStatus::Scheduled
            && $this->scheduled_on->lt(today());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_type' => HealthEventType::class,
            'status' => HealthEventStatus::class,
            'scheduled_on' => 'date',
            'completed_on' => 'date',
            'withdrawal_days' => 'integer',
            'affected_count' => 'integer',
            'cost_minor' => 'integer',
        ];
    }
}
