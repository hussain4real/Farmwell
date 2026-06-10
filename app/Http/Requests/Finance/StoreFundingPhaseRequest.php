<?php

namespace App\Http\Requests\Finance;

use App\Enums\FundingPhaseStatus;
use App\Http\Requests\Finance\Concerns\ResolvesFinanceRequestData;
use App\Support\Money;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFundingPhaseRequest extends FormRequest
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
            'budget_id' => ['nullable', 'integer', $this->budgetRule($farmId)],
            'name' => ['required', 'string', 'max:255'],
            'milestone' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'filled', Rule::enum(FundingPhaseStatus::class)],
            'planned_amount' => ['nullable', 'numeric', 'min:0'],
            'requested_amount' => ['nullable', 'numeric', 'min:0'],
            'approved_amount' => ['nullable', 'numeric', 'min:0'],
            'externally_released_amount' => ['nullable', 'numeric', 'min:0'],
            'expected_on' => ['nullable', 'date'],
            'released_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function phaseAttributes(): array
    {
        $validated = $this->validated();

        return [
            'farm_id' => $validated['farm_id'],
            'production_cycle_id' => $validated['production_cycle_id'] ?? null,
            'budget_id' => $validated['budget_id'] ?? null,
            'name' => $validated['name'],
            'milestone' => $validated['milestone'] ?? null,
            'status' => $validated['status'] ?? FundingPhaseStatus::Draft->value,
            'planned_amount_minor' => Money::toMinorUnit((string) ($validated['planned_amount'] ?? '0')),
            'requested_amount_minor' => Money::toMinorUnit((string) ($validated['requested_amount'] ?? '0')),
            'approved_amount_minor' => Money::toMinorUnit((string) ($validated['approved_amount'] ?? '0')),
            'externally_released_amount_minor' => Money::toMinorUnit((string) ($validated['externally_released_amount'] ?? '0')),
            'expected_on' => $validated['expected_on'] ?? null,
            'released_on' => $validated['released_on'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ];
    }
}
