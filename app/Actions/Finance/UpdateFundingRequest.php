<?php

namespace App\Actions\Finance;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalRequestType;
use App\Enums\FundingPhaseStatus;
use App\Models\ApprovalRequest;
use App\Models\FundingPhase;
use App\Models\Team;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class UpdateFundingRequest
{
    public function __construct(private RecordAuditEvent $audit) {}

    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'requested_amount' => ['sometimes', 'required', 'numeric', 'gt:0', 'regex:/^\d{1,15}(\.\d{1,2})?$/D'],
            'milestone' => ['sometimes', 'nullable', 'string', 'max:255'],
            'expected_on' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:4000'],
        ];
    }

    public static function isEditable(FundingPhase $phase, ?ApprovalRequest $approval): bool
    {
        return $phase->investor_agreement_id !== null
            && $phase->status === FundingPhaseStatus::Requested
            && $phase->approved_amount_minor === 0
            && $phase->externally_released_amount_minor === 0
            && ($approval === null || in_array($approval->status, [ApprovalRequestStatus::Pending, ApprovalRequestStatus::ClarificationRequested], true));
    }

    /** @param array<string, mixed> $changes */
    public function handle(Team $team, User $actor, int $phaseId, array $changes): FundingPhase
    {
        Gate::forUser($actor)->authorize('manageFinance', $team);

        return DB::transaction(function () use ($team, $actor, $phaseId, $changes): FundingPhase {
            $phase = $team->fundingPhases()->lockForUpdate()->findOrFail($phaseId);
            $approval = $phase->approvalRequests()
                ->where('team_id', $team->id)
                ->where('investor_agreement_id', $phase->investor_agreement_id)
                ->where('request_type', ApprovalRequestType::FundingRelease)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! self::isEditable($phase, $approval)) {
                throw ValidationException::withMessages(['requested_amount' => 'Only funding requests awaiting an investor decision can be edited.']);
            }

            if (isset($changes['requested_amount'])) {
                $requestedMinor = Money::toMinorUnit((string) $changes['requested_amount']);
                if ($phase->planned_amount_minor === $phase->requested_amount_minor) {
                    $changes['planned_amount_minor'] = $requestedMinor;
                }
                $changes['requested_amount_minor'] = $requestedMinor;
                unset($changes['requested_amount']);
            }

            $phase->fill($changes);
            $updated = $phase->getDirty();
            if ($updated === []) {
                return $phase;
            }

            $before = array_intersect_key($phase->getRawOriginal(), $updated);
            $phase->save();

            if ($approval !== null) {
                $approval->update(['requested_amount_minor' => $phase->requested_amount_minor]);
            }

            $this->audit->handle(
                team: $team, actor: $actor, action: 'funding_phase.updated', subject: $phase,
                oldValues: $before, newValues: $updated,
                metadata: ['approval_request_id' => $approval?->id],
            );

            return $phase;
        });
    }
}
