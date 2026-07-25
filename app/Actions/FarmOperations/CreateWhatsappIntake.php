<?php

namespace App\Actions\FarmOperations;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\WhatsappIntakeStatus;
use App\Models\Team;
use App\Models\User;
use App\Models\WhatsappIntake;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class CreateWhatsappIntake
{
    public function __construct(
        private RecordAuditEvent $recordAuditEvent,
        private AttachEvidence $attachEvidence,
    ) {
        //
    }

    /**
     * Store a pending WhatsApp intake record for later review.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, UploadedFile>  $evidence
     */
    public function handle(
        Team $team,
        User $actor,
        array $attributes,
        array $evidence = [],
        ?string $evidenceCaption = null,
    ): WhatsappIntake {
        return DB::transaction(function () use ($team, $actor, $attributes, $evidence, $evidenceCaption) {
            $intake = WhatsappIntake::create([
                ...$attributes,
                'team_id' => $team->id,
                'imported_by_id' => $actor->id,
                'review_status' => WhatsappIntakeStatus::Pending->value,
            ]);

            $this->attachEvidence->handle(
                model: $intake,
                files: $evidence,
                uploader: $actor,
                caption: $evidenceCaption,
                visibility: 'private',
                capturedOn: $intake->source_date,
            );

            $this->recordAuditEvent->handle(
                team: $team,
                actor: $actor,
                action: 'whatsapp_intake.created',
                subject: $intake,
                newValues: [
                    ...$intake->only(['farm_id', 'source_sender', 'source_date', 'review_status']),
                    'evidence_count' => count($evidence),
                ],
            );

            return $intake;
        });
    }
}
