<?php

namespace App\Http\Requests\Investors;

use App\Enums\ApprovalRequestStatus;
use App\Enums\InvestorAgreementStatus;
use App\Models\ApprovalRequest;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DecideApprovalRequestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $team = $this->route('current_team');
        $approvalRequest = $this->route('approval_request');

        $user = $this->user();

        if (! $team instanceof Team || ! $approvalRequest instanceof ApprovalRequest || ! $user) {
            return false;
        }

        if ($approvalRequest->team_id !== $team->id) {
            return false;
        }

        if ($user->can('manageApprovalRequests', $team)) {
            return true;
        }

        $agreement = $approvalRequest->investorAgreement;

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
            'status' => [
                'required',
                Rule::in([
                    ApprovalRequestStatus::Approved->value,
                    ApprovalRequestStatus::Rejected->value,
                    ApprovalRequestStatus::ClarificationRequested->value,
                ]),
            ],
            'approved_amount' => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/'],
            'decision_comment' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
