<?php

namespace Database\Factories;

use App\Enums\FarmTaskStatus;
use App\Models\Farm;
use App\Models\FarmTask;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FarmTask>
 */
class FarmTaskFactory extends Factory
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
            'created_by_id' => User::factory(),
            'title' => fake()->sentence(4),
            'activity_type' => fake()->randomElement(['Clearing', 'Planting', 'Feeding', 'Inspection']),
            'description' => fake()->optional()->paragraph(),
            'planned_for' => fake()->optional()->dateTimeBetween('now', '+1 week'),
            'due_on' => fake()->dateTimeBetween('now', '+2 weeks'),
            'reminder_at' => fake()->optional()->dateTimeBetween('now', '+1 week'),
            'status' => FarmTaskStatus::Planned,
            'status_reason' => null,
            'completed_at' => null,
            'investor_visible' => false,
        ];
    }
}
