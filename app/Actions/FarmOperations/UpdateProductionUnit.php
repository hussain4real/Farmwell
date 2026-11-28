<?php

namespace App\Actions\FarmOperations;

use App\Actions\Audit\RecordAuditEvent;
use App\Models\ProductionUnit;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateProductionUnit
{
    public function __construct(private RecordAuditEvent $recordAuditEvent)
    {
        //
    }

    /**
     * Update a production unit and record an audit event.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Team $team, ProductionUnit $productionUnit, User $actor, array $attributes): ProductionUnit
    {
        return DB::transaction(function () use ($team, $productionUnit, $actor, $attributes) {
            $oldValues = $productionUnit->only(['name', 'unit_type', 'size', 'size_unit', 'capacity', 'status', 'water_source', 'housing_system']);
            $oldValues['unit_type'] = $productionUnit->unit_type->value;

            $productionUnit->update($attributes);
            $productionUnit->refresh();

            $newValues = $productionUnit->only(['name', 'unit_type', 'size', 'size_unit', 'capacity', 'status', 'water_source', 'housing_system']);
            $newValues['unit_type'] = $productionUnit->unit_type->value;

            $this->recordAuditEvent->handle(
                team: $team,
                actor: $actor,
                action: 'production_unit.updated',
                subject: $productionUnit,
                oldValues: $oldValues,
                newValues: $newValues,
            );

            return $productionUnit;
        });
    }
}
