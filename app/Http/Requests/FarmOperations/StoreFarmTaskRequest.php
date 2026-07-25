<?php

namespace App\Http\Requests\FarmOperations;

use App\Enums\FarmTaskStatus;
use App\Models\Farm;
use App\Models\ProductionCycle;
use App\Models\ProductionUnit;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFarmTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $team = $this->route('current_team');

        return $team instanceof Team && $this->user()?->can('manageFarmOperations', $team) === true;
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
            'assigned_to_id' => ['nullable', 'integer', Rule::exists((new User)->getTable(), 'id')],
            'title' => ['required', 'string', 'max:255'],
            'activity_type' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'planned_for' => ['nullable', 'date'],
            'due_on' => ['required', 'date'],
            'reminder_at' => ['nullable', 'date'],
            'status' => ['sometimes', 'filled', Rule::enum(FarmTaskStatus::class)],
            'status_reason' => [
                'nullable',
                'string',
                'max:2000',
                Rule::requiredIf(fn (): bool => $this->submittedStatus()?->requiresReason() ?? false),
            ],
            'investor_visible' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get validation callbacks that run after the main rules.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $team = $this->team();
                $farmId = $this->integer('farm_id');

                if ($this->filled('production_unit_id') && ! ProductionUnit::query()
                    ->whereKey($this->integer('production_unit_id'))
                    ->where('farm_id', $farmId)
                    ->exists()) {
                    $validator->errors()->add('production_unit_id', __('The production unit must belong to the selected farm.'));
                }

                if ($this->filled('production_cycle_id') && ! ProductionCycle::query()
                    ->whereKey($this->integer('production_cycle_id'))
                    ->where('farm_id', $farmId)
                    ->exists()) {
                    $validator->errors()->add('production_cycle_id', __('The production cycle must belong to the selected farm.'));
                }

                if ($this->filled('assigned_to_id') && ! $team->members()
                    ->whereKey($this->integer('assigned_to_id'))
                    ->exists()) {
                    $validator->errors()->add('assigned_to_id', __('The assigned user must belong to the current team.'));
                }
            },
        ];
    }

    public function team(): Team
    {
        $team = $this->route('current_team');

        assert($team instanceof Team);

        return $team;
    }

    /**
     * Get task attributes.
     *
     * @return array<string, mixed>
     */
    public function taskAttributes(): array
    {
        return $this->validated();
    }

    private function submittedStatus(): ?FarmTaskStatus
    {
        return FarmTaskStatus::tryFrom((string) $this->input('status'));
    }
}
