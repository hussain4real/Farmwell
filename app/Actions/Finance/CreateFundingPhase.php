<?php

namespace App\Actions\Finance;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\FundingPhaseStatus;
use App\Models\FundingPhase;
use App\Models\Team;
use App\Models\User;

class CreateFundingPhase
{
    public function __construct(
        private RecordAuditEvent $recordAuditEvent,
        private ResolveTeamFinanceCurrency $resolveTeamFinanceCurrency,
    ) {
        //
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Team $team, User $actor, array $attributes): FundingPhase
    {
        $phase = FundingPhase::create([
            ...$attributes,
            'team_id' => $team->id,
            'created_by_id' => $actor->id,
            'status' => FundingPhaseStatus::tryFrom((string) ($attributes['status'] ?? FundingPhaseStatus::Draft->value)) ?? FundingPhaseStatus::Draft,
            'currency' => $this->resolveTeamFinanceCurrency->handle($team),
        ]);

        $this->recordAuditEvent->handle(
            team: $team,
            actor: $actor,
            action: 'funding_phase.created',
            subject: $phase,
            newValues: [
                'farm_id' => $phase->farm_id,
                'production_cycle_id' => $phase->production_cycle_id,
                'budget_id' => $phase->budget_id,
                'name' => $phase->name,
                'status' => $phase->status->value,
                'planned_amount_minor' => $phase->planned_amount_minor,
                'requested_amount_minor' => $phase->requested_amount_minor,
                'approved_amount_minor' => $phase->approved_amount_minor,
                'externally_released_amount_minor' => $phase->externally_released_amount_minor,
                'currency' => $phase->currency,
            ],
        );

        return $phase;
    }
}
