<?php

namespace App\Http\Requests\FarmOperations;

use App\Enums\FarmTaskStatus;
use App\Models\FarmTask;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFarmTaskStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $team = $this->route('current_team');
        $task = $this->route('farm_task');

        return $team instanceof Team
            && $task instanceof FarmTask
            && $task->team_id === $team->id
            && $this->user()?->can('manageFarmOperations', $team) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(FarmTaskStatus::class)],
            'status_reason' => [
                'nullable',
                'string',
                'max:2000',
                Rule::requiredIf(fn (): bool => $this->submittedStatus()?->requiresReason() ?? false),
            ],
        ];
    }

    public function team(): Team
    {
        $team = $this->route('current_team');

        assert($team instanceof Team);

        return $team;
    }

    public function farmTask(): FarmTask
    {
        $task = $this->route('farm_task');

        assert($task instanceof FarmTask);

        return $task;
    }

    public function status(): FarmTaskStatus
    {
        return FarmTaskStatus::from((string) $this->input('status'));
    }

    public function reason(): ?string
    {
        $reason = $this->validated('status_reason');

        return is_string($reason) ? $reason : null;
    }

    private function submittedStatus(): ?FarmTaskStatus
    {
        return FarmTaskStatus::tryFrom((string) $this->input('status'));
    }
}
