<?php

namespace App\Models;

use App\Enums\FarmType;
use App\Enums\ProductionCycleStatus;
use Database\Factories\ProductionCycleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $farm_id
 * @property string $name
 * @property string|null $season
 * @property FarmType $farm_type
 * @property string|null $production_method
 * @property Carbon $planned_start_on
 * @property Carbon|null $planned_end_on
 * @property Carbon|null $actual_start_on
 * @property Carbon|null $actual_end_on
 * @property string|null $expected_output_quantity
 * @property string|null $expected_output_unit
 * @property ProductionCycleStatus $status
 * @property int $plan_version
 * @property string|null $plan_summary
 * @property-read Team $team
 * @property-read Farm $farm
 */
#[Fillable([
    'team_id',
    'farm_id',
    'name',
    'season',
    'farm_type',
    'production_method',
    'planned_start_on',
    'planned_end_on',
    'actual_start_on',
    'actual_end_on',
    'expected_output_quantity',
    'expected_output_unit',
    'status',
    'plan_version',
    'plan_summary',
])]
class ProductionCycle extends Model
{
    /** @use HasFactory<ProductionCycleFactory> */
    use HasFactory;

    /**
     * Get the team that owns the production cycle.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the farm that owns the production cycle.
     *
     * @return BelongsTo<Farm, $this>
     */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    /**
     * Get production units assigned to the cycle.
     *
     * @return BelongsToMany<ProductionUnit, $this>
     */
    public function productionUnits(): BelongsToMany
    {
        return $this->belongsToMany(ProductionUnit::class)
            ->withTimestamps();
    }

    /**
     * Get commodities planned for the cycle.
     *
     * @return BelongsToMany<Commodity, $this>
     */
    public function commodities(): BelongsToMany
    {
        return $this->belongsToMany(Commodity::class)
            ->withPivot(['role', 'expected_output_quantity', 'expected_output_unit', 'notes'])
            ->withTimestamps();
    }

    /**
     * Get plan changes recorded against the cycle.
     *
     * @return HasMany<ProductionPlanChange, $this>
     */
    public function planChanges(): HasMany
    {
        return $this->hasMany(ProductionPlanChange::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'farm_type' => FarmType::class,
            'planned_start_on' => 'date',
            'planned_end_on' => 'date',
            'actual_start_on' => 'date',
            'actual_end_on' => 'date',
            'expected_output_quantity' => 'decimal:2',
            'status' => ProductionCycleStatus::class,
            'plan_version' => 'integer',
        ];
    }
}
