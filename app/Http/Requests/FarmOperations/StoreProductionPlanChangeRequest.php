<?php

namespace App\Http\Requests\FarmOperations;

use App\Enums\InvestorVisibilityStatus;
use App\Enums\ProductionPlanChangeType;
use App\Models\Farm;
use App\Models\InvestorAgreement;
use App\Models\ProductionCycle;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductionPlanChangeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $team = $this->route('current_team');
        $farm = $this->route('farm');
        $productionCycle = $this->route('production_cycle');

        return $team instanceof Team
            && $farm instanceof Farm
            && $productionCycle instanceof ProductionCycle
            && $farm->team_id === $team->id
            && $productionCycle->team_id === $team->id
            && $productionCycle->farm_id === $farm->id
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
        $productionCycle = $this->route('production_cycle');

        assert($team instanceof Team);
        assert($farm instanceof Farm);
        assert($productionCycle instanceof ProductionCycle);

        return [
            'change_type' => ['required', Rule::enum(ProductionPlanChangeType::class)],
            'reason' => ['required', 'string', 'max:2000'],
            'impact' => ['required', 'string', 'max:2000'],
            'investor_safe_summary' => ['nullable', 'string', 'max:2000'],
            'investor_agreement_id' => [
                'nullable',
                'integer',
                Rule::exists((new InvestorAgreement)->getTable(), 'id')
                    ->where('team_id', $team->id)
                    ->where('farm_id', $farm->id)
                    ->where(function ($query) use ($productionCycle): void {
                        $query->whereNull('production_cycle_id')
                            ->orWhere('production_cycle_id', $productionCycle->id);
                    }),
            ],
            'investor_visibility_status' => ['nullable', Rule::enum(InvestorVisibilityStatus::class)],
            'old_values' => ['nullable', 'array'],
            'new_values' => ['nullable', 'array'],
        ];
    }
}
