<?php

namespace App\Actions\Finance;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalRequestType;
use App\Enums\FundingPhaseStatus;
use App\Enums\InvestorAgreementStatus;
use App\Models\ApprovalRequest;
use App\Models\FundingPhase;
use App\Models\InvestorAgreement;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordFundingRelease
{
    public function handle(Team $team, User $actor, FundingPhase $phase, int $amountMinor, string $releasedOn): void
    {
        DB::transaction(function () use ($team, $actor, $phase, $amountMinor, $releasedOn): void {
            $phase = FundingPhase::query()->where('team_id', $team->id)->lockForUpdate()->findOrFail($phase->id);
            $agreement = InvestorAgreement::query()->where('team_id', $team->id)->lockForUpdate()->find($phase->investor_agreement_id);
            $approval = ApprovalRequest::query()->where('team_id', $team->id)
                ->where('investor_agreement_id', $phase->investor_agreement_id)
                ->where('subject_type', $phase->getMorphClass())->where('subject_id', $phase->id)
                ->where('request_type', ApprovalRequestType::FundingRelease->value)
                ->where('status', ApprovalRequestStatus::Approved->value)->latest('id')->first();

            if ($amountMinor <= 0 || $agreement === null || ! in_array($agreement->status, [InvestorAgreementStatus::Active, InvestorAgreementStatus::Completed], true)
                || $approval?->decided_by_id !== $agreement->investor_id
                || $phase->status !== FundingPhaseStatus::Approved || $phase->externally_released_amount_minor !== 0
                || $approval === null || $approval->approved_amount_minor === null
                || $amountMinor > $approval->approved_amount_minor) {
                throw ValidationException::withMessages(['amount' => 'The release requires investor approval and must not exceed the approved amount.']);
            }

            $phase->update(['externally_released_amount_minor' => $amountMinor, 'released_on' => $releasedOn, 'status' => FundingPhaseStatus::ReleasedExternally]);
            app(RecordAuditEvent::class)->handle(team: $team, actor: $actor, action: 'funding_phase.released', subject: $phase,
                oldValues: ['externally_released_amount_minor' => 0],
                newValues: ['externally_released_amount_minor' => $amountMinor, 'released_on' => $releasedOn, 'approval_request_id' => $approval->id]);
        });
    }
}
