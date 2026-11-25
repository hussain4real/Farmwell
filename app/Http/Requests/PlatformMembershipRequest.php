<?php

namespace App\Http\Requests;

use App\Enums\TeamRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlatformMembershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('managePlatform') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'team_id' => ['required', 'integer', Rule::exists('teams', 'id')->whereNull('deleted_at')],
            'role' => ['required', Rule::in([...array_column(TeamRole::assignable(), 'value'), 'remove'])],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
