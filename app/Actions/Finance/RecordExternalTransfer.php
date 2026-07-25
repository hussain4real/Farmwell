<?php

namespace App\Actions\Finance;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\ExternalTransferDirection;
use App\Enums\ExternalTransferStatus;
use App\Models\ExternalTransfer;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class RecordExternalTransfer
{
    public function __construct(
        private AttachFinanceEvidence $attachFinanceEvidence,
        private RecordAuditEvent $recordAuditEvent,
        private ResolveTeamFinanceCurrency $resolveTeamFinanceCurrency,
    ) {
        //
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Team $team, User $actor, array $attributes, ?UploadedFile $proof = null, ?string $caption = null): ExternalTransfer
    {
        return DB::transaction(function () use ($team, $actor, $attributes, $proof, $caption) {
            $status = ExternalTransferStatus::tryFrom((string) ($attributes['status'] ?? ExternalTransferStatus::Recorded->value)) ?? ExternalTransferStatus::Recorded;

            if ($proof instanceof UploadedFile && $status === ExternalTransferStatus::Recorded) {
                $status = ExternalTransferStatus::ProofAttached;
            }

            $transfer = ExternalTransfer::create([
                ...$attributes,
                'team_id' => $team->id,
                'recorded_by_id' => $actor->id,
                'direction' => ExternalTransferDirection::tryFrom((string) $attributes['direction']) ?? ExternalTransferDirection::Incoming,
                'status' => $status,
                'currency' => $this->resolveTeamFinanceCurrency->handle($team),
            ]);

            $this->recordAuditEvent->handle(
                team: $team,
                actor: $actor,
                action: 'external_transfer.recorded',
                subject: $transfer,
                newValues: [
                    'farm_id' => $transfer->farm_id,
                    'production_cycle_id' => $transfer->production_cycle_id,
                    'budget_id' => $transfer->budget_id,
                    'funding_phase_id' => $transfer->funding_phase_id,
                    'expense_id' => $transfer->expense_id,
                    'investor_agreement_id' => $transfer->investor_agreement_id,
                    'direction' => $transfer->direction->value,
                    'amount_minor' => $transfer->amount_minor,
                    'currency' => $transfer->currency,
                    'status' => $transfer->status->value,
                    'investor_visibility_status' => $transfer->investor_visibility_status->value,
                ],
            );

            if ($proof instanceof UploadedFile) {
                $this->attachFinanceEvidence->handle($transfer, [$proof], ExternalTransfer::ProofCollection, $actor, $caption);

                $this->recordAuditEvent->handle(
                    team: $team,
                    actor: $actor,
                    action: 'external_transfer_proof.attached',
                    subject: $transfer,
                    newValues: [
                        'proof_count' => 1,
                    ],
                );
            }

            return $transfer->refresh();
        });
    }
}
