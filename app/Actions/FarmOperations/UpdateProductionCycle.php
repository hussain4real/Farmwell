<?php

namespace App\Actions\FarmOperations;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\ProductionCycleStatus;
use App\Models\ProductionCycle;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateProductionCycle
{
    public function __construct(private RecordAuditEvent $recordAuditEvent)
    {
        //
    }

    /**
     * Update a production cycle and optionally sync its units.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, int>|null  $productionUnitIds
     */
    public function handle(
        Team $team,
        ProductionCycle $productionCycle,
        User $actor,
        array $attributes,
        ?array $productionUnitIds = null,
    ): ProductionCycle {
        return DB::transaction(function () use ($team, $productionCycle, $actor, $attributes, $productionUnitIds) {
            $oldValues = [
                'name' => $productionCycle->name,
                'status' => $productionCycle->status->value,
                'planned_start_on' => $productionCycle->planned_start_on->toDateString(),
                'planned_end_on' => $productionCycle->planned_end_on?->toDateString(),
                'current_growth_stage' => $productionCycle->current_growth_stage?->value,
                'initial_stock_count' => $productionCycle->initial_stock_count,
                'current_stock_count' => $productionCycle->current_stock_count,
            ];

            $nextStatus = isset($attributes['status'])
                ? ProductionCycleStatus::tryFrom((string) $attributes['status'])
                : $productionCycle->status;

            if ($nextStatus === ProductionCycleStatus::Active && $productionCycle->actual_start_on === null && empty($attributes['actual_start_on'])) {
                $attributes['actual_start_on'] = now()->toDateString();
            }

            if (in_array($nextStatus, [ProductionCycleStatus::Completed, ProductionCycleStatus::Closed], true) && $productionCycle->actual_end_on === null && empty($attributes['actual_end_on'])) {
                $attributes['actual_end_on'] = now()->toDateString();
            }

            if (array_key_exists('initial_stock_count', $attributes) && $attributes['initial_stock_count'] !== null && $productionCycle->current_stock_count === null) {
                $attributes['current_stock_count'] = $attributes['initial_stock_count'];
            }

            $productionCycle->update($attributes);

            if ($productionUnitIds !== null) {
                $productionCycle->productionUnits()->sync($productionUnitIds);
            }

            $productionCycle->refresh();

            $this->recordAuditEvent->handle(
                team: $team,
                actor: $actor,
                action: 'production_cycle.updated',
                subject: $productionCycle,
                oldValues: $oldValues,
                newValues: [
                    'name' => $productionCycle->name,
                    'status' => $productionCycle->status->value,
                    'planned_start_on' => $productionCycle->planned_start_on->toDateString(),
                    'planned_end_on' => $productionCycle->planned_end_on?->toDateString(),
                    'current_growth_stage' => $productionCycle->current_growth_stage?->value,
                    'initial_stock_count' => $productionCycle->initial_stock_count,
                    'current_stock_count' => $productionCycle->current_stock_count,
                ],
            );

            return $productionCycle;
        });
    }
}
