<?php

namespace App\Http\Requests\Teams;

use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTeamSettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $team = $this->route('team');

        return $team instanceof Team && $this->user()?->can('manageSettings', $team) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'value' => ['required', 'array'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Get the validated setting value.
     *
     * @return array<string, mixed>
     */
    public function settingValue(): array
    {
        /** @var array<string, mixed> $value */
        $value = $this->validated('value');

        return $value;
    }

    /**
     * Get the validated change reason.
     */
    public function reason(): ?string
    {
        $reason = $this->validated('reason');

        return is_string($reason) ? $reason : null;
    }
}
