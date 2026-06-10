<?php

namespace App\Actions\Finance;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\ExternalTransferStatus;
use App\Enums\TransferReconciliationStatus;
use App\Models\ExternalTransfer;
use App\Models\Team;
use App\Models\TransferReconciliation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReconcileExternalTransfer
{
    public function __construct(private RecordAuditEvent $recordAuditEvent)
    {
        //
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Team $team, User $actor, ExternalTransfer $transfer, array $attributes): TransferReconciliation
    {
        return DB::transaction(function () use ($team, $actor, $transfer, $attributes) {
            $status = TransferReconciliationStatus::tryFrom((string) $attributes['status']) ?? TransferReconciliationStatus::Matched;

            $reconciliation = TransferReconciliation::create([
                ...$attributes,
                'team_id' => $team->id,
                'external_transfer_id' => $transfer->id,
                'reconciled_by_id' => $actor->id,
                'status' => $status,
                'currency' => $transfer->currency,
                'reconciled_at' => $attributes['reconciled_at'] ?? now(),
            ]);

            $oldStatus = $transfer->status->value;
            $transfer->forceFill([
                'status' => $status === TransferReconciliationStatus::Matched
                    ? ExternalTransferStatus::Reconciled
                    : ExternalTransferStatus::PendingReconciliation,
            ])->save();

            $this->recordAuditEvent->handle(
                team: $team,
                actor: $actor,
                action: 'external_transfer.reconciled',
                subject: $transfer,
                oldValues: [
                    'status' => $oldStatus,
                ],
                newValues: [
                    'status' => $transfer->status->value,
                    'reconciliation_status' => $reconciliation->status->value,
                    'reconciled_amount_minor' => $reconciliation->reconciled_amount_minor,
                    'currency' => $reconciliation->currency,
                ],
            );

            return $reconciliation;
        });
    }
}
