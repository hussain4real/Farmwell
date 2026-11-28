<?php

namespace App\Actions\Health;

use App\Actions\Audit\RecordAuditEvent;
use App\Actions\Inventory\RecordInventoryMovement;
use App\Enums\HealthEventStatus;
use App\Enums\InventoryMovementType;
use App\Models\HealthEvent;
use App\Models\InventoryItem;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateHealthEvent
{
    public function __construct(
        private RecordAuditEvent $recordAuditEvent,
        private RecordInventoryMovement $recordInventoryMovement,
    ) {
        //
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(
        Team $team,
        HealthEvent $event,
        User $actor,
        array $attributes,
        ?float $inventoryQuantityUsed = null,
    ): HealthEvent {
        return DB::transaction(function () use ($team, $event, $actor, $attributes, $inventoryQuantityUsed) {
            $oldStatus = $event->status->value;
            $nextStatus = isset($attributes['status'])
                ? HealthEventStatus::from((string) $attributes['status'])
                : $event->status;

            if ($nextStatus === HealthEventStatus::Completed && empty($attributes['completed_on']) && $event->completed_on === null) {
                $attributes['completed_on'] = now()->toDateString();
            }

            $event->update($attributes);
            $event->refresh();

            if (
                $nextStatus === HealthEventStatus::Completed
                && $event->inventory_item_id !== null
                && $inventoryQuantityUsed !== null
                && $inventoryQuantityUsed > 0
            ) {
                $item = InventoryItem::query()
                    ->where('team_id', $team->id)
                    ->whereKey($event->inventory_item_id)
                    ->first();

                if ($item !== null) {
                    $this->recordInventoryMovement->handle($team, $item, $actor, [
                        'farm_id' => $event->farm_id,
                        'production_cycle_id' => $event->production_cycle_id,
                        'movement_type' => InventoryMovementType::Usage,
                        'quantity' => $inventoryQuantityUsed,
                        'movement_date' => $event->completed_on?->toDateString() ?? now()->toDateString(),
                        'notes' => 'Health event: '.$event->title,
                    ]);
                }
            }

            $this->recordAuditEvent->handle(
                team: $team,
                actor: $actor,
                action: 'health_event.updated',
                subject: $event,
                oldValues: ['status' => $oldStatus],
                newValues: [
                    'status' => $event->status->value,
                    'completed_on' => $event->completed_on?->toDateString(),
                ],
            );

            return $event;
        });
    }
}
