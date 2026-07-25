<?php

namespace Database\Factories;

use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BudgetLine>
 */
class BudgetLineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'budget_id' => Budget::factory(),
            'team_id' => fn (array $attributes): int => Budget::query()->findOrFail($attributes['budget_id'])->team_id,
            'farm_id' => fn (array $attributes): int => Budget::query()->findOrFail($attributes['budget_id'])->farm_id,
            'production_cycle_id' => fn (array $attributes): ?int => Budget::query()->findOrFail($attributes['budget_id'])->production_cycle_id,
            'expense_category_id' => fn (array $attributes): int => ExpenseCategory::factory()
                ->create(['team_id' => Budget::query()->findOrFail($attributes['budget_id'])->team_id])
                ->id,
            'description' => fake()->sentence(4),
            'planned_amount_minor' => fake()->numberBetween(50_000, 2_000_000),
            'currency' => fn (array $attributes): string => Budget::query()->findOrFail($attributes['budget_id'])->currency,
            'sort_order' => fake()->numberBetween(1, 20),
        ];
    }
}
