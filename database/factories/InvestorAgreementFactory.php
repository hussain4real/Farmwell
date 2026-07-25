<?php

namespace Database\Factories;

use App\Enums\CapitalRecoveryRule;
use App\Enums\InvestorAgreementStatus;
use App\Models\Farm;
use App\Models\InvestorAgreement;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvestorAgreement>
 */
class InvestorAgreementFactory extends Factory
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
            'investor_id' => User::factory(),
            'farm_id' => fn (array $attributes): int => Farm::factory()
                ->create(['team_id' => $attributes['team_id']])
                ->id,
            'production_cycle_id' => null,
            'created_by_id' => User::factory(),
            'title' => fake()->words(3, true).' agreement',
            'status' => InvestorAgreementStatus::Active,
            'currency' => 'NGN',
            'amount_committed_minor' => 5_000_000,
            'amount_funded_minor' => 2_500_000,
            'capital_recovery_rule' => CapitalRecoveryRule::CapitalFirst,
            'investor_profit_share_percentage' => 40,
            'farm_profit_share_percentage' => 60,
            'funding_model' => 'phased',
            'role_responsibilities' => fake()->optional()->paragraph(),
            'public_notes' => fake()->optional()->paragraph(),
            'internal_notes' => fake()->optional()->paragraph(),
            'starts_on' => now()->toDateString(),
            'ends_on' => now()->addMonths(6)->toDateString(),
            'signed_at' => now(),
        ];
    }
}
