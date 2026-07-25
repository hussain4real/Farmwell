<?php

namespace App\Actions\FarmOperations;

use App\Actions\Audit\RecordAuditEvent;
use App\Models\Farm;
use App\Models\ProductionCycle;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateProductionCycle
{
    public function __construct(private RecordAuditEvent $recordAuditEvent)
    {
        //
    }

    /**
     * Create a production cycle with assigned units and commodity mix.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, int>  $productionUnitIds
     * @param  array<int, array{id: int, role: string, expected_output_quantity?: mixed, expected_output_unit?: mixed, notes?: mixed}>  $commodities
     */
    public function handle(
        Team $team,
        Farm $farm,
        User $actor,
        array $attributes,
        array $productionUnitIds,
        array $commodities,
    ): ProductionCycle {
        return DB::transaction(function () use ($team, $farm, $actor, $attributes, $productionUnitIds, $commodities) {
            $productionCycle = ProductionCycle::create([
                ...$attributes,
                'team_id' => $team->id,
                'farm_id' => $farm->id,
            ]);

            $productionCycle->productionUnits()->sync($productionUnitIds);
            $productionCycle->commodities()->sync($this->commoditySyncPayload($commodities));

            $this->recordAuditEvent->handle(
                team: $team,
                actor: $actor,
                action: 'production_cycle.created',
                subject: $productionCycle,
                newValues: [
                    ...$productionCycle->only(['farm_id', 'name', 'season', 'farm_type', 'status', 'plan_version']),
                    'production_unit_ids' => $productionUnitIds,
                    'commodities' => $commodities,
                ],
            );

            return $productionCycle;
        });
    }

    /**
     * Build the pivot sync payload for cycle commodities.
     *
     * @param  array<int, array{id: int, role: string, expected_output_quantity?: mixed, expected_output_unit?: mixed, notes?: mixed}>  $commodities
     * @return array<int, array{role: string, expected_output_quantity: mixed, expected_output_unit: mixed, notes: mixed}>
     */
    private function commoditySyncPayload(array $commodities): array
    {
        $payload = [];

        foreach ($commodities as $commodity) {
            $payload[$commodity['id']] = [
                'role' => $commodity['role'],
                'expected_output_quantity' => $commodity['expected_output_quantity'] ?? null,
                'expected_output_unit' => $commodity['expected_output_unit'] ?? null,
                'notes' => $commodity['notes'] ?? null,
            ];
        }

        return $payload;
    }
}
