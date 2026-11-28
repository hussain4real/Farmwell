<?php

namespace App\Ai\Tools;

use App\Actions\Finance\ResolveTeamFinanceCurrency;
use App\Ai\Tools\Concerns\InteractsWithFarmwellContext;
use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FundingPhase;
use App\Support\Money;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetFinanceSummaryTool implements Tool
{
    use InteractsWithFarmwellContext;

    public function description(): string
    {
        return 'Get farm accounting balances (released, spent, remaining balance), expense categories, recent expenses and funding phases. Search for older records by ID or text.';
    }

    public function handle(Request $request): string
    {
        if (! $this->allows('viewFinance')) {
            return $this->encodeResult(['error' => 'Not authorized to view workspace finance.']);
        }

        $farmId = $request->integer('farm_id');
        $search = trim($request->string('record_search')->toString());
        $recordId = ctype_digit($search) ? (int) $search : null;
        $currency = app(ResolveTeamFinanceCurrency::class)->handle($this->team);

        $releasedMinor = (int) FundingPhase::query()
            ->where('team_id', $this->team->id)
            ->when($farmId > 0, fn ($query) => $query->where('farm_id', $farmId))
            ->where('currency', $currency)
            ->sum('externally_released_amount_minor');

        $spentMinor = (int) Expense::query()
            ->where('team_id', $this->team->id)
            ->when($farmId > 0, fn ($query) => $query->where('farm_id', $farmId))
            ->where('currency', $currency)
            ->whereIn('status', ExpenseStatus::spendableValues())
            ->sum('amount_minor');

        $categories = ExpenseCategory::query()
            ->where('team_id', $this->team->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $recentExpenses = Expense::query()
            ->where('team_id', $this->team->id)
            ->when($farmId > 0, fn ($query) => $query->where('farm_id', $farmId))
            ->matchingRecord($search)
            ->with(['expenseCategory:id,name', 'farm:id,name'])
            ->latest('incurred_on')
            ->latest('id')
            ->limit(10)
            ->get();

        $fundingPhases = FundingPhase::query()
            ->where('team_id', $this->team->id)
            ->when($farmId > 0, fn ($query) => $query->where('farm_id', $farmId))
            ->when($recordId !== null, fn ($query) => $query->whereKey($recordId))
            ->when($search !== '' && $recordId === null, fn ($query) => $query->whereLike('name', '%'.$search.'%'))
            ->with('farm:id,name')
            ->latest('id')
            ->limit(10)
            ->get();

        return $this->encodeResult([
            'currency' => $currency,
            'totals' => [
                'released' => Money::toDecimal($releasedMinor),
                'spent' => Money::toDecimal($spentMinor),
                'balance' => Money::toDecimal($releasedMinor - $spentMinor),
            ],
            'expense_categories' => $categories->map(fn (ExpenseCategory $category): array => [
                'id' => $category->id,
                'name' => $category->name,
            ])->all(),
            'recent_expenses' => $recentExpenses->map(fn (Expense $expense): array => [
                'id' => $expense->id,
                'farm_name' => $expense->farm?->name,
                'category' => $expense->expenseCategory?->name,
                'description' => $expense->description,
                'amount' => Money::toDecimal($expense->amount_minor),
                'currency' => $expense->currency,
                'incurred_on' => $expense->incurred_on->toDateString(),
                'status' => $expense->status->value,
            ])->all(),
            'funding_phases' => $fundingPhases->map(fn (FundingPhase $phase): array => [
                'id' => $phase->id,
                'farm_id' => $phase->farm_id,
                'farm_name' => $phase->farm?->name,
                'investor_agreement_id' => $phase->investor_agreement_id,
                'name' => $phase->name,
                'status' => $phase->status->value,
                'requested_amount' => Money::toDecimal($phase->requested_amount_minor),
                'approved_amount' => Money::toDecimal($phase->approved_amount_minor),
                'released_amount' => Money::toDecimal($phase->externally_released_amount_minor),
                'currency' => $phase->currency,
            ])->all(),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'record_search' => $schema->string()->description('Optional record ID or phrase from the expense description or funding phase name.'),
            'farm_id' => $schema->integer()->description('Optional farm ID to filter finance records.'),
        ];
    }
}
