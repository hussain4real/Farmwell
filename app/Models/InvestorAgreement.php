<?php

namespace App\Models;

use App\Enums\CapitalRecoveryRule;
use App\Enums\InvestorAgreementStatus;
use Database\Factories\InvestorAgreementFactory;
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
 * @property int $investor_id
 * @property int $farm_id
 * @property int|null $production_cycle_id
 * @property int|null $created_by_id
 * @property string $title
 * @property InvestorAgreementStatus $status
 * @property string $currency
 * @property int $amount_committed_minor
 * @property int $amount_funded_minor
 * @property CapitalRecoveryRule $capital_recovery_rule
 * @property int $investor_profit_share_percentage
 * @property int $farm_profit_share_percentage
 * @property string|null $funding_model
 * @property string|null $role_responsibilities
 * @property string|null $public_notes
 * @property string|null $internal_notes
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property Carbon|null $signed_at
 * @property-read Team $team
 * @property-read User $investor
 * @property-read Farm $farm
 */
class InvestorAgreement extends Model implements HasMedia
{
    /** @use HasFactory<InvestorAgreementFactory> */
    use HasFactory;

    use InteractsWithMedia;

    public const DocumentsCollection = 'agreement_documents';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'team_id',
        'investor_id',
        'farm_id',
        'production_cycle_id',
        'created_by_id',
        'title',
        'status',
        'currency',
        'amount_committed_minor',
        'amount_funded_minor',
        'capital_recovery_rule',
        'investor_profit_share_percentage',
        'farm_profit_share_percentage',
        'funding_model',
        'role_responsibilities',
        'public_notes',
        'internal_notes',
        'starts_on',
        'ends_on',
        'signed_at',
    ];

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function investor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'investor_id');
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
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * @return HasMany<FundingPhase, $this>
     */
    public function fundingPhases(): HasMany
    {
        return $this->hasMany(FundingPhase::class);
    }

    /**
     * @return HasMany<Expense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * @return HasMany<ExternalTransfer, $this>
     */
    public function externalTransfers(): HasMany
    {
        return $this->hasMany(ExternalTransfer::class);
    }

    /**
     * @return HasMany<HarvestRecord, $this>
     */
    public function harvestRecords(): HasMany
    {
        return $this->hasMany(HarvestRecord::class);
    }

    /**
     * @return HasMany<SaleRecord, $this>
     */
    public function saleRecords(): HasMany
    {
        return $this->hasMany(SaleRecord::class);
    }

    /**
     * @return HasMany<DistributionRecord, $this>
     */
    public function distributionRecords(): HasMany
    {
        return $this->hasMany(DistributionRecord::class);
    }

    /**
     * @return HasMany<ApprovalRequest, $this>
     */
    public function approvalRequests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class);
    }

    /**
     * @return HasMany<InvestorComment, $this>
     */
    public function investorComments(): HasMany
    {
        return $this->hasMany(InvestorComment::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::DocumentsCollection)
            ->singleFile()
            ->useDisk('farmwell_private');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvestorAgreementStatus::class,
            'amount_committed_minor' => 'integer',
            'amount_funded_minor' => 'integer',
            'capital_recovery_rule' => CapitalRecoveryRule::class,
            'investor_profit_share_percentage' => 'integer',
            'farm_profit_share_percentage' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'signed_at' => 'datetime',
        ];
    }
}
