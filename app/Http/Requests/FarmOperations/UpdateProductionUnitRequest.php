<?php

namespace App\Http\Requests\FarmOperations;

use App\Enums\ProductionUnitType;
use App\Models\Farm;
use App\Models\ProductionUnit;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductionUnitRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $team = $this->route('current_team');
        $farm = $this->route('farm');
        $unit = $this->route('production_unit');

        return $team instanceof Team
            && $farm instanceof Farm
            && $unit instanceof ProductionUnit
            && $farm->team_id === $team->id
            && $unit->farm_id === $farm->id
            && $this->user()?->can('manageFarmOperations', $team) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $farm = $this->route('farm');
        $unit = $this->route('production_unit');
        assert($farm instanceof Farm && $unit instanceof ProductionUnit);

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('production_units')->where('farm_id', $farm->id)->ignore($unit->id),
            ],
            'unit_type' => ['required', Rule::enum(ProductionUnitType::class)],
            'size' => ['nullable', 'numeric', 'min:0'],
            'size_unit' => ['nullable', 'string', 'max:50'],
            'capacity' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::in(['available', 'in_use', 'resting', 'maintenance', 'retired'])],
            'gps_coordinates' => ['nullable', 'string', 'max:255'],
            'water_source' => ['nullable', 'string', 'max:100'],
            'housing_system' => ['nullable', 'string', 'max:100'],
            'suitability_notes' => ['nullable', 'string', 'max:2000'],
            'history_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
