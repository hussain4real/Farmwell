<?php

namespace Database\Factories;

use App\Enums\FarmType;
use App\Enums\ProductionCycleStatus;
use App\Models\Farm;
use App\Models\ProductionCycle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductionCycle>
 */
class ProductionCycleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $plannedStart = fake()->dateTimeBetween('-1 month', '+1 month');

        return [
            'farm_id' => Farm::factory(),
            'team_id' => fn (array $attributes) => Farm::query()->find($attributes['farm_id'])?->team_id,
            'name' => fake()->unique()->words(3, true),
            'season' => (string) now()->year,
            'farm_type' => fake()->randomElement(FarmType::cases()),
            'production_method' => fake()->optional()->randomElement(['Rain-fed', 'Irrigated', 'Greenhouse', 'Deep litter']),
            'planned_start_on' => $plannedStart,
            'planned_end_on' => fake()->dateTimeBetween($plannedStart, '+6 months'),
            'actual_start_on' => null,
            'actual_end_on' => null,
            'expected_output_quantity' => fake()->randomFloat(2, 1, 500),
            'expected_output_unit' => fake()->randomElement(['kg', 'tons', 'crates', 'heads']),
            'status' => ProductionCycleStatus::Planned,
            'plan_version' => 1,
            'plan_summary' => fake()->optional()->paragraph(),
        ];
    }
}
