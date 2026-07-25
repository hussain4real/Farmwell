<?php

namespace App\Http\Requests\Investors;

use App\Models\InvestorAgreement;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AttachInvestorAgreementDocumentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $team = $this->route('current_team');
        $agreement = $this->route('investor_agreement');

        return $team instanceof Team
            && $agreement instanceof InvestorAgreement
            && $agreement->team_id === $team->id
            && $this->user()?->can('manageInvestorAgreements', $team) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'agreement_document' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp'],
            'document_caption' => ['nullable', 'string', 'max:255'],
        ];
    }
}
