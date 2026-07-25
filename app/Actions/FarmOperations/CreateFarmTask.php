<?php

namespace App\Actions\FarmOperations;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\FarmTaskStatus;
use App\Models\FarmTask;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateFarmTask
{
    public function __construct(private RecordAuditEvent $recordAuditEvent)
    {
        //
    }

    /**
     * Create a calendar task for field operations.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Team $team, User $actor, array $attributes): FarmTask
    {
        return DB::transaction(function () use ($team, $actor, $attributes) {
            $task = FarmTask::create([
                ...$attributes,
                'team_id' => $team->id,
                'created_by_id' => $actor->id,
                'status' => $attributes['status'] ?? FarmTaskStatus::Planned->value,
            ]);

            $this->recordAuditEvent->handle(
                team: $team,
                actor: $actor,
                action: 'farm_task.created',
                subject: $task,
                newValues: $task->only([
                    'farm_id',
                    'production_unit_id',
                    'production_cycle_id',
                    'title',
                    'activity_type',
                    'due_on',
                    'status',
                    'status_reason',
                ]),
            );

            return $task;
        });
    }
}
