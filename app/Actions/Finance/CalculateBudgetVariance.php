<?php

namespace App\Actions\Finance;

use App\Enums\ExpenseStatus;
use App\Models\BudgetLine;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\ProductionCycle;
use App\Models\Team;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;

class CalculateBudgetVariance
{
    /**
     * @return array<string, mixed>
     */
    public function handle(Team $team, ?Farm $farm = null, ?ProductionCycle $cycle = null): array
    {
        $budgetedMinor = $this->budgetLineQuery($team, $farm, $cycle)->sum('planned_amount_minor');
        $spentMinor = $this->expenseQuery($team, $farm, $cycle)->sum('amount_minor');
        $varianceMinor = $budgetedMinor - $spentMinor;

        return [
            'budgetedMinor' => (int) $budgetedMinor,
            'spentMinor' => (int) $spentMinor,
            'balanceMinor' => max(0, (int) $varianceMinor),
            'varianceMinor' => (int) $varianceMinor,
            'budgeted' => Money::toDecimal((int) $budgetedMinor),
            'spent' => Money::toDecimal((int) $spentMinor),
            'balance' => Money::toDecimal(max(0, (int) $varianceMinor)),
            'variance' => Money::toDecimal((int) $varianceMinor),
            'byCategory' => $this->byCategory($team, $farm, $cycle),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function byCategory(Team $team, ?Farm $farm, ?ProductionCycle $cycle): array
    {
        return ExpenseCategory::query()
            ->where('team_id', $team->id)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (ExpenseCategory $category) use ($team, $farm, $cycle): array {
                $budgetedMinor = $this->budgetLineQuery($team, $farm, $cycle)
                    ->where('expense_category_id', $category->id)
                    ->sum('planned_amount_minor');
                $spentMinor = $this->expenseQuery($team, $farm, $cycle)
                    ->where('expense_category_id', $category->id)
                    ->sum('amount_minor');
                $varianceMinor = (int) $budgetedMinor - (int) $spentMinor;

                return [
                    'categoryId' => $category->id,
                    'categoryName' => $category->name,
                    'budgetedMinor' => (int) $budgetedMinor,
                    'spentMinor' => (int) $spentMinor,
                    'balanceMinor' => max(0, $varianceMinor),
                    'varianceMinor' => $varianceMinor,
                    'budgeted' => Money::toDecimal((int) $budgetedMinor),
                    'spent' => Money::toDecimal((int) $spentMinor),
                    'balance' => Money::toDecimal(max(0, $varianceMinor)),
                    'variance' => Money::toDecimal($varianceMinor),
                ];
            })
            ->values()
            ->toArray();
    }

    private function budgetLineQuery(Team $team, ?Farm $farm, ?ProductionCycle $cycle): Builder
    {
        return BudgetLine::query()
            ->where('team_id', $team->id)
            ->when($farm, fn ($query) => $query->where('farm_id', $farm->id))
            ->when($cycle, fn ($query) => $query->where('production_cycle_id', $cycle->id));
    }

    private function expenseQuery(Team $team, ?Farm $farm, ?ProductionCycle $cycle): Builder
    {
        return Expense::query()
            ->where('team_id', $team->id)
            ->whereIn('status', ExpenseStatus::spendableValues())
            ->when($farm, fn ($query) => $query->where('farm_id', $farm->id))
            ->when($cycle, fn ($query) => $query->where('production_cycle_id', $cycle->id));
    }
}
