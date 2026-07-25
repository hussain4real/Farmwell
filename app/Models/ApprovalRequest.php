<?php

namespace App\Models;

use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalRequestType;
use App\Enums\ApprovalTriggerType;
use Database\Factories\ApprovalRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $team_id
 * @property int $investor_agreement_id
 * @property int|null $approval_rule_id
 * @property ApprovalRequestType $request_type
 * @property ApprovalTriggerType $trigger_type
 * @property ApprovalRequestStatus $status
 * @property int|null $threshold_amount_minor
 * @property int $requested_amount_minor
 * @property int|null $approved_amount_minor
 * @property string $currency
 * @property Carbon $requested_at
 * @property Carbon|null $decided_at
 * @property-read InvestorAgreement $investorAgreement
 */
class ApprovalRequest extends Model
{
    /** @use HasFactory<ApprovalRequestFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'team_id',
        'investor_agreement_id',
        'approval_rule_id',
        'farm_id',
        'production_cycle_id',
        'expense_category_id',
        'requested_by_id',
        'decided_by_id',
        'subject_type',
        'subject_id',
        'request_type',
        'trigger_type',
        'status',
        'threshold_amount_minor',
        'requested_amount_minor',
        'approved_amount_minor',
        'currency',
        'requester_comment',
        'decision_comment',
        'requested_at',
        'decided_at',
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
     * @return BelongsTo<ApprovalRule, $this>
     */
    public function approvalRule(): BelongsTo
    {
        return $this->belongsTo(ApprovalRule::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_id');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'request_type' => ApprovalRequestType::class,
            'trigger_type' => ApprovalTriggerType::class,
            'status' => ApprovalRequestStatus::class,
            'threshold_amount_minor' => 'integer',
            'requested_amount_minor' => 'integer',
            'approved_amount_minor' => 'integer',
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }
}
