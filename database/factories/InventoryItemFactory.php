<?php

namespace Database\Factories;

use App\Enums\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    protected $model = InventoryItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'farm_id' => null,
            'name' => fake()->words(3, true),
            'category' => InventoryCategory::Feed,
            'unit' => 'bags',
            'unit_weight_kg' => 25,
            'current_quantity' => 20,
            'reorder_level' => 5,
            'unit_cost_minor' => 15_000_00,
            'currency' => 'NGN',
            'is_active' => true,
        ];
    }
}
