<?php

namespace App\Models;

use App\Enums\ExternalTransferDirection;
use App\Enums\ExternalTransferStatus;
use App\Enums\InvestorVisibilityStatus;
use Database\Factories\ExternalTransferFactory;
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
 * @property int|null $production_cycle_id
 * @property int|null $budget_id
 * @property int|null $funding_phase_id
 * @property int|null $expense_id
 * @property int|null $investor_agreement_id
 * @property int|null $recorded_by_id
 * @property ExternalTransferDirection $direction
 * @property string $transfer_type
 * @property ExternalTransferStatus $status
 * @property InvestorVisibilityStatus $investor_visibility_status
 * @property string|null $counterparty_name
 * @property string|null $reference
 * @property int $amount_minor
 * @property string $currency
 * @property Carbon $transferred_on
 * @property string|null $notes
 * @property-read Team $team
 * @property-read Farm $farm
 */
class ExternalTransfer extends Model implements HasMedia
{
    /** @use HasFactory<ExternalTransferFactory> */
    use HasFactory;

    use InteractsWithMedia;

    public const ProofCollection = 'transfer_proof';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'team_id',
        'farm_id',
        'production_cycle_id',
        'budget_id',
        'funding_phase_id',
        'expense_id',
        'investor_agreement_id',
        'recorded_by_id',
        'direction',
        'transfer_type',
        'status',
        'investor_visibility_status',
        'counterparty_name',
        'reference',
        'amount_minor',
        'currency',
        'transferred_on',
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
     * @return BelongsTo<Budget, $this>
     */
    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    /**
     * @return BelongsTo<FundingPhase, $this>
     */
    public function fundingPhase(): BelongsTo
    {
        return $this->belongsTo(FundingPhase::class);
    }

    /**
     * @return BelongsTo<Expense, $this>
     */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
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
     * @return HasMany<TransferReconciliation, $this>
     */
    public function reconciliations(): HasMany
    {
        return $this->hasMany(TransferReconciliation::class);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::ProofCollection)
            ->singleFile()
            ->useDisk('farmwell_private');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => ExternalTransferDirection::class,
            'status' => ExternalTransferStatus::class,
            'investor_visibility_status' => InvestorVisibilityStatus::class,
            'amount_minor' => 'integer',
            'transferred_on' => 'date',
        ];
    }
}
