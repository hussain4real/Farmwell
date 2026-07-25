<?php

namespace App\Actions\Audit;

use App\Models\AuditEvent;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class RecordAuditEvent
{
    /**
     * Record a durable audit event.
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     * @param  array<string, mixed>  $metadata
     */
    public function handle(
        ?Team $team,
        ?User $actor,
        string $action,
        ?Model $subject = null,
        array $oldValues = [],
        array $newValues = [],
        ?string $reason = null,
        array $metadata = [],
    ): AuditEvent {
        return AuditEvent::create([
            'team_id' => $team?->id,
            'actor_id' => $actor?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject ? (int) $subject->getKey() : null,
            'old_values' => $oldValues === [] ? null : $oldValues,
            'new_values' => $newValues === [] ? null : $newValues,
            'metadata' => $metadata === [] ? null : $metadata,
            'reason' => $reason,
        ]);
    }
}
