<?php

namespace App\Ai\Tools;

use App\Actions\Finance\RecordFundingRelease;
use App\Ai\Tools\Concerns\InteractsWithFarmwellContext;
use App\Models\FundingPhase;
use App\Support\Money;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class RecordFundingReleaseTool implements Approvable, Tool
{
    use InteractsWithApprovals;
    use InteractsWithFarmwellContext;

    public function description(): string
    {
        return 'Record an external release against an investor-approved funding phase, which also updates the agreement recorded funding.';
    }

    protected function needsApproval(Request $request): Approval
    {
        $phaseId = $request->integer('funding_phase_id');
        $amount = (string) ($request['amount'] ?? '0');
        $releasedOn = (string) ($request['released_on'] ?? now()->toDateString());

        return Approval::required("Record release of {$amount} on {$releasedOn} for funding phase #{$phaseId}");
    }

    public function handle(Request $request): string
    {
        if (! $this->allows('manageFinance')) {
            return $this->encodeResult(['error' => 'Not authorized to record funding releases.']);
        }

        $amountMinor = $this->parsePositiveMinorAmount($request['amount'] ?? null);
        $releasedOn = $this->parseDateString($request['released_on'] ?? null);

        if ($amountMinor === null || $releasedOn === null) {
            return $this->encodeResult(['error' => 'Positive decimal amount and YYYY-MM-DD released_on date are required.']);
        }

        $phase = FundingPhase::query()
            ->where('team_id', $this->team->id)
            ->find($request->integer('funding_phase_id'));

        if ($phase === null) {
            return $this->encodeResult(['error' => 'Funding phase not found in this workspace.']);
        }

        try {
            app(RecordFundingRelease::class)->handle($this->team, $this->user, $phase, $amountMinor, $releasedOn);
        } catch (ValidationException $exception) {
            return $this->encodeResult(['error' => $exception->getMessage()]);
        }

        $phase->refresh();
        $this->recordAssistantAudit('funding_phase_released', $phase, ['funding_phase_id' => $phase->id]);

        return $this->encodeResult([
            'released' => true,
            'funding_phase_id' => $phase->id,
            'released_amount' => Money::toDecimal($phase->externally_released_amount_minor),
            'currency' => $phase->currency,
            'released_on' => $releasedOn,
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'funding_phase_id' => $schema->integer()->required(),
            'amount' => $schema->string()->description('Decimal released amount, e.g. 150000.00')->required(),
            'released_on' => $schema->string()->description('YYYY-MM-DD')->required(),
        ];
    }
}
