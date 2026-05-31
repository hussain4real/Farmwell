<?php

namespace App\Http\Requests\FarmOperations;

use App\Enums\FarmActivityStatus;
use App\Enums\WhatsappIntakeStatus;
use App\Models\Commodity;
use App\Models\Farm;
use App\Models\ProductionCycle;
use App\Models\ProductionUnit;
use App\Models\Team;
use App\Models\WhatsappIntake;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConvertWhatsappIntakeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $team = $this->route('current_team');
        $intake = $this->route('whatsapp_intake');

        return $team instanceof Team
            && $intake instanceof WhatsappIntake
            && $intake->team_id === $team->id
            && $intake->review_status === WhatsappIntakeStatus::Pending
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
        $farmId = $this->integer('farm_id');

        return [
            'farm_id' => [
                'required',
                'integer',
                Rule::exists((new Farm)->getTable(), 'id')->where('team_id', $team->id),
            ],
            'production_unit_id' => [
                'nullable',
                'integer',
                Rule::exists((new ProductionUnit)->getTable(), 'id')
                    ->where('team_id', $team->id)
                    ->where('farm_id', $farmId),
            ],
            'production_cycle_id' => [
                'nullable',
                'integer',
                Rule::exists((new ProductionCycle)->getTable(), 'id')
                    ->where('team_id', $team->id)
                    ->where('farm_id', $farmId),
            ],
            'commodity_id' => [
                'nullable',
                'integer',
                Rule::exists((new Commodity)->getTable(), 'id')->where('team_id', $team->id),
            ],
            'activity_date' => ['required', 'date'],
            'activity_type' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:4000'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'next_activity' => ['nullable', 'string', 'max:2000'],
            'investor_safe_summary' => ['nullable', 'string', 'max:2000'],
            'status' => ['sometimes', 'filled', Rule::enum(FarmActivityStatus::class)],
        ];
    }

    public function team(): Team
    {
        $team = $this->route('current_team');

        assert($team instanceof Team);

        return $team;
    }

    public function whatsappIntake(): WhatsappIntake
    {
        $intake = $this->route('whatsapp_intake');

        assert($intake instanceof WhatsappIntake);

        return $intake;
    }

    /**
     * Get official activity attributes for conversion.
     *
     * @return array<string, mixed>
     */
    public function activityAttributes(): array
    {
        return $this->validated();
    }

    /**
     * Get normalized intake attributes to persist after conversion.
     *
     * @return array<string, mixed>
     */
    public function normalizedAttributes(): array
    {
        return [
            'farm_id' => $this->validated('farm_id'),
            'production_unit_id' => $this->validated('production_unit_id'),
            'production_cycle_id' => $this->validated('production_cycle_id'),
            'commodity_id' => $this->validated('commodity_id'),
            'normalized_activity_date' => $this->validated('activity_date'),
            'normalized_activity_type' => $this->validated('activity_type'),
            'normalized_description' => $this->validated('description'),
            'normalized_cost' => $this->validated('cost'),
            'normalized_next_activity' => $this->validated('next_activity'),
            'normalized_investor_safe_summary' => $this->validated('investor_safe_summary'),
        ];
    }
}
