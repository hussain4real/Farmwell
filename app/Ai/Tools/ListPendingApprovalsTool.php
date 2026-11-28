<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\InteractsWithFarmwellContext;
use App\Enums\ApprovalRequestStatus;
use App\Enums\TeamRole;
use App\Models\ApprovalRequest;
use App\Models\Expense;
use App\Models\FundingPhase;
use App\Support\Money;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class ListPendingApprovalsTool implements Tool
{
    use InteractsWithFarmwellContext;

    public function description(): string
    {
        return 'List open investor approval requests (pending or clarification requested) with IDs, requested amounts, record details, and investor clarification notes.';
    }

    public function handle(Request $request): string
    {
        $canViewWorkspace = $this->allows('viewApprovalRequests');
        $canViewPortal = $this->allows('viewInvestorPortal');

        if (! $canViewWorkspace && ! $canViewPortal) {
            return $this->encodeResult(['error' => 'Not authorized to view approval requests.']);
        }

        $investorOnly = ! $canViewWorkspace || $this->user->teamRole($this->team) === TeamRole::Investor;

        $requests = ApprovalRequest::query()
            ->where('team_id', $this->team->id)
            ->whereIn('status', [
                ApprovalRequestStatus::Pending->value,
                ApprovalRequestStatus::ClarificationRequested->value,
            ])
            ->when(
                $investorOnly,
                fn ($query) => $query->whereHas('investorAgreement', fn ($agreement) => $agreement->where('investor_id', $this->user->id)),
            )
            ->when($request->integer('agreement_id') > 0, fn ($query) => $query->where('investor_agreement_id', $request->integer('agreement_id')))
            ->when($request->integer('approval_request_id') > 0, fn ($query) => $query->whereKey($request->integer('approval_request_id')))
            ->with(['investorAgreement:id,title', 'subject'])
            ->latest('id')
            ->limit(15)
            ->get();

        return $this->encodeResult([
            'approval_requests' => $requests->map(fn (ApprovalRequest $item): array => [
                'id' => $item->id,
                'agreement_id' => $item->investor_agreement_id,
                'agreement_title' => $item->investorAgreement?->title,
                'request_type' => $item->request_type->value,
                'status' => $item->status->value,
                'requested_amount' => Money::toDecimal($item->requested_amount_minor),
                'currency' => $item->currency,
                'requester_comment' => $item->requester_comment,
                'decision_comment' => $item->decision_comment,
                'review_token' => $item->reviewToken(),
                'subject_name' => $this->subjectName($item),
                'requested_at' => $item->requested_at->toDateString(),
            ])->all(),
        ]);
    }

    private function subjectName(ApprovalRequest $request): ?string
    {
        $subject = $request->subject;
        if ((! $subject instanceof FundingPhase && ! $subject instanceof Expense)
            || $subject->team_id !== $request->team_id || $subject->investor_agreement_id !== $request->investor_agreement_id) {
            return null;
        }

        return $subject instanceof FundingPhase ? $subject->name : $subject->description;
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'approval_request_id' => $schema->integer()->description('Optional approval request ID.'),
            'agreement_id' => $schema->integer()->description('Optional agreement ID filter.'),
        ];
    }
}
