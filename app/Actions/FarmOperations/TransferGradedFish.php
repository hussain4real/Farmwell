<?php

namespace App\Actions\FarmOperations;

use App\Actions\Audit\RecordAuditEvent;
use App\Enums\ProductionLogDomain;
use App\Models\ProductionCycle;
use App\Models\ProductionLog;
use App\Models\ProductionUnit;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferGradedFish
{
    public function __construct(private RecordAuditEvent $recordAuditEvent)
    {
        //
    }

    /**
     * Grade and move sorted fish from a source cycle/pond into a target cycle and pond on the same farm.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function handle(
        Team $team,
        ProductionCycle $sourceCycle,
        ProductionUnit $targetUnit,
        ProductionCycle $targetCycle,
        User $actor,
        array $attributes,
    ): ProductionLog {
        return DB::transaction(function () use ($team, $sourceCycle, $targetUnit, $targetCycle, $actor, $attributes): ProductionLog {
            $count = (int) $attributes['count'];
            $available = $sourceCycle->current_stock_count ?? $sourceCycle->initial_stock_count ?? 0;

            if ($count > $available) {
                throw ValidationException::withMessages([
                    'count' => __('Transfer count (:count) exceeds current stock (:available).', [
                        'count' => $count,
                        'available' => $available,
                    ]),
                ]);
            }

            $log = ProductionLog::create([
                'team_id' => $team->id,
                'farm_id' => $sourceCycle->farm_id,
                'production_cycle_id' => $sourceCycle->id,
                'production_unit_id' => $attributes['source_unit_id'] ?? null,
                'recorded_by_id' => $actor->id,
                'log_date' => $attributes['transfer_date'] ?? now()->toDateString(),
                'domain' => ProductionLogDomain::Aquaculture,
                'graded_out_count' => $count,
                'average_weight_g' => $attributes['average_weight_g'] ?? null,
                'notes' => $attributes['notes'] ?? sprintf('Graded and transferred %d fish to %s (%s).', $count, $targetCycle->name, $targetUnit->name),
            ]);

            if ($sourceCycle->initial_stock_count !== null) {
                $losses = (int) $sourceCycle->productionLogs()
                    ->selectRaw('COALESCE(SUM(mortality_count + cull_count + graded_out_count), 0) as total_losses')
                    ->value('total_losses');

                $sourceCycle->update([
                    'current_stock_count' => max(0, $sourceCycle->initial_stock_count - $losses),
                ]);
            }

            $targetCycle->productionUnits()->syncWithoutDetaching([$targetUnit->id]);
            $targetCycle->update([
                'initial_stock_count' => ($targetCycle->initial_stock_count ?? 0) + $count,
                'current_stock_count' => ($targetCycle->current_stock_count ?? 0) + $count,
            ]);

            $this->recordAuditEvent->handle(
                team: $team,
                actor: $actor,
                action: 'aquaculture.graded_transfer',
                subject: $sourceCycle,
                newValues: [
                    'source_cycle_id' => $sourceCycle->id,
                    'target_cycle_id' => $targetCycle->id,
                    'target_unit_id' => $targetUnit->id,
                    'count' => $count,
                ],
            );

            return $log;
        });
    }
}
