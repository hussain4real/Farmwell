<?php

namespace App\Models;

use App\Enums\ProductionUnitType;
use Database\Factories\ProductionUnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $team_id
 * @property int $farm_id
 * @property string $name
 * @property ProductionUnitType $unit_type
 * @property string|null $size
 * @property string|null $size_unit
 * @property string|null $capacity
 * @property string $status
 * @property string|null $gps_coordinates
 * @property string|null $suitability_notes
 * @property string|null $history_notes
 * @property-read Team $team
 * @property-read Farm $farm
 */
#[Fillable([
    'team_id',
    'farm_id',
    'name',
    'unit_type',
    'size',
    'size_unit',
    'capacity',
    'status',
    'gps_coordinates',
    'suitability_notes',
    'history_notes',
])]
class ProductionUnit extends Model
{
    /** @use HasFactory<ProductionUnitFactory> */
    use HasFactory;

    /**
     * Get the team that owns the production unit.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the farm that owns the production unit.
     *
     * @return BelongsTo<Farm, $this>
     */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    /**
     * Get production cycles assigned to this unit.
     *
     * @return BelongsToMany<ProductionCycle, $this>
     */
    public function productionCycles(): BelongsToMany
    {
        return $this->belongsToMany(ProductionCycle::class)
            ->withTimestamps();
    }

    /**
     * Get activities recorded for this unit.
     *
     * @return HasMany<FarmActivity, $this>
     */
    public function farmActivities(): HasMany
    {
        return $this->hasMany(FarmActivity::class);
    }

    /**
     * Get tasks scheduled for this unit.
     *
     * @return HasMany<FarmTask, $this>
     */
    public function farmTasks(): HasMany
    {
        return $this->hasMany(FarmTask::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_type' => ProductionUnitType::class,
            'size' => 'decimal:2',
        ];
    }
}
