<?php

namespace App\Actions\Inventory;

use App\Actions\Audit\RecordAuditEvent;
use App\Actions\Finance\ResolveTeamFinanceCurrency;
use App\Models\InventoryItem;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateInventoryItem
{
    public function __construct(
        private RecordAuditEvent $recordAuditEvent,
        private ResolveTeamFinanceCurrency $resolveTeamFinanceCurrency,
    ) {
        //
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(Team $team, User $actor, array $attributes): InventoryItem
    {
        return DB::transaction(function () use ($team, $actor, $attributes) {
            $item = InventoryItem::create([
                ...$attributes,
                'team_id' => $team->id,
                'currency' => $attributes['currency'] ?? $this->resolveTeamFinanceCurrency->handle($team),
            ]);

            $this->recordAuditEvent->handle(
                team: $team,
                actor: $actor,
                action: 'inventory_item.created',
                subject: $item,
                newValues: [
                    'name' => $item->name,
                    'category' => $item->category->value,
                    'unit' => $item->unit,
                    'current_quantity' => $item->current_quantity,
                ],
            );

            return $item;
        });
    }
}
