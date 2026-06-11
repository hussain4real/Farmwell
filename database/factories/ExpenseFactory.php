<?php

namespace Database\Factories;

use App\Enums\ExpenseStatus;
use App\Enums\InvestorVisibilityStatus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Farm;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Expense>
 */
class ExpenseFactory extends Factory
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
            'budget_id' => null,
            'budget_line_id' => null,
            'funding_phase_id' => null,
            'investor_agreement_id' => null,
            'expense_category_id' => fn (array $attributes): int => ExpenseCategory::factory()
                ->create(['team_id' => $attributes['team_id']])
                ->id,
            'farm_activity_id' => null,
            'recorded_by_id' => User::factory(),
            'incurred_on' => fake()->dateTimeBetween('-1 month', 'now'),
            'vendor' => fake()->optional()->company(),
            'payment_method' => fake()->optional()->randomElement(['cash', 'bank transfer', 'card']),
            'description' => fake()->sentence(),
            'amount_minor' => fake()->numberBetween(10_000, 1_000_000),
            'currency' => 'NGN',
            'status' => ExpenseStatus::Approved,
            'investor_visibility_status' => InvestorVisibilityStatus::Private,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
