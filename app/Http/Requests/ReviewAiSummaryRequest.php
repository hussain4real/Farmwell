<?php

namespace App\Http\Requests;

use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewAiSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->route('current_team');

        return $team instanceof Team && ($this->user()?->can('manageInvestorAgreements', $team) === true || $this->user()?->can('manageFarmOperations', $team) === true);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['approve', 'reject'])],
            'final_text' => ['required_if:action,approve', 'nullable', 'string', 'max:20000'],
        ];
    }
}
