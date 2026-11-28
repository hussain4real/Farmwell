<?php

namespace App\Actions\Inventory;

use App\Actions\Audit\RecordAuditEvent;
use App\Models\InventoryItem;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UpdateInventoryItem
{
    public function __construct(private RecordAuditEvent $recordAuditEvent)
    {
        //
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Team $team, InventoryItem $item, User $actor, array $attributes): InventoryItem
    {
        return DB::transaction(function () use ($team, $item, $actor, $attributes) {
            $oldValues = [
                'name' => $item->name,
                'category' => $item->category->value,
                'reorder_level' => $item->reorder_level,
                'unit_cost_minor' => $item->unit_cost_minor,
                'is_active' => $item->is_active,
            ];

            $item->update($attributes);
            $item->refresh();

            $this->recordAuditEvent->handle(
                team: $team,
                actor: $actor,
                action: 'inventory_item.updated',
                subject: $item,
                oldValues: $oldValues,
                newValues: [
                    'name' => $item->name,
                    'category' => $item->category->value,
                    'reorder_level' => $item->reorder_level,
                    'unit_cost_minor' => $item->unit_cost_minor,
                    'is_active' => $item->is_active,
                ],
            );

            return $item;
        });
    }
}
