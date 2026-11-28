<?php

namespace App\Http\Requests\Health;

use App\Models\Farm;
use App\Models\ProductionCycle;
use App\Models\Team;
use App\Support\HealthScheduleTemplates;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ApplyHealthPresetRequest extends FormRequest
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
            && $this->user()?->can('manageFarmOperations', $team) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $keys = array_keys(HealthScheduleTemplates::all());

        return [
            'preset_key' => ['required', 'string', Rule::in($keys)],
        ];
    }
}
