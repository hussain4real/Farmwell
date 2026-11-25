<?php

namespace App\Actions\FarmOperations;

use App\Enums\FarmTaskStatus;
use App\Enums\FarmType;
use App\Enums\HealthEventStatus;
use App\Enums\ProductionCycleStatus;
use App\Models\HealthEvent;
use App\Models\InventoryItem;
use App\Models\Team;

class BuildDailyOperationalReminders
{
    public function __construct(
        private CalculateCycleTelemetry $calculateCycleTelemetry,
    ) {
        //
    }

    /**
     * Build a structured operational reminder digest for a farm team.
     *
     * @return array{
     *     teamName: string,
     *     date: string,
     *     overdueHealthEvents: list<array{id: int, title: string, farmName: string, cycleName: string|null, scheduledOn: string}>,
     *     dueSoonHealthEvents: list<array{id: int, title: string, farmName: string, cycleName: string|null, scheduledOn: string}>,
     *     activeWithdrawals: list<array{id: int, title: string, farmName: string, cycleName: string|null, endsOn: string|null}>,
     *     lowStockItems: list<array{id: int, name: string, farmName: string|null, currentQuantity: string, unit: string}>,
     *     waterQualityAlerts: list<array{cycleId: int, farmId: int, farmName: string, cycleName: string, alerts: list<string>}>,
     *     overdueTasksCount: int,
     *     hasAlerts: bool,
     *     whatsappText: string,
     * }
     */
    public function handle(Team $team): array
    {
        $today = today();
        $horizon = $today->copy()->addDays(3)->toDateString();

        $scheduledEvents = $team->healthEvents()
            ->with(['farm', 'productionCycle'])
            ->where('status', HealthEventStatus::Scheduled->value)
            ->whereDate('scheduled_on', '<=', $horizon)
            ->orderBy('scheduled_on')
            ->get();

        $mapHealthEvent = fn (HealthEvent $event): array => [
            'id' => $event->id,
            'title' => $event->title,
            'farmName' => $event->farm->name,
            'cycleName' => $event->productionCycle?->name,
            'scheduledOn' => $event->scheduled_on->toDateString(),
        ];

        /** @var list<array{id: int, title: string, farmName: string, cycleName: string|null, scheduledOn: string}> $overdueHealthEvents */
        $overdueHealthEvents = $scheduledEvents
            ->filter(fn (HealthEvent $event): bool => $event->scheduled_on->lt($today))
            ->map($mapHealthEvent)
            ->values()
            ->all();

        /** @var list<array{id: int, title: string, farmName: string, cycleName: string|null, scheduledOn: string}> $dueSoonHealthEvents */
        $dueSoonHealthEvents = $scheduledEvents
            ->filter(fn (HealthEvent $event): bool => $event->scheduled_on->gte($today))
            ->map($mapHealthEvent)
            ->values()
            ->all();

        /** @var list<array{id: int, title: string, farmName: string, cycleName: string|null, endsOn: string|null}> $activeWithdrawals */
        $activeWithdrawals = $team->healthEvents()
            ->with(['farm', 'productionCycle'])
            ->where('status', HealthEventStatus::Completed->value)
            ->get()
            ->filter(fn (HealthEvent $event): bool => $event->isUnderWithdrawal())
            ->map(fn (HealthEvent $event): array => [
                'id' => $event->id,
                'title' => $event->title,
                'farmName' => $event->farm->name,
                'cycleName' => $event->productionCycle?->name,
                'endsOn' => $event->withdrawalEndsOn()?->toDateString(),
            ])
            ->values()
            ->all();

        /** @var list<array{id: int, name: string, farmName: string|null, currentQuantity: string, unit: string}> $lowStockItems */
        $lowStockItems = $team->inventoryItems()
            ->with('farm')
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->filter(fn (InventoryItem $item): bool => $item->isLowStock())
            ->map(fn (InventoryItem $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'farmName' => $item->farm?->name,
                'currentQuantity' => (string) $item->current_quantity,
                'unit' => $item->unit,
            ])
            ->values()
            ->all();

        /** @var list<array{cycleId: int, farmId: int, farmName: string, cycleName: string, alerts: list<string>}> $waterQualityAlerts */
        $waterQualityAlerts = [];

        $aquaCycles = $team->productionCycles()
            ->with('farm')
            ->where('farm_type', FarmType::Aquaculture->value)
            ->whereIn('status', [
                ProductionCycleStatus::Active->value,
                ProductionCycleStatus::Planned->value,
            ])
            ->get();

        foreach ($aquaCycles as $cycle) {
            $telemetry = $this->calculateCycleTelemetry->handle($cycle);
            /** @var list<string> $alerts */
            $alerts = $telemetry['aquaculture']['waterAlerts'];

            if ($alerts !== []) {
                $waterQualityAlerts[] = [
                    'cycleId' => $cycle->id,
                    'farmId' => $cycle->farm_id,
                    'farmName' => $cycle->farm->name,
                    'cycleName' => $cycle->name,
                    'alerts' => $alerts,
                ];
            }
        }

        $overdueTasksCount = $team->farmTasks()
            ->whereNotIn('status', [FarmTaskStatus::Completed->value, FarmTaskStatus::Cancelled->value])
            ->whereDate('due_on', '<', $today->toDateString())
            ->count();

        $hasAlerts = $overdueHealthEvents !== []
            || $dueSoonHealthEvents !== []
            || $activeWithdrawals !== []
            || $lowStockItems !== []
            || $waterQualityAlerts !== []
            || $overdueTasksCount > 0;

        $digest = [
            'teamName' => $team->name,
            'date' => $today->toDateString(),
            'overdueHealthEvents' => $overdueHealthEvents,
            'dueSoonHealthEvents' => $dueSoonHealthEvents,
            'activeWithdrawals' => $activeWithdrawals,
            'lowStockItems' => $lowStockItems,
            'waterQualityAlerts' => $waterQualityAlerts,
            'overdueTasksCount' => $overdueTasksCount,
            'hasAlerts' => $hasAlerts,
        ];

        return [
            ...$digest,
            'whatsappText' => $this->formatWhatsappText($digest),
        ];
    }

    /**
     * @param  array{
     *     teamName: string,
     *     date: string,
     *     overdueHealthEvents: list<array{id: int, title: string, farmName: string, cycleName: string|null, scheduledOn: string}>,
     *     dueSoonHealthEvents: list<array{id: int, title: string, farmName: string, cycleName: string|null, scheduledOn: string}>,
     *     activeWithdrawals: list<array{id: int, title: string, farmName: string, cycleName: string|null, endsOn: string|null}>,
     *     lowStockItems: list<array{id: int, name: string, farmName: string|null, currentQuantity: string, unit: string}>,
     *     waterQualityAlerts: list<array{cycleId: int, farmId: int, farmName: string, cycleName: string, alerts: list<string>}>,
     *     overdueTasksCount: int,
     *     hasAlerts: bool,
     * }  $digest
     */
    private function formatWhatsappText(array $digest): string
    {
        $lines = [sprintf('Farmwell Daily Brief (%s) — %s', $digest['date'], $digest['teamName'])];

        foreach ($digest['waterQualityAlerts'] as $water) {
            $lines[] = sprintf('• Water Alert [%s / %s]: %s', $water['farmName'], $water['cycleName'], implode('; ', $water['alerts']));
        }

        foreach ($digest['overdueHealthEvents'] as $event) {
            $lines[] = sprintf('• Overdue Health [%s]: %s (due %s)', $event['farmName'], $event['title'], $event['scheduledOn']);
        }

        foreach ($digest['dueSoonHealthEvents'] as $event) {
            $lines[] = sprintf('• Upcoming Health [%s]: %s (%s)', $event['farmName'], $event['title'], $event['scheduledOn']);
        }

        foreach ($digest['activeWithdrawals'] as $withdrawal) {
            $lines[] = sprintf('• Withdrawal Hold [%s]: %s until %s', $withdrawal['farmName'], $withdrawal['title'], $withdrawal['endsOn'] ?? 'TBD');
        }

        foreach ($digest['lowStockItems'] as $item) {
            $lines[] = sprintf('• Low Stock: %s (%s %s left)', $item['name'], $item['currentQuantity'], $item['unit']);
        }

        if ($digest['overdueTasksCount'] > 0) {
            $lines[] = sprintf('• Overdue Tasks: %d', $digest['overdueTasksCount']);
        }

        return implode("\n", $lines);
    }
}
