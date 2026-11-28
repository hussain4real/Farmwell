<?php

namespace App\Ai\Tools;

use App\Actions\Investors\UpdateInvestorAgreement;
use App\Ai\Tools\Concerns\InteractsWithFarmwellContext;
use App\Models\InvestorAgreement;
use App\Support\Money;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class UpdateAgreementFundingTool implements Approvable, Tool
{
    use InteractsWithApprovals;
    use InteractsWithFarmwellContext;

    public function description(): string
    {
        return 'Update the recorded funded amount on an existing investor agreement.';
    }

    protected function needsApproval(Request $request): Approval
    {
        $agreementId = $request->integer('investor_agreement_id');
        $amount = (string) ($request['amount_funded'] ?? '0');

        return Approval::required("Set recorded funding to {$amount} on investor agreement #{$agreementId}");
    }

    public function handle(Request $request): string
    {
        if (! $this->allows('manageInvestorAgreements')) {
            return $this->encodeResult(['error' => 'Not authorized to update investor agreement funding.']);
        }

        $fundedMinor = $this->parseNonNegativeMinorAmount($request['amount_funded'] ?? null);
        if ($fundedMinor === null) {
            return $this->encodeResult(['error' => 'A valid non-negative decimal amount_funded is required.']);
        }

        $agreement = InvestorAgreement::query()
            ->where('team_id', $this->team->id)
            ->find($request->integer('investor_agreement_id'));

        if ($agreement === null) {
            return $this->encodeResult(['error' => 'Investor agreement not found in this workspace.']);
        }

        $updated = app(UpdateInvestorAgreement::class)->handle($this->team, $agreement, $this->user, [
            'title' => $agreement->title,
            'status' => $agreement->status->value,
            'production_cycle_id' => $agreement->production_cycle_id,
            'currency' => $agreement->currency,
            'amount_committed_minor' => $agreement->amount_committed_minor,
            'amount_funded_minor' => $fundedMinor,
            'capital_recovery_rule' => $agreement->capital_recovery_rule->value,
            'investor_profit_share_percentage' => $agreement->investor_profit_share_percentage,
            'farm_profit_share_percentage' => $agreement->farm_profit_share_percentage,
            'starts_on' => $agreement->starts_on?->toDateString(),
            'ends_on' => $agreement->ends_on?->toDateString(),
        ]);

        $this->recordAssistantAudit('agreement_funding_updated', $updated, ['investor_agreement_id' => $updated->id]);

        return $this->encodeResult([
            'updated' => true,
            'investor_agreement_id' => $updated->id,
            'title' => $updated->title,
            'amount_funded' => Money::toDecimal($updated->amount_funded_minor),
            'currency' => $updated->currency,
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'investor_agreement_id' => $schema->integer()->required(),
            'amount_funded' => $schema->string()->description('New total recorded funding decimal amount, e.g. 400000.00')->required(),
        ];
    }
}
