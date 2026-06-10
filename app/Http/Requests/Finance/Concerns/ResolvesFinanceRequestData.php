<?php

namespace App\Http\Requests\Finance\Concerns;

use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\FundingPhase;
use App\Models\ProductionCycle;
use App\Models\Team;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

trait ResolvesFinanceRequestData
{
    public function team(): Team
    {
        $team = $this->route('current_team');

        assert($team instanceof Team);

        return $team;
    }

    protected function authorizeFinanceManagement(): bool
    {
        $team = $this->route('current_team');

        return $team instanceof Team && $this->user()?->can('manageFinance', $team) === true;
    }

    protected function farmRule(): Exists
    {
        return Rule::exists((new Farm)->getTable(), 'id')->where('team_id', $this->team()->id);
    }

    protected function cycleRule(?int $farmId = null): Exists
    {
        $rule = Rule::exists((new ProductionCycle)->getTable(), 'id')
            ->where('team_id', $this->team()->id);

        if ($farmId !== null && $farmId > 0) {
            $rule->where('farm_id', $farmId);
        }

        return $rule;
    }

    protected function categoryRule(): Exists
    {
        return Rule::exists((new ExpenseCategory)->getTable(), 'id')->where('team_id', $this->team()->id);
    }

    protected function budgetRule(?int $farmId = null): Exists
    {
        $rule = Rule::exists((new Budget)->getTable(), 'id')
            ->where('team_id', $this->team()->id);

        if ($farmId !== null && $farmId > 0) {
            $rule->where('farm_id', $farmId);
        }

        return $rule;
    }

    protected function budgetLineRule(?int $budgetId = null): Exists
    {
        $rule = Rule::exists((new BudgetLine)->getTable(), 'id')
            ->where('team_id', $this->team()->id);

        if ($budgetId !== null && $budgetId > 0) {
            $rule->where('budget_id', $budgetId);
        }

        return $rule;
    }

    protected function fundingPhaseRule(?int $farmId = null): Exists
    {
        $rule = Rule::exists((new FundingPhase)->getTable(), 'id')
            ->where('team_id', $this->team()->id);

        if ($farmId !== null && $farmId > 0) {
            $rule->where('farm_id', $farmId);
        }

        return $rule;
    }

    protected function activityRule(?int $farmId = null): Exists
    {
        $rule = Rule::exists((new FarmActivity)->getTable(), 'id')
            ->where('team_id', $this->team()->id);

        if ($farmId !== null && $farmId > 0) {
            $rule->where('farm_id', $farmId);
        }

        return $rule;
    }
}
