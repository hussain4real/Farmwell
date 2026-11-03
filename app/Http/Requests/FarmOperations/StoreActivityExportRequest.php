<?php

namespace App\Http\Requests\FarmOperations;

use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreActivityExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->route('current_team');

        return $team instanceof Team && $this->user()?->can('manageFarmOperations', $team) === true;
    }

    /** @return array<string, array<mixed>> */
    public function rules(): array
    {
        $team = $this->route('current_team');
        assert($team instanceof Team);
        $farmId = $this->integer('farm_id');

        return [
            'period_start' => ['required', 'date_format:Y-m-d'],
            'period_end' => ['required', 'date_format:Y-m-d', 'after_or_equal:period_start'],
            'farm_id' => ['required', 'integer', Rule::exists('farms', 'id')->where('team_id', $team->id)],
            'production_unit_id' => ['nullable', 'integer', Rule::exists('production_units', 'id')->where('team_id', $team->id)->where('farm_id', $farmId)],
            'commodity_id' => ['nullable', 'integer', Rule::exists('commodities', 'id')->where('team_id', $team->id)],
            'funding_phase_id' => ['nullable', 'integer', Rule::exists('funding_phases', 'id')->where('team_id', $team->id)->where('farm_id', $farmId)],
            'activity_type' => ['nullable', 'string', 'max:100'],
        ];
    }
}
