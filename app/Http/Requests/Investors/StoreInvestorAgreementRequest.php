<?php

namespace App\Http\Requests\Investors;

use App\Enums\CapitalRecoveryRule;
use App\Enums\InvestorAgreementStatus;
use App\Enums\TeamRole;
use App\Models\Farm;
use App\Models\ProductionCycle;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreInvestorAgreementRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $team = $this->route('current_team');

        return $team instanceof Team && $this->user()?->can('manageInvestorAgreements', $team) === true;
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

        $farmId = $this->integer('farm_id');
        $cycleRule = Rule::exists((new ProductionCycle)->getTable(), 'id')
            ->where('team_id', $team->id);

        if ($farmId > 0) {
            $cycleRule->where('farm_id', $farmId);
        }

        return [
            'investor_id' => [
                'required',
                'integer',
                Rule::exists('team_members', 'user_id')
                    ->where('team_id', $team->id)
                    ->where('role', TeamRole::Investor->value),
            ],
            'farm_id' => ['required', 'integer', Rule::exists((new Farm)->getTable(), 'id')->where('team_id', $team->id)],
            'production_cycle_id' => [
                'nullable',
                'integer',
                $cycleRule,
            ],
            'title' => ['required', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(InvestorAgreementStatus::class)],
            'currency' => ['nullable', 'string', 'size:3'],
            'amount_committed' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'amount_funded' => ['nullable', 'regex:/^\d+(\.\d{1,2})?$/'],
            'capital_recovery_rule' => ['nullable', Rule::enum(CapitalRecoveryRule::class)],
            'investor_profit_share_percentage' => ['required', 'integer', 'between:0,100'],
            'farm_profit_share_percentage' => ['required', 'integer', 'between:0,100'],
            'funding_model' => ['nullable', 'string', 'max:255'],
            'role_responsibilities' => ['nullable', 'string', 'max:5000'],
            'public_notes' => ['nullable', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'signed_at' => ['nullable', 'date'],
            'agreement_document' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,webp'],
            'document_caption' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Validate agreement term consistency.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (
                    $this->integer('investor_profit_share_percentage')
                    + $this->integer('farm_profit_share_percentage') !== 100
                ) {
                    $validator->errors()->add('investor_profit_share_percentage', __('The investor and farm profit shares must total 100%.'));
                }
            },
        ];
    }
}
