<?php

namespace App\Http\Requests\Finance;

use App\Enums\BudgetStatus;
use App\Http\Requests\Finance\Concerns\ResolvesFinanceRequestData;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBudgetRequest extends FormRequest
{
    use ResolvesFinanceRequestData;

    public function authorize(): bool
    {
        return $this->authorizeFinanceManagement();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $farmId = $this->integer('farm_id');

        return [
            'farm_id' => ['required', 'integer', $this->farmRule()],
            'production_cycle_id' => ['nullable', 'integer', $this->cycleRule($farmId)],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', 'filled', Rule::enum(BudgetStatus::class)],
            'period_start_on' => ['nullable', 'date'],
            'period_end_on' => ['nullable', 'date', 'after_or_equal:period_start_on'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'lines' => ['nullable', 'array', 'max:20'],
            'lines.*.expense_category_id' => ['nullable', 'integer', $this->categoryRule()],
            'lines.*.description' => ['required_with:lines', 'string', 'max:255'],
            'lines.*.planned_amount' => ['required_with:lines', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,2})?$/'],
            'lines.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:500'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function budgetAttributes(): array
    {
        return collect($this->validated())
            ->except(['lines'])
            ->toArray();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function budgetLines(): array
    {
        return collect($this->validated('lines', []))
            ->map(fn (array $line): array => [
                'expense_category_id' => $line['expense_category_id'] ?? null,
                'description' => $line['description'],
                'planned_amount_minor' => Money::toMinorUnit((string) $line['planned_amount']),
                'sort_order' => $line['sort_order'] ?? 0,
            ])
            ->values()
            ->toArray();
    }
}
