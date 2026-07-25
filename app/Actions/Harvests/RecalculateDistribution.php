<?php

namespace App\Actions\Harvests;

use App\Actions\Audit\RecordAuditEvent;
use App\Actions\Investors\CreateApprovalRequest;
use App\Actions\Investors\DetermineInvestorApprovalRequirement;
use App\Enums\ApprovalRequestType;
use App\Enums\DistributionStatus;
use App\Enums\HarvestRecordStatus;
use App\Enums\InvestorVisibilityStatus;
use App\Models\DistributionRecord;
use App\Models\InvestorAgreement;
use App\Models\SaleRecord;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RecalculateDistribution
{
    public function __construct(
        private CalculateCapitalRecoveryDistribution $calculateCapitalRecoveryDistribution,
        private CreateApprovalRequest $createApprovalRequest,
        private DetermineInvestorApprovalRequirement $determineInvestorApprovalRequirement,
        private RecordAuditEvent $recordAuditEvent,
    ) {
        //
    }

    public function handle(Team $team, SaleRecord $saleRecord, User $actor): ?DistributionRecord
    {
        $agreement = $saleRecord->investorAgreement;

        if (! $agreement) {
            return null;
        }

        return DB::transaction(function () use ($team, $saleRecord, $actor, $agreement): DistributionRecord {
            $lockedAgreement = InvestorAgreement::query()
                ->where('team_id', $team->id)
                ->whereKey($agreement->id)
                ->lockForUpdate()
                ->firstOrFail();

            $calculation = $this->calculateCapitalRecoveryDistribution->handle($lockedAgreement, $saleRecord);
            $status = $saleRecord->harvestRecord->status === HarvestRecordStatus::Loss
                && $calculation['unrecovered_capital_minor'] > 0
                ? DistributionStatus::LossRecorded
                : DistributionStatus::PendingAcknowledgement;

            $distribution = DistributionRecord::query()->updateOrCreate(
                [
                    'investor_agreement_id' => $lockedAgreement->id,
                    'sale_record_id' => $saleRecord->id,
                ],
                [
                    ...$calculation,
                    'team_id' => $team->id,
                    'farm_id' => $saleRecord->farm_id,
                    'production_cycle_id' => $saleRecord->production_cycle_id,
                    'status' => $status,
                    'investor_visibility_status' => InvestorVisibilityStatus::PendingApproval,
                    'calculated_at' => now(),
                ],
            );

            $requirement = $this->determineInvestorApprovalRequirement->handle(
                team: $team,
                agreement: $lockedAgreement,
                requestType: ApprovalRequestType::DistributionAcknowledgement,
                amountMinor: $distribution->capital_recovered_minor + $distribution->investor_share_minor,
                farmType: $saleRecord->farm->farm_type->value,
            );

            $approvalRequest = $this->createApprovalRequest->handle(
                team: $team,
                agreement: $lockedAgreement,
                actor: $actor,
                subject: $distribution,
                requestType: ApprovalRequestType::DistributionAcknowledgement,
                triggerType: $requirement['triggerType'],
                requestedAmountMinor: $distribution->capital_recovered_minor + $distribution->investor_share_minor,
                currency: $distribution->currency,
                rule: $requirement['rule'],
                thresholdAmountMinor: $requirement['thresholdAmountMinor'],
                comment: $requirement['reason'],
            );

            $distribution->forceFill([
                'approval_request_id' => $approvalRequest->id,
            ])->save();

            $this->recordAuditEvent->handle(
                team: $team,
                actor: $actor,
                action: 'distribution.calculated',
                subject: $distribution,
                newValues: [
                    'sale_record_id' => $saleRecord->id,
                    'investor_agreement_id' => $lockedAgreement->id,
                    'capital_recovered_minor' => $distribution->capital_recovered_minor,
                    'unrecovered_capital_minor' => $distribution->unrecovered_capital_minor,
                    'net_profit_minor' => $distribution->net_profit_minor,
                    'investor_share_minor' => $distribution->investor_share_minor,
                    'farm_share_minor' => $distribution->farm_share_minor,
                    'status' => $distribution->status->value,
                ],
            );

            return $distribution->refresh();
        });
    }
}
