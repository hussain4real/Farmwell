<?php

namespace App\Http\Requests\FarmOperations;

use App\Models\Farm;
use App\Models\ProductionCycle;
use App\Models\ProductionUnit;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGradeTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->route('current_team');
        $farm = $this->route('farm');
        $cycle = $this->route('production_cycle');

        return $team instanceof Team
            && $farm instanceof Farm
            && $cycle instanceof ProductionCycle
            && $farm->team_id === $team->id
            && $cycle->farm_id === $farm->id
            && $this->user()?->can('manageFieldWork', $team) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $team = $this->route('current_team');
        $farm = $this->route('farm');
        $cycle = $this->route('production_cycle');
        assert($team instanceof Team && $farm instanceof Farm && $cycle instanceof ProductionCycle);

        return [
            'source_unit_id' => [
                'nullable',
                'integer',
                Rule::exists((new ProductionUnit)->getTable(), 'id')
                    ->where('team_id', $team->id)
                    ->where('farm_id', $farm->id),
            ],
            'target_unit_id' => [
                'required',
                'integer',
                Rule::exists((new ProductionUnit)->getTable(), 'id')
                    ->where('team_id', $team->id)
                    ->where('farm_id', $farm->id),
            ],
            'target_cycle_id' => [
                'required',
                'integer',
                Rule::notIn([$cycle->id]),
                Rule::exists((new ProductionCycle)->getTable(), 'id')
                    ->where('team_id', $team->id)
                    ->where('farm_id', $farm->id),
            ],
            'count' => ['required', 'integer', 'min:1'],
            'transfer_date' => ['required', 'date'],
            'average_weight_g' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
