<?php

namespace App\Http\Requests\Health;

use App\Enums\HealthEventStatus;
use App\Enums\HealthEventType;
use App\Models\Farm;
use App\Models\InventoryItem;
use App\Models\ProductionCycle;
use App\Models\ProductionUnit;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHealthEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->route('current_team');

        return $team instanceof Team && $this->user()?->can('manageFarmOperations', $team) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $team = $this->route('current_team');
        assert($team instanceof Team);

        return [
            'farm_id' => [
                'required',
                'integer',
                Rule::exists((new Farm)->getTable(), 'id')->where('team_id', $team->id),
            ],
            'production_cycle_id' => [
                'nullable',
                'integer',
                Rule::exists((new ProductionCycle)->getTable(), 'id')->where('team_id', $team->id),
            ],
            'production_unit_id' => [
                'nullable',
                'integer',
                Rule::exists((new ProductionUnit)->getTable(), 'id')->where('team_id', $team->id),
            ],
            'inventory_item_id' => [
                'nullable',
                'integer',
                Rule::exists((new InventoryItem)->getTable(), 'id')->where('team_id', $team->id),
            ],
            'event_type' => ['required', Rule::enum(HealthEventType::class)],
            'title' => ['required', 'string', 'max:255'],
            'product_name' => ['nullable', 'string', 'max:150'],
            'dosage' => ['nullable', 'string', 'max:100'],
            'administration_route' => ['nullable', 'string', 'max:100'],
            'scheduled_on' => ['required', 'date'],
            'completed_on' => ['nullable', 'date'],
            'withdrawal_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'status' => ['sometimes', Rule::enum(HealthEventStatus::class)],
            'affected_count' => ['nullable', 'integer', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
