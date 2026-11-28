<?php

namespace Database\Factories;

use App\Enums\InventoryMovementType;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryMovement>
 */
class InventoryMovementFactory extends Factory
{
    protected $model = InventoryMovement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $team = Team::factory();

        return [
            'team_id' => $team,
            'inventory_item_id' => InventoryItem::factory()->for($team),
            'recorded_by_id' => User::factory(),
            'movement_type' => InventoryMovementType::Purchase,
            'quantity' => 10,
            'unit_cost_minor' => 15_000_00,
            'total_cost_minor' => 150_000_00,
            'currency' => 'NGN',
            'movement_date' => now()->toDateString(),
        ];
    }
}
