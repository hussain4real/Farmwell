<?php

namespace App\Models;

use App\Enums\FarmActivityStatus;
use Database\Factories\FarmActivityFactory;
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
 * @property int|null $production_unit_id
 * @property int|null $production_cycle_id
 * @property int|null $commodity_id
 * @property int|null $recorded_by_id
 * @property Carbon $activity_date
 * @property string $activity_type
 * @property string $description
 * @property string|null $inputs_used
 * @property string|null $labour_used
 * @property string|null $cost
 * @property string|null $remarks
 * @property string|null $next_activity
 * @property FarmActivityStatus $status
 * @property string|null $internal_notes
 * @property string|null $investor_safe_summary
 * @property string $source_type
 * @property int|null $source_reference_id
 * @property-read Team $team
 * @property-read Farm $farm
 * @property-read ProductionUnit|null $productionUnit
 * @property-read ProductionCycle|null $productionCycle
 * @property-read Commodity|null $commodity
 * @property-read User|null $recordedBy
 */
#[Fillable([
    'team_id',
    'farm_id',
    'production_unit_id',
    'production_cycle_id',
    'commodity_id',
    'recorded_by_id',
    'activity_date',
    'activity_type',
    'description',
    'inputs_used',
    'labour_used',
    'cost',
    'remarks',
    'next_activity',
    'status',
    'internal_notes',
    'investor_safe_summary',
    'source_type',
    'source_reference_id',
])]
class FarmActivity extends Model implements HasMedia
{
    /** @use HasFactory<FarmActivityFactory> */
    use HasFactory;

    use InteractsWithMedia;

    public const EvidenceCollection = 'evidence';

    /**
     * Get the team that owns the activity.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the farm where the activity happened.
     *
     * @return BelongsTo<Farm, $this>
     */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    /**
     * Get the production unit linked to the activity.
     *
     * @return BelongsTo<ProductionUnit, $this>
     */
    public function productionUnit(): BelongsTo
    {
        return $this->belongsTo(ProductionUnit::class);
    }

    /**
     * Get the production cycle linked to the activity.
     *
     * @return BelongsTo<ProductionCycle, $this>
     */
    public function productionCycle(): BelongsTo
    {
        return $this->belongsTo(ProductionCycle::class);
    }

    /**
     * Get the commodity linked to the activity.
     *
     * @return BelongsTo<Commodity, $this>
     */
    public function commodity(): BelongsTo
    {
        return $this->belongsTo(Commodity::class);
    }

    /**
     * Get the user who recorded the activity.
     *
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::EvidenceCollection)
            ->useDisk('farmwell_private');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activity_date' => 'date',
            'cost' => 'decimal:2',
            'status' => FarmActivityStatus::class,
            'source_reference_id' => 'integer',
        ];
    }
}
