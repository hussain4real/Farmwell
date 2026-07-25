<?php

namespace Database\Factories;

use App\Enums\FarmType;
use App\Models\Farm;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Farm>
 */
class FarmFactory extends Factory
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
            'name' => fake()->unique()->company().' Farm',
            'farm_type' => fake()->randomElement(FarmType::cases()),
            'location' => fake()->city(),
            'status' => 'active',
            'registration_number' => fake()->optional()->bothify('FW-####'),
            'contact_name' => fake()->name(),
            'contact_phone' => fake()->phoneNumber(),
            'description' => fake()->optional()->paragraph(),
        ];
    }
}
