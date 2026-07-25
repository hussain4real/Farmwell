<?php

namespace App\Actions\Finance;

use App\Actions\Audit\RecordAuditEvent;
use App\Actions\Investors\CreateApprovalRequest;
use App\Actions\Investors\DetermineInvestorApprovalRequirement;
use App\Enums\ApprovalRequestType;
use App\Enums\FundingPhaseStatus;
use App\Models\FundingPhase;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateFundingPhase
{
    public function __construct(
        private CreateApprovalRequest $createApprovalRequest,
        private DetermineInvestorApprovalRequirement $determineInvestorApprovalRequirement,
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
        return DB::transaction(function () use ($team, $actor, $attributes): FundingPhase {
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
                    'investor_agreement_id' => $phase->investor_agreement_id,
                    'name' => $phase->name,
                    'status' => $phase->status->value,
                    'investor_visibility_status' => $phase->investor_visibility_status->value,
                    'planned_amount_minor' => $phase->planned_amount_minor,
                    'requested_amount_minor' => $phase->requested_amount_minor,
                    'approved_amount_minor' => $phase->approved_amount_minor,
                    'externally_released_amount_minor' => $phase->externally_released_amount_minor,
                    'currency' => $phase->currency,
                ],
            );

            $agreement = $phase->investorAgreement;
            if ($agreement && $phase->externally_released_amount_minor > 0) {
                $requirement = $this->determineInvestorApprovalRequirement->handle(
                    team: $team,
                    agreement: $agreement,
                    requestType: ApprovalRequestType::FundingRelease,
                    amountMinor: $phase->externally_released_amount_minor,
                    fundingPhase: $phase,
                    farmType: $phase->farm->farm_type->value,
                );

                if ($requirement['required']) {
                    $this->createApprovalRequest->handle(
                        team: $team,
                        agreement: $agreement,
                        actor: $actor,
                        subject: $phase,
                        requestType: ApprovalRequestType::FundingRelease,
                        triggerType: $requirement['triggerType'],
                        requestedAmountMinor: $phase->externally_released_amount_minor,
                        currency: $phase->currency,
                        rule: $requirement['rule'],
                        thresholdAmountMinor: $requirement['thresholdAmountMinor'],
                        comment: $requirement['reason'],
                    );
                }
            }

            return $phase->refresh();
        });
    }
}
