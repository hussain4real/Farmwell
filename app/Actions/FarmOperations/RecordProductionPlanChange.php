<?php

namespace App\Actions\FarmOperations;

use App\Actions\Audit\RecordAuditEvent;
use App\Actions\Investors\CreateApprovalRequest;
use App\Actions\Investors\DetermineInvestorApprovalRequirement;
use App\Enums\ApprovalRequestType;
use App\Models\InvestorAgreement;
use App\Models\ProductionCycle;
use App\Models\ProductionPlanChange;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RecordProductionPlanChange
{
    public function __construct(
        private RecordAuditEvent $recordAuditEvent,
        private DetermineInvestorApprovalRequirement $determineInvestorApprovalRequirement,
        private CreateApprovalRequest $createApprovalRequest,
    ) {
        //
    }

    /**
     * Record a reasoned plan change and advance the cycle plan version.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Team $team, ProductionCycle $productionCycle, User $actor, array $attributes): ProductionPlanChange
    {
        return DB::transaction(function () use ($team, $productionCycle, $actor, $attributes) {
            $productionCycle = ProductionCycle::query()
                ->with('farm')
                ->whereKey($productionCycle->id)
                ->lockForUpdate()
                ->firstOrFail();

            $oldValues = [
                'plan_version' => $productionCycle->plan_version,
            ];

            /** @var array<string, mixed> $requestOldValues */
            $requestOldValues = $attributes['old_values'] ?? [];
            /** @var array<string, mixed> $requestNewValues */
            $requestNewValues = $attributes['new_values'] ?? [];

            $productionCycle->forceFill([
                'plan_version' => $productionCycle->plan_version + 1,
            ])->save();

            $newValues = [
                'plan_version' => $productionCycle->plan_version,
                ...$requestNewValues,
            ];

            $planChange = ProductionPlanChange::create([
                ...$attributes,
                'team_id' => $team->id,
                'production_cycle_id' => $productionCycle->id,
                'actor_id' => $actor->id,
                'old_values' => [
                    ...$oldValues,
                    ...$requestOldValues,
                ],
                'new_values' => $newValues,
            ]);

            $this->recordAuditEvent->handle(
                team: $team,
                actor: $actor,
                action: 'production_plan_change.recorded',
                subject: $planChange,
                oldValues: $planChange->old_values ?? [],
                newValues: [
                    'production_cycle_id' => $productionCycle->id,
                    'change_type' => $planChange->change_type->value,
                    'impact' => $planChange->impact,
                    ...($planChange->new_values ?? []),
                ],
                reason: $planChange->reason,
            );

            $agreement = $planChange->investorAgreement;

            if ($agreement instanceof InvestorAgreement) {
                $requirement = $this->determineInvestorApprovalRequirement->handle(
                    team: $team,
                    agreement: $agreement,
                    requestType: ApprovalRequestType::PlanChange,
                    farmType: $productionCycle->farm?->farm_type->value,
                );

                $this->createApprovalRequest->handle(
                    team: $team,
                    agreement: $agreement,
                    actor: $actor,
                    subject: $planChange,
                    requestType: ApprovalRequestType::PlanChange,
                    triggerType: $requirement['triggerType'],
                    requestedAmountMinor: 0,
                    currency: $agreement->currency,
                    rule: $requirement['rule'],
                    thresholdAmountMinor: $requirement['thresholdAmountMinor'],
                    comment: $requirement['reason'],
                );
            }

            return $planChange;
        });
    }
}
