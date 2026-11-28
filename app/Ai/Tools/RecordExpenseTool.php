<?php

namespace App\Ai\Tools;

use App\Actions\Finance\RecordExpense;
use App\Ai\Tools\Concerns\InteractsWithFarmwellContext;
use App\Enums\ExpenseStatus;
use App\Enums\InvestorVisibilityStatus;
use App\Models\ExpenseCategory;
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

class RecordExpenseTool implements Approvable, Tool
{
    use InteractsWithApprovals;
    use InteractsWithFarmwellContext;

    public function description(): string
    {
        return 'Record a farm expense under a farm and expense category (optionally linked to a production cycle and investor agreement).';
    }

    protected function needsApproval(Request $request): Approval
    {
        $amount = (string) ($request['amount'] ?? '0');
        $date = (string) ($request['incurred_on'] ?? now()->toDateString());
        $summary = trim((string) ($request['description'] ?? 'Farm expense'));

        return Approval::required("Record expense of {$amount} on {$date}: {$summary}");
    }

    public function handle(Request $request): string
    {
        if (! $this->allows('manageFinance')) {
            return $this->encodeResult(['error' => 'Not authorized to record expenses.']);
        }

        $amountMinor = $this->parsePositiveMinorAmount($request['amount'] ?? null);
        $incurredOn = $this->parseDateString($request['incurred_on'] ?? null);
        $description = trim((string) ($request['description'] ?? ''));

        if ($amountMinor === null || $incurredOn === null || $description === '') {
            return $this->encodeResult(['error' => 'Valid positive amount, YYYY-MM-DD incurred_on date, and description are required.']);
        }

        $farm = Farm::query()->where('team_id', $this->team->id)->find($request->integer('farm_id'));
        $category = ExpenseCategory::query()->where('team_id', $this->team->id)->find($request->integer('expense_category_id'));

        if ($farm === null || $category === null) {
            return $this->encodeResult(['error' => 'The selected farm or expense category was not found in this workspace.']);
        }

        $cycleId = $request->integer('production_cycle_id');
        if ($cycleId > 0 && ! ProductionCycle::query()->where('team_id', $this->team->id)->where('farm_id', $farm->id)->whereKey($cycleId)->exists()) {
            return $this->encodeResult(['error' => 'The production cycle does not belong to the selected farm.']);
        }

        $agreementId = $request->integer('investor_agreement_id');
        if ($agreementId > 0 && ! InvestorAgreement::query()->where('team_id', $this->team->id)->where('farm_id', $farm->id)->whereKey($agreementId)->exists()) {
            return $this->encodeResult(['error' => 'The investor agreement does not belong to the selected farm.']);
        }

        $visibility = InvestorVisibilityStatus::tryFrom((string) ($request['investor_visibility_status'] ?? InvestorVisibilityStatus::Private->value))
            ?? InvestorVisibilityStatus::Private;

        $expense = app(RecordExpense::class)->handle($this->team, $this->user, [
            'farm_id' => $farm->id,
            'production_cycle_id' => $cycleId > 0 ? $cycleId : null,
            'investor_agreement_id' => $agreementId > 0 ? $agreementId : null,
            'expense_category_id' => $category->id,
            'incurred_on' => $incurredOn,
            'vendor' => $request->filled('vendor') ? trim((string) $request['vendor']) : null,
            'description' => $description,
            'amount_minor' => $amountMinor,
            'status' => ExpenseStatus::Approved->value,
            'investor_visibility_status' => $visibility,
        ]);

        $this->recordAssistantAudit('expense_recorded', $expense, ['expense_id' => $expense->id]);

        return $this->encodeResult([
            'recorded' => true,
            'expense_id' => $expense->id,
            'farm' => $farm->name,
            'category' => $category->name,
            'amount' => Money::toDecimal($expense->amount_minor),
            'currency' => $expense->currency,
            'incurred_on' => $incurredOn,
            'status' => $expense->status->value,
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'farm_id' => $schema->integer()->required(),
            'expense_category_id' => $schema->integer()->required(),
            'amount' => $schema->string()->description('Decimal amount, e.g. 85000.00')->required(),
            'incurred_on' => $schema->string()->description('YYYY-MM-DD')->required(),
            'description' => $schema->string()->required(),
            'production_cycle_id' => $schema->integer(),
            'investor_agreement_id' => $schema->integer(),
            'vendor' => $schema->string(),
            'investor_visibility_status' => $schema->string()->enum(['private', 'approved']),
        ];
    }
}
