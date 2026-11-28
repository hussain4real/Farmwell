<?php

namespace App\Ai\Tools;

use App\Actions\Finance\CreateFundingPhase;
use App\Ai\Tools\Concerns\InteractsWithFarmwellContext;
use App\Enums\FundingPhaseStatus;
use App\Enums\InvestorVisibilityStatus;
use App\Models\Farm;
use App\Models\InvestorAgreement;
use App\Models\ProductionCycle;
use App\Support\Money;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class CreateFundingPhaseTool implements Approvable, Tool
{
    use InteractsWithApprovals;
    use InteractsWithFarmwellContext;

    public function description(): string
    {
        return 'Create a funding phase linked to an investor agreement and submit it for investor approval.';
    }

    protected function needsApproval(Request $request): Approval
    {
        $name = trim((string) ($request['name'] ?? 'Funding request'));
        $amount = (string) ($request['requested_amount'] ?? '0');

        return Approval::required("Request funding phase '{$name}' for {$amount}");
    }

    public function handle(Request $request): string
    {
        if (! $this->allows('manageFinance')) {
            return $this->encodeResult(['error' => 'Not authorized to create funding phases.']);
        }

        $requestedMinor = $this->parsePositiveMinorAmount($request['requested_amount'] ?? null);
        $name = trim((string) ($request['name'] ?? ''));

        if ($requestedMinor === null || $name === '') {
            return $this->encodeResult(['error' => 'Phase name and positive requested_amount are required.']);
        }

        $farm = Farm::query()->where('team_id', $this->team->id)->find($request->integer('farm_id'));
        $agreement = InvestorAgreement::query()
            ->where('team_id', $this->team->id)
            ->find($request->integer('investor_agreement_id'));

        if ($farm === null || $agreement === null || $agreement->farm_id !== $farm->id) {
            return $this->encodeResult(['error' => 'The farm and investor agreement must belong together in this workspace.']);
        }

        $cycleId = $request->integer('production_cycle_id');
        if ($cycleId > 0 && ! ProductionCycle::query()->where('team_id', $this->team->id)->where('farm_id', $farm->id)->whereKey($cycleId)->exists()) {
            return $this->encodeResult(['error' => 'The production cycle does not belong to the selected farm.']);
        }

        $expectedOn = $request->filled('expected_on') ? $this->parseDateString($request['expected_on']) : null;
        if ($request->filled('expected_on') && $expectedOn === null) {
            return $this->encodeResult(['error' => 'expected_on must use YYYY-MM-DD format.']);
        }

        $phase = app(CreateFundingPhase::class)->handle($this->team, $this->user, [
            'farm_id' => $farm->id,
            'production_cycle_id' => $cycleId > 0 ? $cycleId : null,
            'investor_agreement_id' => $agreement->id,
            'name' => $name,
            'milestone' => $request->filled('milestone') ? trim((string) $request['milestone']) : null,
            'status' => FundingPhaseStatus::Requested->value,
            'investor_visibility_status' => InvestorVisibilityStatus::Approved->value,
            'planned_amount_minor' => $requestedMinor,
            'requested_amount_minor' => $requestedMinor,
            'approved_amount_minor' => 0,
            'externally_released_amount_minor' => 0,
            'expected_on' => $expectedOn,
            'notes' => $request->filled('notes') ? trim((string) $request['notes']) : null,
        ]);

        $this->recordAssistantAudit('funding_phase_requested', $phase, ['funding_phase_id' => $phase->id]);

        return $this->encodeResult([
            'created' => true,
            'funding_phase_id' => $phase->id,
            'name' => $phase->name,
            'status' => $phase->status->value,
            'requested_amount' => Money::toDecimal($phase->requested_amount_minor),
            'currency' => $phase->currency,
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'farm_id' => $schema->integer()->required(),
            'investor_agreement_id' => $schema->integer()->required(),
            'name' => $schema->string()->required(),
            'requested_amount' => $schema->string()->description('Decimal amount requested, e.g. 200000.00')->required(),
            'production_cycle_id' => $schema->integer(),
            'milestone' => $schema->string(),
            'expected_on' => $schema->string()->description('Optional YYYY-MM-DD target date'),
            'notes' => $schema->string(),
        ];
    }
}
