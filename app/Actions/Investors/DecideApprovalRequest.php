<?php

namespace App\Actions\Investors;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\ApprovalRequestStatus;
use App\Enums\DistributionStatus;
use App\Enums\InvestorVisibilityStatus;
use App\Models\ApprovalRequest;
use App\Models\DistributionRecord;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DecideApprovalRequest
{
    public function __construct(private RecordAuditEvent $recordAuditEvent)
    {
        //
    }

    public function handle(Team $team, ApprovalRequest $approvalRequest, User $actor, ApprovalRequestStatus $status, ?int $approvedAmountMinor = null, ?string $comment = null): ApprovalRequest
    {
        return DB::transaction(function () use ($team, $approvalRequest, $actor, $status, $approvedAmountMinor, $comment): ApprovalRequest {
            $approvalRequest = ApprovalRequest::query()
                ->where('team_id', $team->id)
                ->lockForUpdate()
                ->findOrFail($approvalRequest->id);

            $this->validateStatusTransition($approvalRequest->status, $status);

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

            if ($subject instanceof DistributionRecord) {
                $subject->forceFill([
                    'status' => $subject->status === DistributionStatus::LossRecorded
                        ? DistributionStatus::LossRecorded
                        : ($status === ApprovalRequestStatus::Approved
                            ? DistributionStatus::Acknowledged
                            : DistributionStatus::PendingAcknowledgement),
                    'acknowledged_at' => $status === ApprovalRequestStatus::Approved ? now() : null,
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

    private function validateStatusTransition(ApprovalRequestStatus $currentStatus, ApprovalRequestStatus $newStatus): void
    {
        $isAllowed = match ($currentStatus) {
            ApprovalRequestStatus::Pending => in_array($newStatus, [
                ApprovalRequestStatus::Approved,
                ApprovalRequestStatus::Rejected,
                ApprovalRequestStatus::ClarificationRequested,
            ], true),
            ApprovalRequestStatus::ClarificationRequested => in_array($newStatus, [
                ApprovalRequestStatus::Approved,
                ApprovalRequestStatus::Rejected,
            ], true),
            ApprovalRequestStatus::Approved,
            ApprovalRequestStatus::Rejected,
            ApprovalRequestStatus::Cancelled => false,
        };

        if (! $isAllowed) {
            throw ValidationException::withMessages([
                'status' => __('This approval request has already been decided.'),
            ]);
        }
    }
}
