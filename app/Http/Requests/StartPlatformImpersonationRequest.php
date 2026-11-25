<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartPlatformImpersonationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('managePlatform') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
