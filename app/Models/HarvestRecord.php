<?php

namespace App\Models;

use App\Enums\HarvestRecordStatus;
use App\Enums\HarvestStage;
use App\Enums\InvestorVisibilityStatus;
use Database\Factories\HarvestRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $team_id
 * @property int $farm_id
 * @property int|null $production_unit_id
 * @property int|null $production_cycle_id
 * @property int $commodity_id
 * @property int|null $investor_agreement_id
 * @property int|null $recorded_by_id
 * @property Carbon $harvested_on
 * @property HarvestStage $stage
 * @property int $sequence_number
 * @property string $quantity
 * @property string $quantity_unit
 * @property string|null $quality_notes
 * @property int $labour_cost_minor
 * @property string $currency
 * @property HarvestRecordStatus $status
 * @property InvestorVisibilityStatus $investor_visibility_status
 * @property string|null $notes
 * @property-read Team $team
 * @property-read Farm $farm
 * @property-read Commodity $commodity
 */
class HarvestRecord extends Model implements HasMedia
{
    /** @use HasFactory<HarvestRecordFactory> */
    use HasFactory;

    use InteractsWithMedia;

    public const EvidenceCollection = 'harvest_evidence';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'team_id',
        'farm_id',
        'production_unit_id',
        'production_cycle_id',
        'commodity_id',
        'investor_agreement_id',
        'recorded_by_id',
        'harvested_on',
        'stage',
        'sequence_number',
        'quantity',
        'quantity_unit',
        'quality_notes',
        'labour_cost_minor',
        'currency',
        'status',
        'investor_visibility_status',
        'notes',
    ];

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
     * @return BelongsTo<ProductionUnit, $this>
     */
    public function productionUnit(): BelongsTo
    {
        return $this->belongsTo(ProductionUnit::class);
    }

    /**
     * @return BelongsTo<ProductionCycle, $this>
     */
    public function productionCycle(): BelongsTo
    {
        return $this->belongsTo(ProductionCycle::class);
    }

    /**
     * @return BelongsTo<Commodity, $this>
     */
    public function commodity(): BelongsTo
    {
        return $this->belongsTo(Commodity::class);
    }

    /**
     * @return BelongsTo<InvestorAgreement, $this>
     */
    public function investorAgreement(): BelongsTo
    {
        return $this->belongsTo(InvestorAgreement::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }

    /**
     * @return HasMany<SaleRecord, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(SaleRecord::class);
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
            'harvested_on' => 'date',
            'stage' => HarvestStage::class,
            'sequence_number' => 'integer',
            'quantity' => 'decimal:2',
            'labour_cost_minor' => 'integer',
            'status' => HarvestRecordStatus::class,
            'investor_visibility_status' => InvestorVisibilityStatus::class,
        ];
    }
}
