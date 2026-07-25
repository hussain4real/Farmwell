<?php

namespace Database\Factories;

use App\Enums\ExternalTransferDirection;
use App\Enums\ExternalTransferStatus;
use App\Enums\InvestorVisibilityStatus;
use App\Models\ExternalTransfer;
use App\Models\Farm;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExternalTransfer>
 */
class ExternalTransferFactory extends Factory
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
            'funding_phase_id' => null,
            'expense_id' => null,
            'investor_agreement_id' => null,
            'recorded_by_id' => User::factory(),
            'direction' => ExternalTransferDirection::Incoming,
            'transfer_type' => 'recorded release',
            'status' => ExternalTransferStatus::Recorded,
            'investor_visibility_status' => InvestorVisibilityStatus::Private,
            'counterparty_name' => fake()->company(),
            'reference' => fake()->optional()->bothify('TRF-####'),
            'amount_minor' => fake()->numberBetween(100_000, 5_000_000),
            'currency' => 'NGN',
            'transferred_on' => fake()->dateTimeBetween('-1 month', 'now'),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
