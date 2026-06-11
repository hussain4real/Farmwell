<?php

namespace App\Http\Requests\Investors;

use App\Models\ExpenseCategory;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvestorApprovalSettingsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $team = $this->route('current_team');

        return $team instanceof Team && $this->user()?->can('manageApprovalRequests', $team) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $team = $this->route('current_team');
        assert($team instanceof Team);

        return [
            'currency' => ['nullable', 'string', 'size:3'],
            'threshold_amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'required_expense_category_ids' => ['nullable', 'array'],
            'required_expense_category_ids.*' => ['integer', Rule::exists((new ExpenseCategory)->getTable(), 'id')->where('team_id', $team->id)],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
