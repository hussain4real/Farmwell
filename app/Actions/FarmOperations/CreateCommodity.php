<?php

namespace App\Actions\FarmOperations;

use App\Actions\Audit\RecordAuditEvent;
use App\Models\Commodity;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateCommodity
{
    public function __construct(private RecordAuditEvent $recordAuditEvent)
    {
        //
    }

    /**
     * Create a team-scoped commodity and audit the setup.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Team $team, User $actor, array $attributes): Commodity
    {
        return DB::transaction(function () use ($team, $actor, $attributes) {
            $commodity = Commodity::create([
                ...$attributes,
                'team_id' => $team->id,
            ]);

            $this->recordAuditEvent->handle(
                team: $team,
                actor: $actor,
                action: 'commodity.created',
                subject: $commodity,
                newValues: $commodity->only(['name', 'farm_type', 'measurement_unit']),
            );

            return $commodity;
        });
    }
}
