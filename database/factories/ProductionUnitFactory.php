<?php

namespace Database\Factories;

use App\Enums\ProductionUnitType;
use App\Models\Farm;
use App\Models\ProductionUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductionUnit>
 */
class ProductionUnitFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'team_id' => fn (array $attributes) => Farm::query()->find($attributes['farm_id'])?->team_id,
            'name' => fake()->words(2, true).' '.fake()->bothify('##'),
            'unit_type' => fake()->randomElement(ProductionUnitType::cases()),
            'size' => fake()->randomFloat(2, 0.25, 25),
            'size_unit' => fake()->randomElement(['hectares', 'acres', 'sqm', 'pens']),
            'capacity' => fake()->optional()->randomElement(['2,000 plants', '500 birds', '12 ponds']),
            'status' => 'available',
            'gps_coordinates' => fake()->optional()->latitude().', '.fake()->longitude(),
            'suitability_notes' => fake()->optional()->sentence(),
            'history_notes' => fake()->optional()->sentence(),
        ];
    }
}
