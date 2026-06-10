<?php

namespace App\Http\Requests\Finance;

use App\Http\Requests\Finance\Concerns\ResolvesFinanceRequestData;
use App\Models\Budget;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreBudgetLineRequest extends FormRequest
{
    use ResolvesFinanceRequestData;

    public function authorize(): bool
    {
        $budget = $this->route('budget');

        return $budget instanceof Budget
            && $budget->team_id === $this->team()->id
            && $this->authorizeFinanceManagement();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'expense_category_id' => ['nullable', 'integer', $this->categoryRule()],
            'description' => ['required', 'string', 'max:255'],
            'planned_amount' => ['required', 'numeric', 'min:0'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:500'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function lineAttributes(): array
    {
        return [
            'expense_category_id' => $this->validated('expense_category_id'),
            'description' => $this->validated('description'),
            'planned_amount_minor' => Money::toMinorUnit((string) $this->validated('planned_amount')),
            'sort_order' => $this->validated('sort_order', 0),
        ];
    }
}
