<?php

namespace App\Http\Requests\Investors;

use App\Enums\InvestorAgreementStatus;
use App\Models\InvestorAgreement;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvestorCommentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $team = $this->route('current_team');
        $agreement = $this->route('investor_agreement');
        $user = $this->user();

        if (! $team instanceof Team || ! $agreement instanceof InvestorAgreement || ! $user) {
            return false;
        }

        if ($agreement->team_id !== $team->id) {
            return false;
        }

        if ($user->can('manageInvestorAgreements', $team)) {
            return true;
        }

        return $agreement->investor_id === $user->id
            && in_array($agreement->status, [InvestorAgreementStatus::Active, InvestorAgreementStatus::Completed], true)
            && $user->can('viewInvestorPortal', $team);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:2000'],
            'subject_type' => ['nullable', 'string', Rule::in(['expense', 'funding_phase', 'external_transfer', 'activity', 'task', 'plan_change', 'approval_request'])],
            'subject_id' => ['nullable', 'integer'],
        ];
    }
}
