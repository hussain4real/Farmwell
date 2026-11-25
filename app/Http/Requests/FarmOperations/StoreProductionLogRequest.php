<?php

namespace App\Http\Requests\FarmOperations;

use App\Enums\CropGrowthStage;
use App\Enums\PestPressure;
use App\Enums\ProductionLogDomain;
use App\Models\Farm;
use App\Models\InventoryItem;
use App\Models\ProductionCycle;
use App\Models\ProductionUnit;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductionLogRequest extends FormRequest
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
        assert($team instanceof Team && $farm instanceof Farm);

        return [
            'log_date' => ['required', 'date'],
            'domain' => ['required', Rule::enum(ProductionLogDomain::class)],
            'production_unit_id' => [
                'nullable',
                'integer',
                Rule::exists((new ProductionUnit)->getTable(), 'id')
                    ->where('team_id', $team->id)
                    ->where('farm_id', $farm->id),
            ],
            'inventory_item_id' => [
                'nullable',
                'integer',
                Rule::exists((new InventoryItem)->getTable(), 'id')
                    ->where('team_id', $team->id),
            ],
            'mortality_count' => ['nullable', 'integer', 'min:0'],
            'cull_count' => ['nullable', 'integer', 'min:0'],
            'feed_used_kg' => ['nullable', 'numeric', 'min:0'],
            'feed_type' => ['nullable', 'string', 'max:100'],
            'feed_pellet_size_mm' => ['nullable', 'string', 'max:50'],
            'water_used_liters' => ['nullable', 'numeric', 'min:0'],
            'average_weight_g' => ['nullable', 'numeric', 'min:0'],
            'sample_size' => ['nullable', 'integer', 'min:1'],
            'eggs_collected' => ['nullable', 'integer', 'min:0'],
            'eggs_damaged' => ['nullable', 'integer', 'min:0'],
            'water_ph' => ['nullable', 'numeric', 'min:0', 'max:14'],
            'water_temp_c' => ['nullable', 'numeric', 'min:0', 'max:60'],
            'dissolved_oxygen_mg_l' => ['nullable', 'numeric', 'min:0', 'max:30'],
            'ammonia_mg_l' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'water_exchanged' => ['sometimes', 'boolean'],
            'growth_stage' => ['nullable', Rule::enum(CropGrowthStage::class)],
            'rainfall_mm' => ['nullable', 'numeric', 'min:0'],
            'irrigation_applied' => ['sometimes', 'boolean'],
            'pest_pressure' => ['nullable', Rule::enum(PestPressure::class)],
            'disease_observed' => ['nullable', 'string', 'max:255'],
            'input_applied_name' => ['nullable', 'string', 'max:150'],
            'input_applied_quantity' => ['nullable', 'numeric', 'min:0'],
            'input_applied_unit' => ['nullable', 'string', 'max:50'],
            'weather_notes' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'evidence' => ['nullable', 'array', 'max:5'],
            'evidence.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,webp,pdf'],
            'evidence_caption' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function logAttributes(): array
    {
        $validated = $this->validated();
        unset($validated['evidence'], $validated['evidence_caption']);

        $validated['mortality_count'] = (int) ($validated['mortality_count'] ?? 0);
        $validated['cull_count'] = (int) ($validated['cull_count'] ?? 0);
        $validated['water_exchanged'] = (bool) ($validated['water_exchanged'] ?? false);
        $validated['irrigation_applied'] = (bool) ($validated['irrigation_applied'] ?? false);

        return $validated;
    }
}
