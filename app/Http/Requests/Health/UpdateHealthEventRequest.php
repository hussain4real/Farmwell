<?php

namespace App\Http\Requests\Health;

use App\Enums\HealthEventStatus;
use App\Models\HealthEvent;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHealthEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->route('current_team');
        $event = $this->route('health_event');

        return $team instanceof Team
            && $event instanceof HealthEvent
            && $event->team_id === $team->id
            && $this->user()?->can('manageFieldWork', $team) === true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'required', Rule::enum(HealthEventStatus::class)],
            'completed_on' => ['nullable', 'date'],
            'affected_count' => ['nullable', 'integer', 'min:0'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'outcome_notes' => ['nullable', 'string', 'max:2000'],
            'inventory_quantity_used' => ['nullable', 'numeric', 'min:0.01'],
        ];
    }
}
