<?php

namespace App\Actions\FarmOperations;

use App\Actions\Audit\RecordAuditEvent;
use App\Models\Farm;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateFarm
{
    public function __construct(private RecordAuditEvent $recordAuditEvent)
    {
        //
    }

    /**
     * Update a team-scoped farm and record an audit event.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Team $team, Farm $farm, User $actor, array $attributes): Farm
    {
        return DB::transaction(function () use ($team, $farm, $actor, $attributes) {
            $oldValues = $farm->only(['name', 'farm_type', 'location', 'status', 'contact_name', 'contact_phone']);
            $oldValues['farm_type'] = $farm->farm_type->value;

            $farm->update($attributes);
            $farm->refresh();

            $newValues = $farm->only(['name', 'farm_type', 'location', 'status', 'contact_name', 'contact_phone']);
            $newValues['farm_type'] = $farm->farm_type->value;

            $this->recordAuditEvent->handle(
                team: $team,
                actor: $actor,
                action: 'farm.updated',
                subject: $farm,
                oldValues: $oldValues,
                newValues: $newValues,
            );

            return $farm;
        });
    }
}
