<?php

namespace Database\Factories;

use App\Enums\DistributionStatus;
use App\Enums\InvestorVisibilityStatus;
use App\Models\DistributionRecord;
use App\Models\Farm;
use App\Models\InvestorAgreement;
use App\Models\SaleRecord;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DistributionRecord>
 */
class DistributionRecordFactory extends Factory
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
            'investor_agreement_id' => fn (array $attributes): int => InvestorAgreement::factory()
                ->create([
                    'team_id' => $attributes['team_id'],
                    'farm_id' => $attributes['farm_id'],
                    'production_cycle_id' => $attributes['production_cycle_id'] ?? null,
                ])
                ->id,
            'sale_record_id' => fn (array $attributes): int => SaleRecord::factory()
                ->create([
                    'team_id' => $attributes['team_id'],
                    'farm_id' => $attributes['farm_id'],
                    'production_cycle_id' => $attributes['production_cycle_id'] ?? null,
                    'investor_agreement_id' => $attributes['investor_agreement_id'],
                ])
                ->id,
            'approval_request_id' => null,
            'sale_gross_amount_minor' => 1_500_000,
            'sale_net_amount_minor' => 1_400_000,
            'previous_capital_recovered_minor' => 0,
            'capital_recovered_minor' => 1_000_000,
            'unrecovered_capital_minor' => 0,
            'gross_profit_minor' => 500_000,
            'net_profit_minor' => 400_000,
            'investor_profit_share_percentage' => 40,
            'farm_profit_share_percentage' => 60,
            'investor_share_minor' => 160_000,
            'farm_share_minor' => 240_000,
            'currency' => 'NGN',
            'status' => DistributionStatus::PendingAcknowledgement,
            'investor_visibility_status' => InvestorVisibilityStatus::PendingApproval,
            'calculated_at' => now(),
            'acknowledged_at' => null,
            'paid_at' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
