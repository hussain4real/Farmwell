<?php

namespace App\Actions\FarmOperations;

use App\Actions\Audit\RecordAuditEvent;
use App\Actions\Inventory\RecordInventoryMovement;
use App\Enums\InventoryMovementType;
use App\Models\Farm;
use App\Models\InventoryItem;
use App\Models\ProductionCycle;
use App\Models\ProductionLog;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class RecordProductionLog
{
    public function __construct(
        private RecordAuditEvent $recordAuditEvent,
        private RecordInventoryMovement $recordInventoryMovement,
    ) {
        //
    }

    /**
     * Record a domain-specific daily production log and update live cycle telemetry.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, UploadedFile>  $evidence
     */
    public function handle(
        Team $team,
        Farm $farm,
        User $actor,
        array $attributes,
        ?ProductionCycle $cycle = null,
        array $evidence = [],
        ?string $evidenceCaption = null,
    ): ProductionLog {
        return DB::transaction(function () use ($team, $farm, $actor, $attributes, $cycle, $evidence, $evidenceCaption): ProductionLog {
            $log = ProductionLog::create([
                ...$attributes,
                'team_id' => $team->id,
                'farm_id' => $farm->id,
                'production_cycle_id' => $cycle?->id ?? ($attributes['production_cycle_id'] ?? null),
                'recorded_by_id' => $actor->id,
            ]);

            foreach ($evidence as $file) {
                $log
                    ->addMedia($file)
                    ->withCustomProperties([
                        'caption' => $evidenceCaption,
                        'visibility' => 'private',
                        'captured_on' => $log->log_date->toDateString(),
                        'uploaded_by_id' => $actor->id,
                    ])
                    ->toMediaCollection(ProductionLog::EvidenceCollection, 'farmwell_private');
            }

            $targetCycle = $cycle ?? $log->productionCycle;

            if ($targetCycle !== null) {
                $this->syncCycleState($targetCycle, $log);
            }

            if ($log->inventory_item_id !== null) {
                $item = InventoryItem::query()
                    ->where('team_id', $team->id)
                    ->whereKey($log->inventory_item_id)
                    ->first();

                if ($item !== null) {
                    $deductQuantity = $this->resolveInventoryDeductionQuantity($item, $log);

                    if ($deductQuantity > 0) {
                        $this->recordInventoryMovement->handle($team, $item, $actor, [
                            'farm_id' => $farm->id,
                            'production_cycle_id' => $targetCycle?->id,
                            'production_log_id' => $log->id,
                            'movement_type' => InventoryMovementType::Usage,
                            'quantity' => $deductQuantity,
                            'movement_date' => $log->log_date->toDateString(),
                            'notes' => 'Daily production log #'.$log->id,
                        ]);
                    }
                }
            }

            $this->recordAuditEvent->handle(
                team: $team,
                actor: $actor,
                action: 'production_log.recorded',
                subject: $log,
                newValues: [
                    'farm_id' => $log->farm_id,
                    'production_cycle_id' => $log->production_cycle_id,
                    'domain' => $log->domain->value,
                    'log_date' => $log->log_date->toDateString(),
                    'mortality_count' => $log->mortality_count,
                    'feed_used_kg' => $log->feed_used_kg,
                ],
            );

            return $log;
        });
    }

    private function syncCycleState(ProductionCycle $cycle, ProductionLog $log): void
    {
        if ($cycle->initial_stock_count !== null) {
            $losses = (int) $cycle->productionLogs()
                ->selectRaw('COALESCE(SUM(mortality_count + cull_count + graded_out_count), 0) as total_losses')
                ->value('total_losses');

            $cycle->current_stock_count = max(0, $cycle->initial_stock_count - $losses);
        }

        if ($log->growth_stage !== null) {
            $cycle->current_growth_stage = $log->growth_stage;
        }

        if ($cycle->isDirty()) {
            $cycle->save();
        }
    }

    private function resolveInventoryDeductionQuantity(InventoryItem $item, ProductionLog $log): float
    {
        if ($log->feed_used_kg !== null && (float) $log->feed_used_kg > 0) {
            $kg = (float) $log->feed_used_kg;

            if ($item->unit_weight_kg !== null && (float) $item->unit_weight_kg > 0 && str_contains(strtolower($item->unit), 'bag')) {
                return round($kg / (float) $item->unit_weight_kg, 2);
            }

            return round($kg, 2);
        }

        if ($log->input_applied_quantity !== null && (float) $log->input_applied_quantity > 0) {
            return round((float) $log->input_applied_quantity, 2);
        }

        return 0.0;
    }
}
