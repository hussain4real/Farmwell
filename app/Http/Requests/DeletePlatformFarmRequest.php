<?php

namespace App\Http\Requests;

use App\Models\Farm;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeletePlatformFarmRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('managePlatform') === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $farm = $this->route('farm');

        return [
            'confirm_name' => ['required', 'string', Rule::in([$farm instanceof Farm ? $farm->name : ''])],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'confirm_name.in' => 'Enter the exact farm name to confirm deletion.',
        ];
    }
}
