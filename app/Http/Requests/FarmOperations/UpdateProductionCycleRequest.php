<?php

namespace App\Http\Requests\FarmOperations;

use App\Enums\CropGrowthStage;
use App\Enums\EnterpriseSubtype;
use App\Enums\ProductionCycleStatus;
use App\Models\Farm;
use App\Models\ProductionCycle;
use App\Models\ProductionUnit;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductionCycleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $team = $this->route('current_team');
        $farm = $this->route('farm');
        $cycle = $this->route('production_cycle');
        assert($team instanceof Team && $farm instanceof Farm && $cycle instanceof ProductionCycle);

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('production_cycles')->where('farm_id', $farm->id)->ignore($cycle->id),
            ],
            'season' => ['nullable', 'string', 'max:100'],
            'enterprise_subtype' => ['nullable', Rule::enum(EnterpriseSubtype::class)],
            'breed_or_variety' => ['nullable', 'string', 'max:150'],
            'production_method' => ['nullable', 'string', 'max:255'],
            'planned_start_on' => ['sometimes', 'required', 'date'],
            'planned_end_on' => ['nullable', 'date'],
            'actual_start_on' => ['nullable', 'date'],
            'actual_end_on' => ['nullable', 'date'],
            'expected_output_quantity' => ['nullable', 'numeric', 'min:0'],
            'expected_output_unit' => ['nullable', 'string', 'max:50'],
            'initial_stock_count' => ['nullable', 'integer', 'min:0'],
            'initial_average_weight_g' => ['nullable', 'numeric', 'min:0'],
            'target_weight_g' => ['nullable', 'numeric', 'min:0'],
            'current_growth_stage' => ['nullable', Rule::enum(CropGrowthStage::class)],
            'status' => ['sometimes', 'required', Rule::enum(ProductionCycleStatus::class)],
            'plan_summary' => ['nullable', 'string', 'max:4000'],
            'production_unit_ids' => ['sometimes', 'array', 'min:1'],
            'production_unit_ids.*' => [
                'integer',
                'distinct',
                Rule::exists((new ProductionUnit)->getTable(), 'id')
                    ->where('team_id', $team->id)
                    ->where('farm_id', $farm->id),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function cycleAttributes(): array
    {
        return collect($this->validated())
            ->except(['production_unit_ids'])
            ->toArray();
    }

    /**
     * @return array<int, int>|null
     */
    public function productionUnitIds(): ?array
    {
        $ids = $this->validated('production_unit_ids');

        return is_array($ids) ? array_map('intval', $ids) : null;
    }
}
