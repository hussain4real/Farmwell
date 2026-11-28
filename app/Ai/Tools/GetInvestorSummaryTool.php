<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\InteractsWithFarmwellContext;
use App\Enums\ApprovalRequestStatus;
use App\Enums\TeamRole;
use App\Models\DistributionRecord;
use App\Models\Expense;
use App\Models\FundingPhase;
use App\Models\InvestorAgreement;
use App\Support\Money;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetInvestorSummaryTool implements Tool
{
    use InteractsWithFarmwellContext;

    public function description(): string
    {
        return 'Get investor agreements in this workspace with committed capital, recorded funding, externally released funds, approved spend, balance, and capital recovery.';
    }

    public function handle(Request $request): string
    {
        $canManageAll = $this->allows('viewInvestorAgreements');
        $isInvestor = $this->allows('viewInvestorPortal');

        if (! $canManageAll && ! $isInvestor) {
            return $this->encodeResult(['error' => 'Not authorized to view investor agreements.']);
        }

        $investorOnly = ! $canManageAll || $this->user->teamRole($this->team) === TeamRole::Investor;

        $agreements = InvestorAgreement::query()
            ->where('team_id', $this->team->id)
            ->when($investorOnly, fn ($query) => $query->where('investor_id', $this->user->id))
            ->with(['farm:id,name', 'investor:id,name'])
            ->orderBy('id')
            ->get();

        return $this->encodeResult([
            'agreements' => $agreements->map(function (InvestorAgreement $agreement): array {
                $releasedMinor = (int) FundingPhase::query()
                    ->where('team_id', $this->team->id)
                    ->where('investor_agreement_id', $agreement->id)
                    ->where('currency', $agreement->currency)
                    ->sum('externally_released_amount_minor');

                $spentMinor = (int) Expense::query()
                    ->where('team_id', $this->team->id)
                    ->where('investor_agreement_id', $agreement->id)
                    ->where('currency', $agreement->currency)
                    ->where('investor_visibility_status', 'approved')
                    ->sum('amount_minor');

                $recoveredMinor = (int) DistributionRecord::query()
                    ->where('team_id', $this->team->id)
                    ->where('investor_agreement_id', $agreement->id)
                    ->sum('capital_recovered_minor');

                $pendingDecisions = $agreement->approvalRequests()
                    ->where('status', ApprovalRequestStatus::Pending->value)
                    ->count();

                return [
                    'id' => $agreement->id,
                    'title' => $agreement->title,
                    'farm_id' => $agreement->farm_id,
                    'farm_name' => $agreement->farm?->name,
                    'investor_name' => $agreement->investor?->name,
                    'status' => $agreement->status->value,
                    'currency' => $agreement->currency,
                    'amount_committed' => Money::toDecimal($agreement->amount_committed_minor),
                    'amount_funded' => Money::toDecimal($agreement->amount_funded_minor),
                    'released' => Money::toDecimal($releasedMinor),
                    'approved_spent' => Money::toDecimal($spentMinor),
                    'balance' => Money::toDecimal($releasedMinor - $spentMinor),
                    'capital_recovered' => Money::toDecimal($recoveredMinor),
                    'investor_profit_share_percentage' => $agreement->investor_profit_share_percentage,
                    'farm_profit_share_percentage' => $agreement->farm_profit_share_percentage,
                    'pending_decisions' => $pendingDecisions,
                ];
            })->all(),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'agreement_id' => $schema->integer()->description('Optional agreement ID to focus the summary.'),
        ];
    }
}
