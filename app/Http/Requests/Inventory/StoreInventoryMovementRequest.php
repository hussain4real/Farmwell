<?php

namespace App\Http\Requests\Inventory;

use App\Enums\InventoryMovementType;
use App\Models\Farm;
use App\Models\ProductionCycle;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->route('current_team');
        $type = InventoryMovementType::tryFrom((string) $this->input('movement_type'));
        $ability = $type === InventoryMovementType::Usage ? 'manageFieldWork' : 'manageFarmOperations';

        return $team instanceof Team && $this->user()?->can($ability, $team) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $team = $this->route('current_team');
        assert($team instanceof Team);

        return [
            'movement_type' => ['required', Rule::enum(InventoryMovementType::class)],
            'adjustment_direction' => ['nullable', Rule::in(['in', 'out'])],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'movement_date' => ['required', 'date'],
            'farm_id' => [
                'nullable',
                'integer',
                Rule::exists((new Farm)->getTable(), 'id')->where('team_id', $team->id),
            ],
            'production_cycle_id' => [
                'nullable',
                'integer',
                Rule::exists((new ProductionCycle)->getTable(), 'id')->where('team_id', $team->id),
            ],
            'reference' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'record_as_expense' => ['sometimes', 'boolean'],
        ];
    }
}
