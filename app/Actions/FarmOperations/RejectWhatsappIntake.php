<?php

namespace App\Actions\FarmOperations;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\WhatsappIntakeStatus;
use App\Models\Team;
use App\Models\User;
use App\Models\WhatsappIntake;
use Illuminate\Support\Facades\DB;

class RejectWhatsappIntake
{
    public function __construct(private RecordAuditEvent $recordAuditEvent)
    {
        //
    }

    public function handle(Team $team, WhatsappIntake $intake, User $reviewer, string $reason): WhatsappIntake
    {
        return DB::transaction(function () use ($team, $intake, $reviewer, $reason) {
            $intake->forceFill([
                'review_status' => WhatsappIntakeStatus::Rejected,
                'reviewer_id' => $reviewer->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ])->save();

            $this->recordAuditEvent->handle(
                team: $team,
                actor: $reviewer,
                action: 'whatsapp_intake.rejected',
                subject: $intake,
                newValues: $intake->only(['review_status', 'reviewer_id', 'reviewed_at', 'rejection_reason']),
                reason: $reason,
            );

            return $intake;
        });
    }
}
