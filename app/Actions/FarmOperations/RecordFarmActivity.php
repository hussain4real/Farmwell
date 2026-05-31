<?php

namespace App\Actions\FarmOperations;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\FarmActivityStatus;
use App\Models\FarmActivity;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class RecordFarmActivity
{
    public function __construct(
        private RecordAuditEvent $recordAuditEvent,
        private AttachEvidence $attachEvidence,
    ) {
        //
    }

    /**
     * Record a diary activity with optional private evidence.
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
        string $evidenceVisibility = 'private',
    ): FarmActivity {
        return DB::transaction(function () use ($team, $actor, $attributes, $evidence, $evidenceCaption, $evidenceVisibility) {
            $activity = FarmActivity::create([
                ...$attributes,
                'team_id' => $team->id,
                'recorded_by_id' => $actor->id,
                'status' => $attributes['status'] ?? FarmActivityStatus::Planned->value,
            ]);

            $this->attachEvidence->handle(
                model: $activity,
                files: $evidence,
                uploader: $actor,
                caption: $evidenceCaption,
                visibility: $evidenceVisibility,
                capturedOn: $activity->activity_date,
            );

            $this->recordAuditEvent->handle(
                team: $team,
                actor: $actor,
                action: 'farm_activity.recorded',
                subject: $activity,
                newValues: [
                    ...$activity->only([
                        'farm_id',
                        'production_unit_id',
                        'production_cycle_id',
                        'commodity_id',
                        'activity_date',
                        'activity_type',
                        'cost',
                        'next_activity',
                        'status',
                    ]),
                    'evidence_count' => count($evidence),
                ],
            );

            return $activity;
        });
    }
}
