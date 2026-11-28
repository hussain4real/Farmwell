<?php

namespace App\Ai\Tools;

use App\Ai\Tools\Concerns\InteractsWithFarmwellContext;
use App\Enums\FarmTaskStatus;
use App\Enums\ProductionCycleStatus;
use App\Models\Farm;
use App\Models\FarmTask;
use App\Models\InventoryItem;
use App\Models\ProductionCycle;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class GetWorkspaceOverviewTool implements Tool
{
    use InteractsWithFarmwellContext;

    public function description(): string
    {
        return 'Get the current workspace overview: farms, active production cycles, latest telemetry, low-stock inventory items, and pending or delayed tasks.';
    }

    public function handle(Request $request): string
    {
        if (! $this->allows('viewFieldWork')) {
            return $this->encodeResult(['error' => 'Not authorized to view farm operations.']);
        }

        $farmId = $request->integer('farm_id');

        $farms = Farm::query()
            ->where('team_id', $this->team->id)
            ->when($farmId > 0, fn ($query) => $query->whereKey($farmId))
            ->orderBy('name')
            ->get(['id', 'name', 'farm_type', 'status', 'location']);

        $cycles = ProductionCycle::query()
            ->where('team_id', $this->team->id)
            ->when($farmId > 0, fn ($query) => $query->where('farm_id', $farmId))
            ->whereIn('status', [
                ProductionCycleStatus::Active->value,
                ProductionCycleStatus::Planned->value,
                ProductionCycleStatus::Delayed->value,
            ])
            ->with('farm:id,name')
            ->latest('id')
            ->limit(15)
            ->get(['id', 'farm_id', 'name', 'status', 'current_stock_count', 'initial_stock_count', 'planned_start_on', 'planned_end_on']);

        $lowStock = InventoryItem::query()
            ->where('team_id', $this->team->id)
            ->where('is_active', true)
            ->when($farmId > 0, fn ($query) => $query->where('farm_id', $farmId))
            ->whereColumn('current_quantity', '<=', 'reorder_level')
            ->orderBy('name')
            ->limit(15)
            ->get(['id', 'farm_id', 'name', 'category', 'current_quantity', 'reorder_level', 'unit']);

        $tasks = FarmTask::query()
            ->where('team_id', $this->team->id)
            ->when($farmId > 0, fn ($query) => $query->where('farm_id', $farmId))
            ->whereIn('status', [
                FarmTaskStatus::Planned->value,
                FarmTaskStatus::InProgress->value,
                FarmTaskStatus::Delayed->value,
                FarmTaskStatus::Blocked->value,
            ])
            ->orderBy('due_on')
            ->limit(15)
            ->get(['id', 'farm_id', 'production_cycle_id', 'title', 'activity_type', 'status', 'due_on']);

        return $this->encodeResult([
            'farms' => $farms->map(fn (Farm $farm): array => [
                'id' => $farm->id,
                'name' => $farm->name,
                'farm_type' => $farm->farm_type->value,
                'status' => $farm->status,
                'location' => $farm->location,
            ])->all(),
            'cycles' => $cycles->map(fn (ProductionCycle $cycle): array => [
                'id' => $cycle->id,
                'farm_id' => $cycle->farm_id,
                'farm_name' => $cycle->farm?->name,
                'name' => $cycle->name,
                'status' => $cycle->status->value,
                'current_stock_count' => $cycle->current_stock_count,
                'initial_stock_count' => $cycle->initial_stock_count,
                'planned_start_on' => $cycle->planned_start_on?->toDateString(),
                'planned_end_on' => $cycle->planned_end_on?->toDateString(),
            ])->all(),
            'low_stock_items' => $lowStock->map(fn (InventoryItem $item): array => [
                'id' => $item->id,
                'farm_id' => $item->farm_id,
                'name' => $item->name,
                'current_quantity' => $item->current_quantity,
                'reorder_level' => $item->reorder_level,
                'unit' => $item->unit,
            ])->all(),
            'open_tasks' => $tasks->map(fn (FarmTask $task): array => [
                'id' => $task->id,
                'farm_id' => $task->farm_id,
                'title' => $task->title,
                'status' => $task->status->value,
                'due_on' => $task->due_on?->toDateString(),
            ])->all(),
        ]);
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'farm_id' => $schema->integer()->description('Optional farm ID to filter workspace data.'),
        ];
    }
}
