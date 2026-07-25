<?php

namespace Database\Factories;

use App\Enums\BudgetStatus;
use App\Models\Budget;
use App\Models\Farm;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Budget>
 */
class BudgetFactory extends Factory
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
            'farm_id' => fn (array $attributes): int => Farm::factory()
                ->create(['team_id' => $attributes['team_id']])
                ->id,
            'production_cycle_id' => null,
            'created_by_id' => User::factory(),
            'approved_by_id' => null,
            'name' => fake()->words(3, true).' budget',
            'status' => BudgetStatus::Draft,
            'currency' => 'NGN',
            'period_start_on' => fake()->dateTimeBetween('-1 month', 'now'),
            'period_end_on' => fake()->dateTimeBetween('now', '+3 months'),
            'notes' => fake()->optional()->sentence(),
            'approved_at' => null,
        ];
    }
}
