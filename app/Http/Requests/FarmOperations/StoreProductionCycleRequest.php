<?php

namespace App\Http\Requests\FarmOperations;

use App\Enums\CommodityRole;
use App\Enums\FarmType;
use App\Enums\ProductionCycleStatus;
use App\Models\Commodity;
use App\Models\Farm;
use App\Models\ProductionUnit;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductionCycleRequest extends FormRequest
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
        $team = $this->team();
        $farm = $this->farm();

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('production_cycles')->where('farm_id', $farm->id),
            ],
            'season' => ['nullable', 'string', 'max:100'],
            'farm_type' => ['required', Rule::enum(FarmType::class)],
            'production_method' => ['nullable', 'string', 'max:255'],
            'planned_start_on' => ['required', 'date'],
            'planned_end_on' => ['nullable', 'date', 'after_or_equal:planned_start_on'],
            'expected_output_quantity' => ['nullable', 'numeric', 'min:0'],
            'expected_output_unit' => ['nullable', 'string', 'max:50'],
            'status' => ['sometimes', 'filled', Rule::enum(ProductionCycleStatus::class)],
            'plan_summary' => ['nullable', 'string', 'max:4000'],
            'production_unit_ids' => ['required', 'array', 'min:1'],
            'production_unit_ids.*' => [
                'integer',
                'distinct',
                Rule::exists((new ProductionUnit)->getTable(), 'id')
                    ->where('team_id', $team->id)
                    ->where('farm_id', $farm->id),
            ],
            'primary_commodity_id' => ['nullable', 'integer'],
            'secondary_commodity_id' => ['nullable', 'integer', 'different:primary_commodity_id'],
            'commodities' => ['required', 'array', 'min:1'],
            'commodities.*.id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists((new Commodity)->getTable(), 'id')->where('team_id', $team->id),
            ],
            'commodities.*.role' => ['required', Rule::enum(CommodityRole::class)],
            'commodities.*.expected_output_quantity' => ['nullable', 'numeric', 'min:0'],
            'commodities.*.expected_output_unit' => ['nullable', 'string', 'max:50'],
            'commodities.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Normalize the simple dashboard fields into the structured commodity payload.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('commodities') || ! $this->filled('primary_commodity_id')) {
            return;
        }

        $commodities = [
            [
                'id' => (int) $this->input('primary_commodity_id'),
                'role' => CommodityRole::Primary->value,
            ],
        ];

        if ($this->filled('secondary_commodity_id')) {
            $commodities[] = [
                'id' => (int) $this->input('secondary_commodity_id'),
                'role' => CommodityRole::Intercrop->value,
            ];
        }

        $this->merge(['commodities' => $commodities]);
    }

    public function team(): Team
    {
        $team = $this->route('current_team');

        assert($team instanceof Team);

        return $team;
    }

    public function farm(): Farm
    {
        $farm = $this->route('farm');

        assert($farm instanceof Farm);

        return $farm;
    }

    /**
     * Get production cycle attributes without relationship payloads.
     *
     * @return array<string, mixed>
     */
    public function cycleAttributes(): array
    {
        return collect($this->validated())
            ->except(['production_unit_ids', 'commodities', 'primary_commodity_id', 'secondary_commodity_id'])
            ->toArray();
    }

    /**
     * Get the validated production unit IDs.
     *
     * @return array<int, int>
     */
    public function productionUnitIds(): array
    {
        return array_map('intval', $this->validated('production_unit_ids'));
    }

    /**
     * Get the validated commodity mix.
     *
     * @return array<int, array{id: int, role: string, expected_output_quantity?: mixed, expected_output_unit?: mixed, notes?: mixed}>
     */
    public function commodities(): array
    {
        /** @var array<int, array{id: int, role: string, expected_output_quantity?: mixed, expected_output_unit?: mixed, notes?: mixed}> $commodities */
        $commodities = $this->validated('commodities');

        return $commodities;
    }
}
