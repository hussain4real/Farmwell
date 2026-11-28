<?php

namespace App\Models;

use App\Enums\CropGrowthStage;
use App\Enums\PestPressure;
use App\Enums\ProductionLogDomain;
use Database\Factories\ProductionLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $team_id
 * @property int $farm_id
 * @property int|null $production_cycle_id
 * @property int|null $production_unit_id
 * @property int|null $recorded_by_id
 * @property int|null $inventory_item_id
 * @property Carbon $log_date
 * @property ProductionLogDomain $domain
 * @property int $mortality_count
 * @property int $cull_count
 * @property int $graded_out_count
 * @property string|null $feed_used_kg
 * @property string|null $feed_type
 * @property string|null $feed_pellet_size_mm
 * @property string|null $water_used_liters
 * @property string|null $average_weight_g
 * @property int|null $sample_size
 * @property int|null $eggs_collected
 * @property int|null $eggs_damaged
 * @property string|null $water_ph
 * @property string|null $water_temp_c
 * @property string|null $dissolved_oxygen_mg_l
 * @property string|null $ammonia_mg_l
 * @property bool $water_exchanged
 * @property CropGrowthStage|null $growth_stage
 * @property string|null $rainfall_mm
 * @property bool $irrigation_applied
 * @property PestPressure|null $pest_pressure
 * @property string|null $disease_observed
 * @property string|null $input_applied_name
 * @property string|null $input_applied_quantity
 * @property string|null $input_applied_unit
 * @property string|null $weather_notes
 * @property string|null $notes
 * @property array<string, mixed>|null $metrics
 * @property-read Team $team
 * @property-read Farm $farm
 * @property-read ProductionCycle|null $productionCycle
 * @property-read ProductionUnit|null $productionUnit
 * @property-read User|null $recordedBy
 * @property-read InventoryItem|null $inventoryItem
 */
#[Fillable([
    'team_id',
    'farm_id',
    'production_cycle_id',
    'production_unit_id',
    'recorded_by_id',
    'inventory_item_id',
    'log_date',
    'domain',
    'mortality_count',
    'cull_count',
    'graded_out_count',
    'feed_used_kg',
    'feed_type',
    'feed_pellet_size_mm',
    'water_used_liters',
    'average_weight_g',
    'sample_size',
    'eggs_collected',
    'eggs_damaged',
    'water_ph',
    'water_temp_c',
    'dissolved_oxygen_mg_l',
    'ammonia_mg_l',
    'water_exchanged',
    'growth_stage',
    'rainfall_mm',
    'irrigation_applied',
    'pest_pressure',
    'disease_observed',
    'input_applied_name',
    'input_applied_quantity',
    'input_applied_unit',
    'weather_notes',
    'notes',
    'metrics',
])]
class ProductionLog extends Model implements HasMedia
{
    /** @use HasFactory<ProductionLogFactory> */
    use HasFactory;

    use InteractsWithMedia;

    public const EvidenceCollection = 'production_log_evidence';

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
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }

    /**
     * @return BelongsTo<InventoryItem, $this>
     */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::EvidenceCollection)
            ->useDisk('farmwell_private');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'log_date' => 'date',
            'domain' => ProductionLogDomain::class,
            'mortality_count' => 'integer',
            'cull_count' => 'integer',
            'graded_out_count' => 'integer',
            'feed_used_kg' => 'decimal:2',
            'water_used_liters' => 'decimal:2',
            'average_weight_g' => 'decimal:2',
            'sample_size' => 'integer',
            'eggs_collected' => 'integer',
            'eggs_damaged' => 'integer',
            'water_ph' => 'decimal:2',
            'water_temp_c' => 'decimal:2',
            'dissolved_oxygen_mg_l' => 'decimal:2',
            'ammonia_mg_l' => 'decimal:3',
            'water_exchanged' => 'boolean',
            'growth_stage' => CropGrowthStage::class,
            'rainfall_mm' => 'decimal:2',
            'irrigation_applied' => 'boolean',
            'pest_pressure' => PestPressure::class,
            'input_applied_quantity' => 'decimal:2',
            'metrics' => 'array',
        ];
    }
}
