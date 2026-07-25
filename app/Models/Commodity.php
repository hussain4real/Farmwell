<?php

namespace App\Models;

use App\Enums\FarmType;
use Database\Factories\CommodityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property FarmType $farm_type
 * @property string|null $measurement_unit
 * @property string|null $notes
 * @property-read Team $team
 */
#[Fillable(['team_id', 'name', 'farm_type', 'measurement_unit', 'notes'])]
class Commodity extends Model
{
    /** @use HasFactory<CommodityFactory> */
    use HasFactory;

    /**
     * Get the team that owns the commodity.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get production cycles that use this commodity.
     *
     * @return BelongsToMany<ProductionCycle, $this>
     */
    public function productionCycles(): BelongsToMany
    {
        return $this->belongsToMany(ProductionCycle::class)
            ->withPivot(['role', 'expected_output_quantity', 'expected_output_unit', 'notes'])
            ->withTimestamps();
    }

    /**
     * Get harvest records for this commodity.
     *
     * @return HasMany<HarvestRecord, $this>
     */
    public function harvestRecords(): HasMany
    {
        return $this->hasMany(HarvestRecord::class);
    }

    /**
     * Get sale records for this commodity.
     *
     * @return HasMany<SaleRecord, $this>
     */
    public function saleRecords(): HasMany
    {
        return $this->hasMany(SaleRecord::class);
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
        ];
    }
}
