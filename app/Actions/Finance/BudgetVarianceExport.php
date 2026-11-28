<?php

namespace App\Actions\Finance;

use App\Enums\ExpenseStatus;
use App\Models\Budget;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;

class BudgetVarianceExport extends StringValueBinder implements FromArray, WithColumnWidths, WithCustomValueBinder, WithStrictNullComparison
{
    public const MAX_EXPENSES = 1000;

    public function __construct(private Budget $budget, private string $start, private string $end)
    {
        $this->setNumericConversion(false);
    }

    public function array(): array
    {
        $scope = ['team_id' => $this->budget->team_id, 'farm_id' => $this->budget->farm_id, 'currency' => $this->budget->currency];
        $lines = $this->budget->lines()->where($scope);
        $planned = (clone $lines)->groupBy('expense_category_id')
            ->selectRaw('expense_category_id, SUM(planned_amount_minor) AS planned')->pluck('planned', 'expense_category_id');
        $expenses = Expense::query()->where($scope)
            ->where(function (Builder $query) use ($lines) {
                $query->where('budget_id', $this->budget->id)
                    ->orWhere(fn (Builder $query) => $query->whereNull('budget_id')->whereIn('budget_line_id', $lines->select('id')));
            })
            ->whereIn('status', ExpenseStatus::spendableValues())
            ->where('incurred_on', '>=', $this->start)
            ->where('incurred_on', '<', CarbonImmutable::parse($this->end)->addDay()->toDateString())
            ->with(['farmActivity' => fn ($query) => $query->where('team_id', $this->budget->team_id)->where('farm_id', $this->budget->farm_id)->select('id', 'activity_type')])
            ->orderBy('incurred_on')->orderBy('id')->limit(self::MAX_EXPENSES + 1)->get();

        if ($expenses->count() > self::MAX_EXPENSES) {
            throw ValidationException::withMessages(['period_end' => 'More than '.self::MAX_EXPENSES.' expenses match. Choose a shorter period.']);
        }

        $spent = $expenses->groupBy('expense_category_id')->map(fn ($group) => $group->sum('amount_minor'));
        $categoryIds = $planned->keys()->merge($spent->keys())->unique();
        $names = ExpenseCategory::query()->where('team_id', $this->budget->team_id)
            ->whereIn('id', $categoryIds->filter())->pluck('name', 'id');
        $rows = [
            ['Budget variance', $this->budget->name],
            ['Farm', $this->budget->farm->name],
            ['Expense period', $this->start, $this->end],
            ['Currency', $this->budget->currency],
            ['Category', 'Full budget', 'Period spend', 'Variance (budget less spend)', 'Remaining balance'],
        ];
        foreach ($categoryIds->sortBy(fn ($id) => $names->get($id, 'Uncategorized')) as $id) {
            $rows[] = $this->varianceRow($names->get($id, 'Uncategorized'), (int) $planned->get($id, 0), (int) $spent->get($id, 0));
        }
        $rows[] = $this->varianceRow('Total', (int) $planned->sum(), (int) $spent->sum());
        $rows[] = [];
        $rows[] = ['Expense date', 'Category', 'Amount', 'Status', 'Expense description', 'Recorded notes', 'Linked field activity'];
        foreach ($expenses as $expense) {
            $activity = $expense->farmActivity;
            $rows[] = [$expense->incurred_on->toDateString(), $names->get($expense->expense_category_id), $expense->amount_minor / 100,
                $expense->status->label(), $expense->description, $expense->notes, $activity ? '#'.$activity->id.' '.$activity->activity_type : null];
        }

        return $rows;
    }

    /** @return array<int, string|float|int> */
    private function varianceRow(string $label, int $planned, int $spent): array
    {
        $variance = $planned - $spent;

        return [$label, $planned / 100, $spent / 100, $variance / 100, max(0, $variance) / 100];
    }

    public function columnWidths(): array
    {
        return ['A' => 28, 'B' => 26, 'C' => 18, 'D' => 30, 'E' => 45, 'F' => 45, 'G' => 32];
    }
}
