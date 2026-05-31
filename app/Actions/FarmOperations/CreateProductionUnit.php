<?php

namespace App\Actions\FarmOperations;

use App\Actions\Audit\RecordAuditEvent;
use App\Models\Farm;
use App\Models\ProductionUnit;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateProductionUnit
{
    public function __construct(private RecordAuditEvent $recordAuditEvent)
    {
        //
    }

    /**
     * Create a farm production unit and audit the setup.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Team $team, Farm $farm, User $actor, array $attributes): ProductionUnit
    {
        return DB::transaction(function () use ($team, $farm, $actor, $attributes) {
            $productionUnit = ProductionUnit::create([
                ...$attributes,
                'team_id' => $team->id,
                'farm_id' => $farm->id,
            ]);

            $this->recordAuditEvent->handle(
                team: $team,
                actor: $actor,
                action: 'production_unit.created',
                subject: $productionUnit,
                newValues: $productionUnit->only(['farm_id', 'name', 'unit_type', 'size', 'size_unit', 'status']),
            );

            return $productionUnit;
        });
    }
}
