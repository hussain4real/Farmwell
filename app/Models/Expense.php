<?php

namespace App\Models;

use App\Enums\ExpenseStatus;
use App\Enums\InvestorVisibilityStatus;
use Database\Factories\ExpenseFactory;
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
 * @property int|null $budget_id
 * @property int|null $budget_line_id
 * @property int|null $funding_phase_id
 * @property int|null $investor_agreement_id
 * @property int $expense_category_id
 * @property int|null $farm_activity_id
 * @property int|null $recorded_by_id
 * @property Carbon $incurred_on
 * @property string|null $vendor
 * @property string|null $payment_method
 * @property string $description
 * @property int $amount_minor
 * @property string $currency
 * @property ExpenseStatus $status
 * @property InvestorVisibilityStatus $investor_visibility_status
 * @property string|null $notes
 * @property-read Team $team
 * @property-read Farm $farm
 * @property-read ExpenseCategory $expenseCategory
 */
class Expense extends Model implements HasMedia
{
    /** @use HasFactory<ExpenseFactory> */
    use HasFactory;

    use InteractsWithMedia;

    public const ReceiptsCollection = 'receipts';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'team_id',
        'farm_id',
        'production_cycle_id',
        'budget_id',
        'budget_line_id',
        'funding_phase_id',
        'investor_agreement_id',
        'expense_category_id',
        'farm_activity_id',
        'recorded_by_id',
        'incurred_on',
        'vendor',
        'payment_method',
        'description',
        'amount_minor',
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
     * @return BelongsTo<BudgetLine, $this>
     */
    public function budgetLine(): BelongsTo
    {
        return $this->belongsTo(BudgetLine::class);
    }

    /**
     * @return BelongsTo<FundingPhase, $this>
     */
    public function fundingPhase(): BelongsTo
    {
        return $this->belongsTo(FundingPhase::class);
    }

    /**
     * @return BelongsTo<InvestorAgreement, $this>
     */
    public function investorAgreement(): BelongsTo
    {
        return $this->belongsTo(InvestorAgreement::class);
    }

    /**
     * @return BelongsTo<ExpenseCategory, $this>
     */
    public function expenseCategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    /**
     * @return BelongsTo<FarmActivity, $this>
     */
    public function farmActivity(): BelongsTo
    {
        return $this->belongsTo(FarmActivity::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_id');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::ReceiptsCollection)
            ->useDisk('farmwell_private');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'incurred_on' => 'date',
            'amount_minor' => 'integer',
            'status' => ExpenseStatus::class,
            'investor_visibility_status' => InvestorVisibilityStatus::class,
        ];
    }
}
