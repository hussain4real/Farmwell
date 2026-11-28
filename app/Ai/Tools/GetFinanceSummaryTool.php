<?php

namespace App\Ai\Tools;

use App\Actions\Finance\ResolveTeamFinanceCurrency;
use App\Ai\Tools\Concerns\InteractsWithFarmwellContext;
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
        return 'Get farm accounting balances (released, spent, remaining balance), expense categories, recent expenses, and funding phases.';
    }

    public function handle(Request $request): string
    {
        if (! $this->allows('viewFinance')) {
            return $this->encodeResult(['error' => 'Not authorized to view workspace finance.']);
        }

        $farmId = $request->integer('farm_id');
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
            ->sum('amount_minor');

        $categories = ExpenseCategory::query()
            ->where('team_id', $this->team->id)
            ->orderBy('name')
            ->get(['id', 'name']);

        $recentExpenses = Expense::query()
            ->where('team_id', $this->team->id)
            ->when($farmId > 0, fn ($query) => $query->where('farm_id', $farmId))
            ->with(['expenseCategory:id,name', 'farm:id,name'])
            ->latest('incurred_on')
            ->latest('id')
            ->limit(10)
            ->get();

        $fundingPhases = FundingPhase::query()
            ->where('team_id', $this->team->id)
            ->when($farmId > 0, fn ($query) => $query->where('farm_id', $farmId))
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
            'farm_id' => $schema->integer()->description('Optional farm ID to filter finance records.'),
        ];
    }
}
