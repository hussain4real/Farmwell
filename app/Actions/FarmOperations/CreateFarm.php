<?php

namespace App\Actions\FarmOperations;

use App\Actions\Audit\RecordAuditEvent;
use App\Models\Farm;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateFarm
{
    public function __construct(private RecordAuditEvent $recordAuditEvent)
    {
        //
    }

    /**
     * Create a team-scoped farm and audit the setup.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Team $team, User $actor, array $attributes): Farm
    {
        return DB::transaction(function () use ($team, $actor, $attributes) {
            $farm = Farm::create([
                ...$attributes,
                'team_id' => $team->id,
            ]);

            $this->recordAuditEvent->handle(
                team: $team,
                actor: $actor,
                action: 'farm.created',
                subject: $farm,
                newValues: $farm->only(['name', 'farm_type', 'location', 'status']),
            );

            return $farm;
        });
    }
}
