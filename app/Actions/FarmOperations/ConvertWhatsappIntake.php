<?php

namespace App\Actions\FarmOperations;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\WhatsappIntakeStatus;
use App\Models\FarmActivity;
use App\Models\Team;
use App\Models\User;
use App\Models\WhatsappIntake;
use Illuminate\Support\Facades\DB;

class ConvertWhatsappIntake
{
    public function __construct(
        private RecordFarmActivity $recordFarmActivity,
        private RecordAuditEvent $recordAuditEvent,
    ) {
        //
    }

    /**
     * Convert a pending WhatsApp intake into an official diary activity.
     *
     * @param  array<string, mixed>  $activityAttributes
     * @param  array<string, mixed>  $normalizedAttributes
     */
    public function handle(
        Team $team,
        WhatsappIntake $intake,
        User $reviewer,
        array $activityAttributes,
        array $normalizedAttributes,
    ): FarmActivity {
        return DB::transaction(function () use ($team, $intake, $reviewer, $activityAttributes, $normalizedAttributes) {
            $activity = $this->recordFarmActivity->handle(
                team: $team,
                actor: $reviewer,
                attributes: [
                    ...$activityAttributes,
                    'source_type' => 'whatsapp_intake',
                    'source_reference_id' => $intake->id,
                ],
            );

            foreach ($intake->getMedia(WhatsappIntake::EvidenceCollection) as $media) {
                $media->copy($activity, FarmActivity::EvidenceCollection, 'farmwell_private');
            }

            $intake->forceFill([
                ...$normalizedAttributes,
                'review_status' => WhatsappIntakeStatus::Converted,
                'reviewer_id' => $reviewer->id,
                'reviewed_at' => now(),
                'converted_activity_id' => $activity->id,
                'rejection_reason' => null,
            ])->save();

            $this->recordAuditEvent->handle(
                team: $team,
                actor: $reviewer,
                action: 'whatsapp_intake.converted',
                subject: $intake,
                newValues: [
                    'converted_activity_id' => $activity->id,
                    'review_status' => $intake->review_status->value,
                ],
            );

            return $activity;
        });
    }
}
