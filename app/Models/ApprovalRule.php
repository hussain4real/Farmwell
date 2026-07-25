<?php

namespace App\Models;

use App\Enums\ApprovalRequestType;
use App\Enums\ApprovalTriggerType;
use Database\Factories\ApprovalRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $team_id
 * @property int|null $investor_agreement_id
 * @property int|null $expense_category_id
 * @property int|null $funding_phase_id
 * @property ApprovalRequestType $request_type
 * @property ApprovalTriggerType $trigger_type
 * @property int|null $threshold_amount_minor
 * @property string $currency
 * @property bool $is_active
 */
class ApprovalRule extends Model
{
    /** @use HasFactory<ApprovalRuleFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'team_id',
        'investor_agreement_id',
        'expense_category_id',
        'funding_phase_id',
        'created_by_id',
        'name',
        'request_type',
        'trigger_type',
        'threshold_amount_minor',
        'currency',
        'farm_type',
        'is_active',
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
     * @return BelongsTo<FundingPhase, $this>
     */
    public function fundingPhase(): BelongsTo
    {
        return $this->belongsTo(FundingPhase::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'request_type' => ApprovalRequestType::class,
            'trigger_type' => ApprovalTriggerType::class,
            'threshold_amount_minor' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
