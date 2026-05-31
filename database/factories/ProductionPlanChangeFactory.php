<?php

namespace Database\Factories;

use App\Enums\ProductionPlanChangeType;
use App\Models\ProductionCycle;
use App\Models\ProductionPlanChange;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductionPlanChange>
 */
class ProductionPlanChangeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'production_cycle_id' => ProductionCycle::factory(),
            'team_id' => fn (array $attributes) => ProductionCycle::query()->find($attributes['production_cycle_id'])?->team_id,
            'actor_id' => User::factory(),
            'change_type' => fake()->randomElement(ProductionPlanChangeType::cases()),
            'reason' => fake()->sentence(),
            'impact' => fake()->sentence(),
            'investor_safe_summary' => fake()->optional()->sentence(),
            'old_values' => ['plan_version' => 1],
            'new_values' => ['plan_version' => 2],
        ];
    }
}
