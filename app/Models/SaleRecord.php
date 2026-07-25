<?php

namespace App\Models;

use App\Enums\InvestorVisibilityStatus;
use App\Enums\SalePaymentStatus;
use Database\Factories\SaleRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $team_id
 * @property int $farm_id
 * @property int|null $production_cycle_id
 * @property int $harvest_record_id
 * @property int $commodity_id
 * @property int|null $investor_agreement_id
 * @property int|null $recorded_by_id
 * @property Carbon $sold_on
 * @property string $buyer_name
 * @property string $quantity
 * @property string $quantity_unit
 * @property int $unit_price_minor
 * @property int $gross_amount_minor
 * @property int $deduction_amount_minor
 * @property int $net_amount_minor
 * @property string $currency
 * @property SalePaymentStatus $payment_status
 * @property string|null $reference
 * @property InvestorVisibilityStatus $investor_visibility_status
 * @property string|null $notes
 * @property-read Team $team
 * @property-read Farm $farm
 * @property-read HarvestRecord $harvestRecord
 * @property-read Commodity $commodity
 */
class SaleRecord extends Model implements HasMedia
{
    /** @use HasFactory<SaleRecordFactory> */
    use HasFactory;

    use InteractsWithMedia;

    public const EvidenceCollection = 'sale_evidence';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'team_id',
        'farm_id',
        'production_cycle_id',
        'harvest_record_id',
        'commodity_id',
        'investor_agreement_id',
        'recorded_by_id',
        'sold_on',
        'buyer_name',
        'quantity',
        'quantity_unit',
        'unit_price_minor',
        'gross_amount_minor',
        'deduction_amount_minor',
        'net_amount_minor',
        'currency',
        'payment_status',
        'reference',
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
     * @return BelongsTo<ProductionCycle, $this>
     */
    public function productionCycle(): BelongsTo
    {
        return $this->belongsTo(ProductionCycle::class);
    }

    /**
     * @return BelongsTo<HarvestRecord, $this>
     */
    public function harvestRecord(): BelongsTo
    {
        return $this->belongsTo(HarvestRecord::class);
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
     * @return HasOne<DistributionRecord, $this>
     */
    public function distributionRecord(): HasOne
    {
        return $this->hasOne(DistributionRecord::class);
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
            'sold_on' => 'date',
            'quantity' => 'decimal:2',
            'unit_price_minor' => 'integer',
            'gross_amount_minor' => 'integer',
            'deduction_amount_minor' => 'integer',
            'net_amount_minor' => 'integer',
            'payment_status' => SalePaymentStatus::class,
            'investor_visibility_status' => InvestorVisibilityStatus::class,
        ];
    }
}
