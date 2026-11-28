<?php

namespace App\Actions\Finance;

use App\Actions\Investors\CreateApprovalRequest;
use App\Actions\Investors\DetermineInvestorApprovalRequirement;
use App\Enums\ApprovalRequestType;
use App\Enums\FundingPhaseStatus;
use App\Enums\InvestorAgreementStatus;
use App\Models\ApprovalRequest;
use App\Models\FundingPhase;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitFundingRequest
{
    /** @param array<int, UploadedFile> $evidence */
    public function handle(Team $team, User $actor, FundingPhase $phase, array $evidence): void
    {
        DB::transaction(function () use ($team, $actor, $phase, $evidence): void {
            $phase = FundingPhase::query()->where('team_id', $team->id)->lockForUpdate()->findOrFail($phase->id);
            $agreement = $phase->investorAgreement;
            $alreadyRequested = ApprovalRequest::query()->where('team_id', $team->id)
                ->where('subject_type', $phase->getMorphClass())->where('subject_id', $phase->id)
                ->where('request_type', ApprovalRequestType::FundingRelease->value)->exists();
            if ($alreadyRequested || $agreement === null || $agreement->team_id !== $team->id
                || ! in_array($agreement->status, [InvestorAgreementStatus::Active, InvestorAgreementStatus::Completed], true)
                || $phase->status !== FundingPhaseStatus::Requested || $phase->requested_amount_minor <= 0
                || $phase->externally_released_amount_minor !== 0) {
                throw ValidationException::withMessages(['milestone_evidence' => 'This phase cannot be submitted for approval.']);
            }

            app(AttachFinanceEvidence::class)->handle($phase, $evidence, FundingPhase::EvidenceCollection, $actor);
            $requirement = app(DetermineInvestorApprovalRequirement::class)->handle(
                team: $team, agreement: $agreement, requestType: ApprovalRequestType::FundingRelease,
                amountMinor: $phase->requested_amount_minor, fundingPhase: $phase, farmType: $phase->farm->farm_type->value,
            );
            app(CreateApprovalRequest::class)->handle(team: $team, agreement: $agreement, actor: $actor, subject: $phase,
                requestType: ApprovalRequestType::FundingRelease, triggerType: $requirement['triggerType'],
                requestedAmountMinor: $phase->requested_amount_minor, currency: $phase->currency,
                rule: $requirement['rule'], thresholdAmountMinor: $requirement['thresholdAmountMinor'], comment: $requirement['reason']);
        });
    }
}
