<?php

namespace App\Http\Requests\Assistant;

use App\Actions\Teams\ResolveWorkspacePlan;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ResolveAssistantApprovalsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->route('current_team');

        return $team instanceof Team
            && ! $this->attributes->has('platform_access_team')
            && app(ResolveWorkspacePlan::class)->handle($team)->allowsAssistant();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decisions' => ['required', 'array', 'min:1'],
            'decisions.*.action' => ['required', 'string', Rule::in(['approve', 'edit', 'reject'])],
            'decisions.*.arguments' => ['required_if:decisions.*.action,edit', 'array'],
            'decisions.*.reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
