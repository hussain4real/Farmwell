<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\InteractsWithFarmwellContext;
use App\Enums\ApprovalRequestStatus;
use App\Enums\TeamRole;
use App\Models\ApprovalRequest;
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
        return 'List open investor approval requests (pending or clarification requested) with IDs, requested amounts, and comments.';
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
            ->with('investorAgreement:id,title')
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
                'requested_at' => $item->requested_at->toDateString(),
            ])->all(),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'agreement_id' => $schema->integer()->description('Optional agreement ID filter.'),
        ];
    }
}
