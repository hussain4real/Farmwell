<?php

namespace App\Models;

use App\Enums\WhatsappIntakeStatus;
use Database\Factories\WhatsappIntakeFactory;
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
 * @property int|null $farm_id
 * @property int|null $production_unit_id
 * @property int|null $production_cycle_id
 * @property int|null $commodity_id
 * @property int|null $imported_by_id
 * @property int|null $reviewer_id
 * @property int|null $converted_activity_id
 * @property string $source_message
 * @property string|null $source_sender
 * @property Carbon|null $source_date
 * @property Carbon|null $normalized_activity_date
 * @property string|null $normalized_activity_type
 * @property string|null $normalized_description
 * @property string|null $normalized_cost
 * @property string|null $normalized_next_activity
 * @property string|null $normalized_investor_safe_summary
 * @property WhatsappIntakeStatus $review_status
 * @property Carbon|null $reviewed_at
 * @property string|null $rejection_reason
 * @property-read Team $team
 * @property-read Farm|null $farm
 * @property-read ProductionUnit|null $productionUnit
 * @property-read ProductionCycle|null $productionCycle
 * @property-read Commodity|null $commodity
 * @property-read User|null $importedBy
 * @property-read User|null $reviewer
 * @property-read FarmActivity|null $convertedActivity
 */
#[Fillable([
    'team_id',
    'farm_id',
    'production_unit_id',
    'production_cycle_id',
    'commodity_id',
    'imported_by_id',
    'reviewer_id',
    'converted_activity_id',
    'source_message',
    'source_sender',
    'source_date',
    'normalized_activity_date',
    'normalized_activity_type',
    'normalized_description',
    'normalized_cost',
    'normalized_next_activity',
    'normalized_investor_safe_summary',
    'review_status',
    'reviewed_at',
    'rejection_reason',
])]
class WhatsappIntake extends Model implements HasMedia
{
    /** @use HasFactory<WhatsappIntakeFactory> */
    use HasFactory;

    use InteractsWithMedia;

    public const EvidenceCollection = 'evidence';

    /**
     * Get the team that owns the intake.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the suggested farm for this intake.
     *
     * @return BelongsTo<Farm, $this>
     */
    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    /**
     * Get the suggested production unit for this intake.
     *
     * @return BelongsTo<ProductionUnit, $this>
     */
    public function productionUnit(): BelongsTo
    {
        return $this->belongsTo(ProductionUnit::class);
    }

    /**
     * Get the suggested production cycle for this intake.
     *
     * @return BelongsTo<ProductionCycle, $this>
     */
    public function productionCycle(): BelongsTo
    {
        return $this->belongsTo(ProductionCycle::class);
    }

    /**
     * Get the suggested commodity for this intake.
     *
     * @return BelongsTo<Commodity, $this>
     */
    public function commodity(): BelongsTo
    {
        return $this->belongsTo(Commodity::class);
    }

    /**
     * Get the user who imported this intake.
     *
     * @return BelongsTo<User, $this>
     */
    public function importedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_by_id');
    }

    /**
     * Get the user who reviewed this intake.
     *
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * Get the official activity created from this intake.
     *
     * @return BelongsTo<FarmActivity, $this>
     */
    public function convertedActivity(): BelongsTo
    {
        return $this->belongsTo(FarmActivity::class, 'converted_activity_id');
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
            'source_date' => 'date',
            'normalized_activity_date' => 'date',
            'normalized_cost' => 'decimal:2',
            'review_status' => WhatsappIntakeStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }
}
