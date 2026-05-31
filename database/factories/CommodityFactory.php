<?php

namespace Database\Factories;

use App\Enums\FarmType;
use App\Models\Commodity;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Commodity>
 */
class CommodityFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'farm_type' => fake()->randomElement(FarmType::cases()),
            'measurement_unit' => fake()->randomElement(['kg', 'tons', 'crates', 'heads']),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
