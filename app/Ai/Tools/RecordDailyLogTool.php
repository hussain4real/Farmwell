<?php

namespace App\Ai\Tools;

use App\Actions\FarmOperations\RecordProductionLog;
use App\Ai\Tools\Concerns\InteractsWithFarmwellContext;
use App\Enums\ProductionLogDomain;
use App\Models\Farm;
use App\Models\InventoryItem;
use App\Models\ProductionCycle;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class RecordDailyLogTool implements Approvable, Tool
{
    use InteractsWithApprovals;
    use InteractsWithFarmwellContext;

    public function description(): string
    {
        return 'Record a daily field production log (feed, mortality, culls, eggs, weight, water quality, or rainfall) on a farm or production cycle.';
    }

    protected function needsApproval(Request $request): Approval
    {
        $farmId = $request->integer('farm_id');
        $logDate = (string) ($request['log_date'] ?? now()->toDateString());

        return Approval::required("Record daily production log for farm #{$farmId} on {$logDate}");
    }

    public function handle(Request $request): string
    {
        if (! $this->allows('manageFieldWork')) {
            return $this->encodeResult(['error' => 'Not authorized to record production logs.']);
        }

        $logDate = $this->parseDateString($request['log_date'] ?? null);
        if ($logDate === null) {
            return $this->encodeResult(['error' => 'log_date must use YYYY-MM-DD format.']);
        }

        $farm = Farm::query()->where('team_id', $this->team->id)->find($request->integer('farm_id'));
        if ($farm === null) {
            return $this->encodeResult(['error' => 'Farm not found in this workspace.']);
        }

        $cycleId = $request->integer('production_cycle_id');
        $cycle = $cycleId > 0
            ? ProductionCycle::query()->where('team_id', $this->team->id)->where('farm_id', $farm->id)->find($cycleId)
            : null;

        if ($cycleId > 0 && $cycle === null) {
            return $this->encodeResult(['error' => 'Production cycle not found on the selected farm.']);
        }

        $inventoryItemId = $request->integer('inventory_item_id');
        if ($inventoryItemId > 0 && ! InventoryItem::query()->where('team_id', $this->team->id)->whereKey($inventoryItemId)->exists()) {
            return $this->encodeResult(['error' => 'Inventory item not found in this workspace.']);
        }

        $attributes = array_filter([
            'production_cycle_id' => $cycle?->id,
            'inventory_item_id' => $inventoryItemId > 0 ? $inventoryItemId : null,
            'log_date' => $logDate,
            'domain' => ProductionLogDomain::fromFarmType($farm->farm_type)->value,
            'feed_used_kg' => $request['feed_used_kg'] ?? null,
            'mortality_count' => $request['mortality_count'] ?? 0,
            'cull_count' => $request['cull_count'] ?? 0,
            'eggs_collected' => $request['eggs_collected'] ?? null,
            'eggs_damaged' => $request['eggs_damaged'] ?? null,
            'average_weight_g' => $request['average_weight_g'] ?? null,
            'water_ph' => $request['water_ph'] ?? null,
            'water_temp_c' => $request['water_temp_c'] ?? null,
            'dissolved_oxygen_mg_l' => $request['dissolved_oxygen_mg_l'] ?? null,
            'ammonia_mg_l' => $request['ammonia_mg_l'] ?? null,
            'rainfall_mm' => $request['rainfall_mm'] ?? null,
            'notes' => $request->filled('notes') ? trim((string) $request['notes']) : null,
        ], static fn (mixed $value): bool => $value !== null);

        $log = app(RecordProductionLog::class)->handle($this->team, $farm, $this->user, $attributes, $cycle);

        $this->recordAssistantAudit('daily_log_recorded', $log, ['production_log_id' => $log->id]);

        return $this->encodeResult([
            'recorded' => true,
            'production_log_id' => $log->id,
            'farm' => $farm->name,
            'log_date' => $logDate,
            'domain' => $log->domain->value,
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'farm_id' => $schema->integer()->required(),
            'log_date' => $schema->string()->description('YYYY-MM-DD')->required(),
            'production_cycle_id' => $schema->integer(),
            'inventory_item_id' => $schema->integer(),
            'feed_used_kg' => $schema->number(),
            'mortality_count' => $schema->integer()->min(0),
            'cull_count' => $schema->integer()->min(0),
            'eggs_collected' => $schema->integer()->min(0),
            'eggs_damaged' => $schema->integer()->min(0),
            'average_weight_g' => $schema->number(),
            'water_ph' => $schema->number(),
            'water_temp_c' => $schema->number(),
            'dissolved_oxygen_mg_l' => $schema->number(),
            'ammonia_mg_l' => $schema->number(),
            'rainfall_mm' => $schema->number(),
            'notes' => $schema->string(),
        ];
    }
}
