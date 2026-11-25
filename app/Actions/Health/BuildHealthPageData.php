<?php

namespace App\Actions\Health;

use App\Actions\Finance\ResolveTeamFinanceCurrency;
use App\Enums\HealthEventStatus;
use App\Enums\HealthEventType;
use App\Models\Farm;
use App\Models\HealthEvent;
use App\Models\InventoryItem;
use App\Models\ProductionCycle;
use App\Models\ProductionUnit;
use App\Models\Team;
use App\Models\User;
use App\Support\HealthScheduleTemplates;
use App\Support\Money;

class BuildHealthPageData
{
    public function __construct(private ResolveTeamFinanceCurrency $resolveTeamFinanceCurrency)
    {
        //
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(Team $team, User $user): array
    {
        $events = $team->healthEvents()
            ->with(['farm', 'productionCycle', 'productionUnit', 'inventoryItem', 'recordedBy'])
            ->orderByRaw("CASE WHEN status = 'scheduled' THEN 0 ELSE 1 END")
            ->orderBy('scheduled_on')
            ->limit(60)
            ->get();

        $activeWithdrawals = $events->filter(fn (HealthEvent $event): bool => $event->isUnderWithdrawal())->values();
        $overdueCount = $events->filter(fn (HealthEvent $event): bool => $event->isOverdue())->count();
        $scheduledCount = $events->where('status', HealthEventStatus::Scheduled)->count();
        $completedCount = $events->where('status', HealthEventStatus::Completed)->count();

        return [
            'permissions' => $user->toTeamPermissions($team),
            'currency' => $this->resolveTeamFinanceCurrency->handle($team),
            'summary' => [
                'scheduled' => $scheduledCount,
                'overdue' => $overdueCount,
                'completed' => $completedCount,
                'activeWithdrawals' => $activeWithdrawals->count(),
            ],
            'events' => $events->map(fn (HealthEvent $event): array => $this->eventPayload($event))->values(),
            'activeWithdrawals' => $activeWithdrawals->map(fn (HealthEvent $event): array => $this->eventPayload($event))->values(),
            'farms' => $team->farms()
                ->with(['productionUnits', 'productionCycles' => fn ($query) => $query->latest()])
                ->orderBy('name')
                ->get()
                ->map(fn (Farm $farm) => [
                    'id' => $farm->id,
                    'name' => $farm->name,
                    'farmType' => $farm->farm_type->value,
                    'productionUnits' => $farm->productionUnits->map(fn (ProductionUnit $unit) => [
                        'id' => $unit->id,
                        'name' => $unit->name,
                    ])->values()->all(),
                    'productionCycles' => $farm->productionCycles->map(fn (ProductionCycle $cycle) => [
                        'id' => $cycle->id,
                        'name' => $cycle->name,
                    ])->values()->all(),
                ])->values()->all(),
            'inventoryItems' => $team->inventoryItems()
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (InventoryItem $item): array => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'unit' => $item->unit,
                    'currentQuantity' => $item->current_quantity,
                ])->values(),
            'options' => [
                'eventTypes' => HealthEventType::options(),
                'eventStatuses' => HealthEventStatus::options(),
                'presets' => HealthScheduleTemplates::options(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function eventPayload(HealthEvent $event): array
    {
        return [
            'id' => $event->id,
            'farmId' => $event->farm_id,
            'farmName' => $event->farm->name,
            'productionCycleId' => $event->production_cycle_id,
            'productionCycleName' => $event->productionCycle?->name,
            'productionUnitId' => $event->production_unit_id,
            'productionUnitName' => $event->productionUnit?->name,
            'inventoryItemId' => $event->inventory_item_id,
            'inventoryItemName' => $event->inventoryItem?->name,
            'eventType' => $event->event_type->value,
            'eventTypeLabel' => $event->event_type->label(),
            'title' => $event->title,
            'productName' => $event->product_name,
            'dosage' => $event->dosage,
            'administrationRoute' => $event->administration_route,
            'scheduledOn' => $event->scheduled_on->toDateString(),
            'completedOn' => $event->completed_on?->toDateString(),
            'withdrawalDays' => $event->withdrawal_days,
            'withdrawalEndsOn' => $event->withdrawalEndsOn()?->toDateString(),
            'isUnderWithdrawal' => $event->isUnderWithdrawal(),
            'isOverdue' => $event->isOverdue(),
            'status' => $event->status->value,
            'statusLabel' => $event->status->label(),
            'affectedCount' => $event->affected_count,
            'costMinor' => $event->cost_minor,
            'cost' => Money::toDecimal($event->cost_minor),
            'currency' => $event->currency,
            'notes' => $event->notes,
            'outcomeNotes' => $event->outcome_notes,
        ];
    }
}
