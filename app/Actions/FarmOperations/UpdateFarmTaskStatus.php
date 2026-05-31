<?php

namespace App\Actions\FarmOperations;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\FarmTaskStatus;
use App\Models\FarmTask;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateFarmTaskStatus
{
    public function __construct(private RecordAuditEvent $recordAuditEvent)
    {
        //
    }

    /**
     * Update a task status and preserve delay/cancellation reasons.
     */
    public function handle(Team $team, FarmTask $task, User $actor, FarmTaskStatus $status, ?string $reason): FarmTask
    {
        return DB::transaction(function () use ($team, $task, $actor, $status, $reason) {
            $oldValues = $task->only(['status', 'status_reason', 'completed_at', 'completed_by_id']);

            $task->forceFill([
                'status' => $status,
                'status_reason' => $reason,
                'completed_at' => $status === FarmTaskStatus::Completed ? now() : null,
                'completed_by_id' => $status === FarmTaskStatus::Completed ? $actor->id : null,
            ])->save();

            $this->recordAuditEvent->handle(
                team: $team,
                actor: $actor,
                action: 'farm_task.status_updated',
                subject: $task,
                oldValues: $oldValues,
                newValues: $task->only(['status', 'status_reason', 'completed_at', 'completed_by_id']),
                reason: $reason,
            );

            return $task;
        });
    }
}
