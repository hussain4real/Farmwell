<?php

namespace App\Ai\Tools;

use App\Actions\Investors\DecideApprovalRequest;
use App\Ai\Tools\Concerns\InteractsWithFarmwellContext;
use App\Enums\ApprovalRequestStatus;
use App\Enums\TeamRole;
use App\Models\ApprovalRequest;
use App\Support\Money;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class DecideApprovalRequestTool implements Approvable, Tool
{
    use InteractsWithApprovals;
    use InteractsWithFarmwellContext;

    public function description(): string
    {
        return 'Submit an investor decision (approved, rejected, or clarification_requested) on one of your pending approval requests.';
    }

    protected function needsApproval(Request $request): Approval
    {
        $requestId = $request->integer('approval_request_id');
        $decision = (string) ($request['decision'] ?? 'approved');

        return Approval::required("Submit '{$decision}' decision for approval request #{$requestId}");
    }

    public function handle(Request $request): string
    {
        if (! $this->allows('viewInvestorPortal') || $this->user->teamRole($this->team) !== TeamRole::Investor) {
            return $this->encodeResult(['error' => 'Only the assigned investor can decide investor approval requests.']);
        }

        $decision = ApprovalRequestStatus::tryFrom((string) ($request['decision'] ?? ''));
        if (! in_array($decision, [
            ApprovalRequestStatus::Approved,
            ApprovalRequestStatus::Rejected,
            ApprovalRequestStatus::ClarificationRequested,
        ], true)) {
            return $this->encodeResult(['error' => 'Decision must be approved, rejected, or clarification_requested.']);
        }

        $approvalRequest = ApprovalRequest::query()
            ->where('team_id', $this->team->id)
            ->with('investorAgreement')
            ->find($request->integer('approval_request_id'));

        if ($approvalRequest === null || $approvalRequest->investorAgreement->investor_id !== $this->user->id) {
            return $this->encodeResult(['error' => 'Approval request not found for your investor agreements.']);
        }

        $approvedMinor = null;
        if ($request->filled('approved_amount')) {
            $approvedMinor = $this->parsePositiveMinorAmount($request['approved_amount']);
            if ($approvedMinor === null) {
                return $this->encodeResult(['error' => 'approved_amount must be a positive decimal amount.']);
            }
        }

        try {
            $decided = app(DecideApprovalRequest::class)->handle(
                team: $this->team,
                approvalRequest: $approvalRequest,
                actor: $this->user,
                status: $decision,
                approvedAmountMinor: $approvedMinor,
                comment: $request->filled('comment') ? trim((string) $request['comment']) : null,
                reviewToken: $request->filled('review_token') ? (string) $request['review_token'] : null,
            );
        } catch (ValidationException $exception) {
            return $this->encodeResult(['error' => $exception->getMessage()]);
        }

        $this->recordAssistantAudit('approval_decided', $decided, ['approval_request_id' => $decided->id]);

        return $this->encodeResult([
            'decided' => true,
            'approval_request_id' => $decided->id,
            'status' => $decided->status->value,
            'approved_amount' => $decided->approved_amount_minor !== null ? Money::toDecimal($decided->approved_amount_minor) : null,
            'currency' => $decided->currency,
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'approval_request_id' => $schema->integer()->required(),
            'review_token' => $schema->string()->description('Copy from ListPendingApprovalsTool when deciding an approval request.'),
            'decision' => $schema->string()->enum(['approved', 'rejected', 'clarification_requested'])->required(),
            'approved_amount' => $schema->string()->description('Optional partial or full approved decimal amount'),
            'comment' => $schema->string(),
        ];
    }
}
