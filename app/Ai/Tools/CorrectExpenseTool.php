<?php

namespace App\Ai\Tools;

use App\Actions\Finance\CorrectExpense;
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

class CorrectExpenseTool implements Approvable, Tool
{
    use InteractsWithApprovals;
    use InteractsWithFarmwellContext;

    public function description(): string
    {
        return 'Correct details on a previously recorded expense in place. Find the expense in GetFinanceSummaryTool and supply a reason and only the corrected fields.';
    }

    protected function needsApproval(Request $request): Approval
    {
        $id = $request->integer('expense_id');

        return Approval::required("Save correction to expense #{$id}");
    }

    public function handle(Request $request): string
    {
        if (! $this->allows('manageFinance')) {
            return $this->encodeResult(['error' => 'Only finance managers can correct expenses.']);
        }

        try {
            $data = $request->validate(CorrectExpense::rules() + ['expense_id' => ['required', 'integer', 'min:1']]);
            $id = (int) $data['expense_id'];
            unset($data['expense_id']);

            if (count($data) === 1) {
                return $this->encodeResult(['error' => 'Provide at least one expense detail to correct.']);
            }

            app(CorrectExpense::class)->handle($this->team, $this->user, $id, $data);
        } catch (ValidationException $exception) {
            return $this->encodeResult(['error' => $exception->getMessage(), 'fields' => $exception->errors()]);
        } catch (ModelNotFoundException) {
            return $this->encodeResult(['error' => 'Record not found in this workspace.']);
        }

        return $this->encodeResult(['corrected' => true, 'expense_id' => $id]);
    }

    /** @return array<string, Type> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'expense_id' => $schema->integer()->required(),
            'amount' => $schema->string()->description('Corrected decimal amount in the record currency.'),
            'description' => $schema->string(),
            'incurred_on' => $schema->string()->description('Expense date in YYYY-MM-DD.'),
            'vendor' => $schema->string()->nullable(),
            'payment_method' => $schema->string()->nullable(),
            'notes' => $schema->string()->nullable(),
            'reason' => $schema->string()->required(),
        ];
    }
}
