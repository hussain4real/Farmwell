<?php

namespace App\Actions\Investors;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\CapitalRecoveryRule;
use App\Enums\InvestorAgreementStatus;
use App\Models\InvestorAgreement;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateInvestorAgreement
{
    public function __construct(private RecordAuditEvent $recordAuditEvent)
    {
        //
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Team $team, InvestorAgreement $agreement, User $actor, array $attributes): InvestorAgreement
    {
        return DB::transaction(function () use ($team, $agreement, $actor, $attributes): InvestorAgreement {
            $agreement = InvestorAgreement::query()
                ->where('team_id', $team->id)
                ->lockForUpdate()
                ->findOrFail($agreement->id);

            $oldValues = [
                'title' => $agreement->title,
                'status' => $agreement->status->value,
                'production_cycle_id' => $agreement->production_cycle_id,
                'currency' => $agreement->currency,
                'amount_committed_minor' => $agreement->amount_committed_minor,
                'amount_funded_minor' => $agreement->amount_funded_minor,
                'capital_recovery_rule' => $agreement->capital_recovery_rule->value,
                'investor_profit_share_percentage' => $agreement->investor_profit_share_percentage,
                'farm_profit_share_percentage' => $agreement->farm_profit_share_percentage,
                'starts_on' => $agreement->starts_on?->toDateString(),
                'ends_on' => $agreement->ends_on?->toDateString(),
            ];

            $agreement->fill([
                ...$attributes,
                'status' => InvestorAgreementStatus::from((string) $attributes['status']),
                'capital_recovery_rule' => CapitalRecoveryRule::from((string) $attributes['capital_recovery_rule']),
            ])->save();

            $this->recordAuditEvent->handle(
                team: $team,
                actor: $actor,
                action: 'investor_agreement.updated',
                subject: $agreement,
                oldValues: $oldValues,
                newValues: [
                    'title' => $agreement->title,
                    'status' => $agreement->status->value,
                    'production_cycle_id' => $agreement->production_cycle_id,
                    'currency' => $agreement->currency,
                    'amount_committed_minor' => $agreement->amount_committed_minor,
                    'amount_funded_minor' => $agreement->amount_funded_minor,
                    'capital_recovery_rule' => $agreement->capital_recovery_rule->value,
                    'investor_profit_share_percentage' => $agreement->investor_profit_share_percentage,
                    'farm_profit_share_percentage' => $agreement->farm_profit_share_percentage,
                    'starts_on' => $agreement->starts_on?->toDateString(),
                    'ends_on' => $agreement->ends_on?->toDateString(),
                ],
            );

            return $agreement->refresh();
        });
    }
}
