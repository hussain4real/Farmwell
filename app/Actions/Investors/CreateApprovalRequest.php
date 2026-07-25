<?php

namespace App\Actions\Investors;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalRequestType;
use App\Enums\ApprovalTriggerType;
use App\Enums\InvestorVisibilityStatus;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRule;
use App\Models\ExpenseCategory;
use App\Models\InvestorAgreement;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateApprovalRequest
{
    public function __construct(private RecordAuditEvent $recordAuditEvent)
    {
        //
    }

    public function handle(
        Team $team,
        InvestorAgreement $agreement,
        ?User $actor,
        Model $subject,
        ApprovalRequestType $requestType,
        ApprovalTriggerType $triggerType,
        int $requestedAmountMinor,
        string $currency,
        ?ApprovalRule $rule = null,
        ?ExpenseCategory $category = null,
        ?int $thresholdAmountMinor = null,
        ?string $comment = null,
    ): ApprovalRequest {
        return DB::transaction(function () use ($team, $agreement, $actor, $subject, $requestType, $triggerType, $requestedAmountMinor, $currency, $rule, $category, $thresholdAmountMinor, $comment): ApprovalRequest {
            $approvalRequest = ApprovalRequest::query()
                ->where('team_id', $team->id)
                ->where('investor_agreement_id', $agreement->id)
                ->where('subject_type', $subject->getMorphClass())
                ->where('subject_id', $subject->getKey())
                ->where('request_type', $requestType->value)
                ->where('status', ApprovalRequestStatus::Pending->value)
                ->first();

            if (! $approvalRequest instanceof ApprovalRequest) {
                $approvalRequest = ApprovalRequest::create([
                    'team_id' => $team->id,
                    'investor_agreement_id' => $agreement->id,
                    'approval_rule_id' => $rule?->id,
                    'farm_id' => $subject->getAttribute('farm_id') ?: $agreement->farm_id,
                    'production_cycle_id' => $subject->getAttribute('production_cycle_id') ?: $agreement->production_cycle_id,
                    'expense_category_id' => $category?->id,
                    'requested_by_id' => $actor?->id,
                    'subject_type' => $subject->getMorphClass(),
                    'subject_id' => $subject->getKey(),
                    'request_type' => $requestType,
                    'trigger_type' => $triggerType,
                    'status' => ApprovalRequestStatus::Pending,
                    'threshold_amount_minor' => $thresholdAmountMinor,
                    'requested_amount_minor' => $requestedAmountMinor,
                    'currency' => $currency,
                    'requester_comment' => $comment,
                    'requested_at' => now(),
                ]);

                $this->recordAuditEvent->handle(
                    team: $team,
                    actor: $actor,
                    action: 'approval_request.created',
                    subject: $approvalRequest,
                    newValues: [
                        'investor_agreement_id' => $agreement->id,
                        'request_type' => $requestType->value,
                        'trigger_type' => $triggerType->value,
                        'requested_amount_minor' => $requestedAmountMinor,
                        'currency' => $currency,
                    ],
                );
            }

            if ($subject->isFillable('investor_visibility_status')) {
                $subject->forceFill([
                    'investor_visibility_status' => InvestorVisibilityStatus::PendingApproval,
                ])->save();
            }

            return $approvalRequest->refresh();
        });
    }
}
