<?php

namespace App\Http\Requests\Teams;

use App\Enums\TeamRole;
use App\Rules\UniqueTeamInvitation;
use App\Services\InfobipVerification;
use App\Support\PilotPhone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateTeamInvitationRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['bail', 'required_without:phone', 'prohibits:phone', 'nullable', 'string', 'email', 'max:255', new UniqueTeamInvitation($this->route('team'))],
            'phone' => ['bail', 'nullable', Rule::prohibitedIf(! InfobipVerification::enabled()), 'string',
                config('services.phone_login.pilot_host') ? 'regex:'.PilotPhone::NumberPattern : Rule::in([config('services.phone_login.allowed_number')]), new UniqueTeamInvitation($this->route('team'))],
            'role' => ['required', 'string', Rule::in(array_column(TeamRole::assignable(), 'value'))],
        ];
    }
}
