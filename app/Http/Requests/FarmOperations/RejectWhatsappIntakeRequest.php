<?php

namespace App\Http\Requests\FarmOperations;

use App\Enums\WhatsappIntakeStatus;
use App\Models\Team;
use App\Models\WhatsappIntake;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RejectWhatsappIntakeRequest extends FormRequest
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
        return [
            'rejection_reason' => ['required', 'string', 'max:2000'],
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

    public function rejectionReason(): string
    {
        $reason = $this->validated('rejection_reason');

        assert(is_string($reason));

        return $reason;
    }
}
