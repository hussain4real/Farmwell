<?php

namespace App\Actions\Investors;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\ApprovalRequestStatus;
use App\Enums\InvestorVisibilityStatus;
use App\Models\ApprovalRequest;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class DecideApprovalRequest
{
    public function __construct(private RecordAuditEvent $recordAuditEvent)
    {
        //
    }

    public function handle(Team $team, ApprovalRequest $approvalRequest, User $actor, ApprovalRequestStatus $status, ?int $approvedAmountMinor = null, ?string $comment = null): ApprovalRequest
    {
        return DB::transaction(function () use ($team, $approvalRequest, $actor, $status, $approvedAmountMinor, $comment): ApprovalRequest {
            $oldValues = [
                'status' => $approvalRequest->status->value,
                'approved_amount_minor' => $approvalRequest->approved_amount_minor,
            ];

            $approvalRequest->fill([
                'status' => $status,
                'decided_by_id' => $actor->id,
                'approved_amount_minor' => $status === ApprovalRequestStatus::Approved
                    ? ($approvedAmountMinor ?? $approvalRequest->requested_amount_minor)
                    : null,
                'decision_comment' => $comment,
                'decided_at' => now(),
            ])->save();

            $subject = $approvalRequest->subject;
            if ($subject instanceof Model && $subject->isFillable('investor_visibility_status')) {
                $subject->forceFill([
                    'investor_visibility_status' => match ($status) {
                        ApprovalRequestStatus::Approved => InvestorVisibilityStatus::Approved,
                        ApprovalRequestStatus::Rejected => InvestorVisibilityStatus::Rejected,
                        ApprovalRequestStatus::ClarificationRequested => InvestorVisibilityStatus::ClarificationRequested,
                        default => InvestorVisibilityStatus::PendingApproval,
                    },
                ])->save();
            }

            $this->recordAuditEvent->handle(
                team: $team,
                actor: $actor,
                action: 'approval_request.decided',
                subject: $approvalRequest,
                oldValues: $oldValues,
                newValues: [
                    'status' => $approvalRequest->status->value,
                    'approved_amount_minor' => $approvalRequest->approved_amount_minor,
                    'decision_comment' => $comment,
                ],
            );

            return $approvalRequest->refresh();
        });
    }
}
