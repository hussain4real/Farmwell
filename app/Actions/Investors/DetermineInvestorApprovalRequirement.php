<?php

namespace App\Actions\Investors;

use App\Enums\ApprovalRequestType;
use App\Enums\ApprovalTriggerType;
use App\Models\ApprovalRule;
use App\Models\ExpenseCategory;
use App\Models\FundingPhase;
use App\Models\InvestorAgreement;
use App\Models\Team;

class DetermineInvestorApprovalRequirement
{
    public function __construct(private ResolveInvestorApprovalSettings $resolveInvestorApprovalSettings)
    {
        //
    }

    /**
     * @return array{required: bool, triggerType: ApprovalTriggerType, thresholdAmountMinor: int|null, rule: ApprovalRule|null, reason: string}
     */
    public function handle(
        Team $team,
        ?InvestorAgreement $agreement,
        ApprovalRequestType $requestType,
        int $amountMinor = 0,
        ?ExpenseCategory $category = null,
        ?FundingPhase $fundingPhase = null,
        ?string $farmType = null,
    ): array {
        if (! $agreement instanceof InvestorAgreement) {
            return $this->result(false, ApprovalTriggerType::Manual, null, null, 'No investor agreement is linked.');
        }

        if ($requestType === ApprovalRequestType::FundingRelease) {
            $rule = $this->matchingRule($team, $agreement, $requestType, ApprovalTriggerType::FundingRelease, $category, $fundingPhase, $farmType);

            return $this->result(true, ApprovalTriggerType::FundingRelease, null, $rule, 'Funding releases require investor approval.');
        }

        if ($requestType === ApprovalRequestType::PlanChange) {
            $rule = $this->matchingRule($team, $agreement, $requestType, ApprovalTriggerType::PlanChange, $category, $fundingPhase, $farmType);

            return $this->result(true, ApprovalTriggerType::PlanChange, null, $rule, 'Material plan changes require investor approval.');
        }

        if ($requestType === ApprovalRequestType::BudgetOverrun) {
            $rule = $this->matchingRule($team, $agreement, $requestType, ApprovalTriggerType::BudgetOverrun, $category, $fundingPhase, $farmType);

            return $this->result(true, ApprovalTriggerType::BudgetOverrun, null, $rule, 'This expense creates a material budget overrun.');
        }

        if ($category?->requires_investor_approval) {
            $rule = $this->matchingRule($team, $agreement, $requestType, ApprovalTriggerType::Category, $category, $fundingPhase, $farmType);

            return $this->result(true, ApprovalTriggerType::Category, null, $rule, 'This expense category requires investor approval.');
        }

        $settings = $this->resolveInvestorApprovalSettings->handle($team);
        $thresholdMinor = $settings['expenseThresholdMinor'];

        if ($amountMinor >= $thresholdMinor) {
            $rule = $this->matchingRule($team, $agreement, $requestType, ApprovalTriggerType::Threshold, $category, $fundingPhase, $farmType);

            return $this->result(true, ApprovalTriggerType::Threshold, $thresholdMinor, $rule, 'The amount meets the investor approval threshold.');
        }

        return $this->result(false, ApprovalTriggerType::Manual, $thresholdMinor, null, 'This record is below the configured investor approval threshold.');
    }

    private function matchingRule(
        Team $team,
        InvestorAgreement $agreement,
        ApprovalRequestType $requestType,
        ApprovalTriggerType $triggerType,
        ?ExpenseCategory $category,
        ?FundingPhase $fundingPhase,
        ?string $farmType,
    ): ?ApprovalRule {
        return ApprovalRule::query()
            ->where('team_id', $team->id)
            ->where('request_type', $requestType->value)
            ->where('trigger_type', $triggerType->value)
            ->where('is_active', true)
            ->where(function ($query) use ($agreement): void {
                $query->whereNull('investor_agreement_id')
                    ->orWhere('investor_agreement_id', $agreement->id);
            })
            ->where(function ($query) use ($category): void {
                $query->whereNull('expense_category_id')
                    ->when($category, fn ($query) => $query->orWhere('expense_category_id', $category->id));
            })
            ->where(function ($query) use ($fundingPhase): void {
                $query->whereNull('funding_phase_id')
                    ->when($fundingPhase, fn ($query) => $query->orWhere('funding_phase_id', $fundingPhase->id));
            })
            ->where(function ($query) use ($farmType): void {
                $query->whereNull('farm_type')
                    ->when($farmType, fn ($query) => $query->orWhere('farm_type', $farmType));
            })
            ->latest()
            ->first();
    }

    /**
     * @return array{required: bool, triggerType: ApprovalTriggerType, thresholdAmountMinor: int|null, rule: ApprovalRule|null, reason: string}
     */
    private function result(bool $required, ApprovalTriggerType $triggerType, ?int $thresholdAmountMinor, ?ApprovalRule $rule, string $reason): array
    {
        return [
            'required' => $required,
            'triggerType' => $triggerType,
            'thresholdAmountMinor' => $thresholdAmountMinor,
            'rule' => $rule,
            'reason' => $reason,
        ];
    }
}
