<?php

namespace App\Models;

use App\Enums\ProductionPlanChangeType;
use Database\Factories\ProductionPlanChangeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $team_id
 * @property int $production_cycle_id
 * @property int|null $actor_id
 * @property ProductionPlanChangeType $change_type
 * @property string $reason
 * @property string $impact
 * @property string|null $investor_safe_summary
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property-read Team $team
 * @property-read ProductionCycle $productionCycle
 * @property-read User|null $actor
 */
#[Fillable([
    'team_id',
    'production_cycle_id',
    'actor_id',
    'change_type',
    'reason',
    'impact',
    'investor_safe_summary',
    'old_values',
    'new_values',
])]
class ProductionPlanChange extends Model
{
    /** @use HasFactory<ProductionPlanChangeFactory> */
    use HasFactory;

    /**
     * Get the team that owns the plan change.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the production cycle this change belongs to.
     *
     * @return BelongsTo<ProductionCycle, $this>
     */
    public function productionCycle(): BelongsTo
    {
        return $this->belongsTo(ProductionCycle::class);
    }

    /**
     * Get the user who recorded the change.
     *
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'change_type' => ProductionPlanChangeType::class,
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }
}
