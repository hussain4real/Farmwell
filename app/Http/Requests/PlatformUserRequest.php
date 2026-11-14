<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PlatformUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('managePlatform') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'reason' => ['required', 'string', 'max:1000'],
            'platform_role' => ['prohibited'],
            'email' => ['prohibited'],
            'phone' => ['prohibited'],
            'password' => ['prohibited'],
        ];
    }
}
