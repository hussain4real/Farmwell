<?php

namespace App\Http\Requests\FarmOperations;

use App\Enums\ProductionUnitType;
use App\Models\Farm;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductionUnitRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $team = $this->route('current_team');
        $farm = $this->route('farm');

        return $team instanceof Team
            && $farm instanceof Farm
            && $farm->team_id === $team->id
            && $this->user()?->can('manageFarmOperations', $team) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $farm = $this->farm();

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('production_units')->where('farm_id', $farm->id),
            ],
            'unit_type' => ['required', Rule::enum(ProductionUnitType::class)],
            'size' => ['nullable', 'numeric', 'min:0'],
            'size_unit' => ['nullable', 'string', 'max:50'],
            'capacity' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'filled', 'string', 'max:50'],
            'gps_coordinates' => ['nullable', 'string', 'max:255'],
            'suitability_notes' => ['nullable', 'string', 'max:2000'],
            'history_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function farm(): Farm
    {
        $farm = $this->route('farm');

        assert($farm instanceof Farm);

        return $farm;
    }
}
