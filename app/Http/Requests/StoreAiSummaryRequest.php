<?php

namespace App\Http\Requests;

use App\Enums\InvestorAgreementStatus;
use App\Enums\WhatsappIntakeStatus;
use App\Models\Team;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAiSummaryRequest extends FormRequest
{
    public function authorize(): bool
    {
        $team = $this->route('current_team');

        return $team instanceof Team
            && ($this->isInvestorProgress()
                ? $this->user()?->can('manageInvestorAgreements', $team) === true
                : $this->user()?->can('manageFarmOperations', $team) === true);
    }

    private function isInvestorProgress(): bool
    {
        return in_array($this->input('type'), [null, 'investor_progress'], true);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $team = $this->route('current_team');
        assert($team instanceof Team);

        $farmId = $this->integer('farm_id');
        $cycleId = $this->integer('production_cycle_id');
        $intake = Rule::exists('whatsapp_intakes', 'id')->where('team_id', $team->id)
            ->where('review_status', WhatsappIntakeStatus::Pending->value)->where('farm_id', $farmId);
        if ($cycleId > 0) {
            $intake->where('production_cycle_id', $cycleId);
        }

        return [
            'type' => ['nullable', Rule::in(['investor_progress', 'weekly', 'risk_decision', 'intake_cleanup'])],
            'agreement_id' => [Rule::requiredIf($this->isInvestorProgress()), Rule::prohibitedIf(! $this->isInvestorProgress()), 'nullable', 'integer', Rule::exists('investor_agreements', 'id')
                ->where('team_id', $team->id)
                ->whereIn('status', [InvestorAgreementStatus::Active->value, InvestorAgreementStatus::Completed->value])],
            'farm_id' => [Rule::requiredIf(! $this->isInvestorProgress()), Rule::prohibitedIf($this->isInvestorProgress()), 'nullable', 'integer', Rule::exists('farms', 'id')->where('team_id', $team->id)],
            'production_cycle_id' => [Rule::prohibitedIf($this->isInvestorProgress()), 'nullable', 'integer', Rule::exists('production_cycles', 'id')->where('team_id', $team->id)->where('farm_id', $farmId)],
            'whatsapp_intake_id' => ['required_if:type,intake_cleanup', 'prohibited_unless:type,intake_cleanup', 'nullable', 'integer', $intake],
            'input_text' => ['required_if:type,intake_cleanup', 'prohibited_unless:type,intake_cleanup', 'nullable', 'string', 'max:5000'],
        ];
    }
}
