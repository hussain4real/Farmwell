<?php

namespace App\Http\Requests;

use App\Enums\WorkspacePlan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlatformWorkspacePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('managePlatform') === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'plan' => ['required', 'string', Rule::enum(WorkspacePlan::class)],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
