<?php

namespace App\Ai\Tools;

use App\Actions\Finance\UpdateFundingRequest;
use App\Ai\Tools\Concerns\InteractsWithFarmwellContext;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class UpdateFundingRequestTool implements Approvable, Tool
{
    use InteractsWithApprovals;
    use InteractsWithFarmwellContext;

    public function description(): string
    {
        return 'Edit an existing funding request awaiting an investor decision. Find the funding phase in GetFinanceSummaryTool. Supply only fields the user wants changed.';
    }

    protected function needsApproval(Request $request): Approval
    {
        $id = $request->integer('funding_phase_id');

        return Approval::required("Save changes to funding phase #{$id}");
    }

    public function handle(Request $request): string
    {
        if (! $this->allows('manageFinance')) {
            return $this->encodeResult(['error' => 'Only finance managers can edit funding requests.']);
        }

        try {
            $data = $request->validate(UpdateFundingRequest::rules() + ['funding_phase_id' => ['required', 'integer', 'min:1']]);
            $id = (int) $data['funding_phase_id'];
            unset($data['funding_phase_id']);

            if ($data === []) {
                return $this->encodeResult(['error' => 'Provide at least one funding request detail to change.']);
            }

            app(UpdateFundingRequest::class)->handle($this->team, $this->user, $id, $data);
        } catch (ValidationException $exception) {
            return $this->encodeResult(['error' => $exception->getMessage(), 'fields' => $exception->errors()]);
        } catch (ModelNotFoundException) {
            return $this->encodeResult(['error' => 'Record not found in this workspace.']);
        }

        return $this->encodeResult(['updated' => true, 'funding_phase_id' => $id]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'funding_phase_id' => $schema->integer()->required(),
            'name' => $schema->string(),
            'requested_amount' => $schema->string()->description('Updated decimal amount in the record currency.'),
            'milestone' => $schema->string()->nullable(),
            'expected_on' => $schema->string()->nullable()->description('Target date in YYYY-MM-DD.'),
            'notes' => $schema->string()->nullable(),
        ];
    }
}
