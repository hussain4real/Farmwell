<?php

namespace App\Http\Requests\FarmOperations;

use App\Enums\CropGrowthStage;
use App\Enums\EnterpriseSubtype;
use App\Enums\FarmType;
use App\Enums\ProductionCycleStatus;
use App\Enums\ProductionUnitType;
use App\Models\Team;
use App\Support\FarmTemplates;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOnboardingRequest extends FormRequest
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

        $templateKeys = array_column(FarmTemplates::all(), 'key');

        return [
            'template_key' => ['nullable', 'string', Rule::in($templateKeys)],
            'currency' => ['nullable', 'string', 'size:3'],
            'investor_mode' => ['sometimes', 'boolean'],
            'farm_name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('farms', 'name')->where('team_id', $team->id),
            ],
            'farm_type' => ['required', Rule::enum(FarmType::class)],
            'location' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'units' => ['required', 'array', 'min:1', 'max:10'],
            'units.*.name' => ['required', 'string', 'max:255', 'distinct'],
            'units.*.unit_type' => ['required', Rule::enum(ProductionUnitType::class)],
            'units.*.size' => ['nullable', 'numeric', 'min:0'],
            'units.*.size_unit' => ['nullable', 'string', 'max:50'],
            'units.*.capacity' => ['nullable', 'string', 'max:255'],
            'units.*.water_source' => ['nullable', 'string', 'max:100'],
            'units.*.housing_system' => ['nullable', 'string', 'max:100'],
            'commodity_name' => ['required', 'string', 'max:255'],
            'commodity_unit' => ['nullable', 'string', 'max:50'],
            'cycle_name' => ['required', 'string', 'max:255'],
            'season' => ['nullable', 'string', 'max:100'],
            'enterprise_subtype' => ['nullable', Rule::enum(EnterpriseSubtype::class)],
            'breed_or_variety' => ['nullable', 'string', 'max:150'],
            'planned_start_on' => ['required', 'date'],
            'planned_end_on' => ['nullable', 'date', 'after_or_equal:planned_start_on'],
            'expected_output_quantity' => ['nullable', 'numeric', 'min:0'],
            'expected_output_unit' => ['nullable', 'string', 'max:50'],
            'initial_stock_count' => ['nullable', 'integer', 'min:0'],
            'initial_average_weight_g' => ['nullable', 'numeric', 'min:0'],
            'target_weight_g' => ['nullable', 'numeric', 'min:0'],
            'current_growth_stage' => ['nullable', Rule::enum(CropGrowthStage::class)],
            'cycle_status' => ['sometimes', Rule::enum(ProductionCycleStatus::class)],
            'plan_summary' => ['nullable', 'string', 'max:4000'],
            'create_starter_tasks' => ['sometimes', 'boolean'],
            'create_starter_inventory' => ['sometimes', 'boolean'],
            'create_health_schedule' => ['sometimes', 'boolean'],
        ];
    }
}
