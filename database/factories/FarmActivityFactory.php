<?php

namespace Database\Factories;

use App\Enums\FarmActivityStatus;
use App\Models\Farm;
use App\Models\FarmActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FarmActivity>
 */
class FarmActivityFactory extends Factory
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
            'recorded_by_id' => User::factory(),
            'activity_date' => fake()->dateTimeBetween('-2 weeks', 'now'),
            'activity_type' => fake()->randomElement(['Clearing', 'Planting', 'Feeding', 'Inspection']),
            'description' => fake()->paragraph(),
            'inputs_used' => fake()->optional()->sentence(),
            'labour_used' => fake()->optional()->sentence(),
            'cost' => fake()->optional()->randomFloat(2, 500, 25000),
            'remarks' => fake()->optional()->sentence(),
            'next_activity' => fake()->optional()->sentence(),
            'status' => FarmActivityStatus::Completed,
            'internal_notes' => fake()->optional()->sentence(),
            'investor_safe_summary' => fake()->optional()->sentence(),
            'source_type' => 'manual',
            'source_reference_id' => null,
        ];
    }
}
