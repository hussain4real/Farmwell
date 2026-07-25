<?php

namespace Database\Factories;

use App\Enums\InvestorVisibilityStatus;
use App\Enums\SalePaymentStatus;
use App\Models\Farm;
use App\Models\HarvestRecord;
use App\Models\SaleRecord;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleRecord>
 */
class SaleRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->randomFloat(2, 1, 250);
        $unitPriceMinor = fake()->numberBetween(5_000, 50_000);
        $grossAmountMinor = (int) round($quantity * $unitPriceMinor);
        $deductionAmountMinor = fake()->numberBetween(0, (int) floor($grossAmountMinor * 0.15));

        return [
            'team_id' => Team::factory(),
            'farm_id' => fn (array $attributes): int => Farm::factory()
                ->create(['team_id' => $attributes['team_id']])
                ->id,
            'production_cycle_id' => null,
            'harvest_record_id' => fn (array $attributes): int => HarvestRecord::factory()
                ->create([
                    'team_id' => $attributes['team_id'],
                    'farm_id' => $attributes['farm_id'],
                    'production_cycle_id' => $attributes['production_cycle_id'] ?? null,
                ])
                ->id,
            'commodity_id' => fn (array $attributes): int => HarvestRecord::query()
                ->findOrFail($attributes['harvest_record_id'])
                ->commodity_id,
            'investor_agreement_id' => null,
            'recorded_by_id' => User::factory(),
            'sold_on' => fake()->dateTimeBetween('-1 week', 'now'),
            'buyer_name' => fake()->company(),
            'quantity' => $quantity,
            'quantity_unit' => 'kg',
            'unit_price_minor' => $unitPriceMinor,
            'gross_amount_minor' => $grossAmountMinor,
            'deduction_amount_minor' => $deductionAmountMinor,
            'net_amount_minor' => $grossAmountMinor - $deductionAmountMinor,
            'currency' => 'NGN',
            'payment_status' => SalePaymentStatus::Paid,
            'reference' => fake()->optional()->bothify('SALE-####'),
            'investor_visibility_status' => InvestorVisibilityStatus::Private,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
