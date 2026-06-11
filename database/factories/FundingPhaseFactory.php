<?php

namespace Database\Factories;

use App\Enums\FundingPhaseStatus;
use App\Enums\InvestorVisibilityStatus;
use App\Models\Farm;
use App\Models\FundingPhase;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FundingPhase>
 */
class FundingPhaseFactory extends Factory
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
            'investor_agreement_id' => null,
            'created_by_id' => User::factory(),
            'name' => 'Phase '.fake()->numberBetween(1, 5),
            'milestone' => fake()->optional()->sentence(3),
            'status' => FundingPhaseStatus::Draft,
            'investor_visibility_status' => InvestorVisibilityStatus::Private,
            'currency' => 'NGN',
            'planned_amount_minor' => fake()->numberBetween(100_000, 5_000_000),
            'requested_amount_minor' => fake()->numberBetween(100_000, 5_000_000),
            'approved_amount_minor' => fake()->numberBetween(100_000, 5_000_000),
            'externally_released_amount_minor' => fake()->numberBetween(100_000, 5_000_000),
            'expected_on' => fake()->dateTimeBetween('now', '+2 months'),
            'released_on' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
