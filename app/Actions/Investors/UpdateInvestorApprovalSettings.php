<?php

namespace App\Actions\Investors;

use App\Actions\Audit\RecordAuditEvent;
use App\Actions\Teams\UpdateTeamSetting;
use App\Enums\ApprovalRequestType;
use App\Enums\ApprovalTriggerType;
use App\Models\ApprovalRule;
use App\Models\Team;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class UpdateInvestorApprovalSettings
{
    public function __construct(
        private UpdateTeamSetting $updateTeamSetting,
        private RecordAuditEvent $recordAuditEvent,
    ) {
        //
    }

    public function handle(Team $team, User $actor, string $currency, int $thresholdAmountMinor, ?string $reason = null): ApprovalRule
    {
        return DB::transaction(function () use ($team, $actor, $currency, $thresholdAmountMinor, $reason): ApprovalRule {
            $currency = Money::normalizeCurrency($currency);

            $this->updateTeamSetting->handle(
                team: $team,
                actor: $actor,
                settingGroup: 'approvals',
                settingKey: 'investor_expense_threshold',
                value: [
                    'currency' => $currency,
                    'threshold_amount_minor' => $thresholdAmountMinor,
                ],
                reason: $reason,
            );

            $rule = ApprovalRule::query()
                ->where('team_id', $team->id)
                ->where('request_type', ApprovalRequestType::Expense->value)
                ->where('trigger_type', ApprovalTriggerType::Threshold->value)
                ->whereNull('investor_agreement_id')
                ->whereNull('expense_category_id')
                ->first();

            $oldValues = $rule?->only(['threshold_amount_minor', 'currency', 'is_active']) ?? [];

            $rule ??= new ApprovalRule([
                'team_id' => $team->id,
                'name' => 'Default investor expense threshold',
                'request_type' => ApprovalRequestType::Expense,
                'trigger_type' => ApprovalTriggerType::Threshold,
                'created_by_id' => $actor->id,
            ]);

            $rule->fill([
                'threshold_amount_minor' => $thresholdAmountMinor,
                'currency' => $currency,
                'is_active' => true,
            ])->save();

            $this->recordAuditEvent->handle(
                team: $team,
                actor: $actor,
                action: 'approval_rule.updated',
                subject: $rule,
                oldValues: $oldValues,
                newValues: [
                    'threshold_amount_minor' => $thresholdAmountMinor,
                    'currency' => $currency,
                    'is_active' => true,
                ],
                reason: $reason,
            );

            return $rule->refresh();
        });
    }
}
