<?php

namespace Database\Factories;

use App\Enums\HarvestRecordStatus;
use App\Enums\HarvestStage;
use App\Enums\InvestorVisibilityStatus;
use App\Models\Commodity;
use App\Models\Farm;
use App\Models\HarvestRecord;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HarvestRecord>
 */
class HarvestRecordFactory extends Factory
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
            'production_unit_id' => null,
            'production_cycle_id' => null,
            'commodity_id' => fn (array $attributes): int => Commodity::factory()
                ->create(['team_id' => $attributes['team_id']])
                ->id,
            'investor_agreement_id' => null,
            'recorded_by_id' => User::factory(),
            'harvested_on' => fake()->dateTimeBetween('-2 weeks', 'now'),
            'stage' => fake()->randomElement(HarvestStage::cases()),
            'sequence_number' => fake()->numberBetween(1, 6),
            'quantity' => fake()->randomFloat(2, 1, 500),
            'quantity_unit' => fake()->randomElement(['kg', 'bags', 'crates']),
            'quality_notes' => fake()->optional()->sentence(),
            'labour_cost_minor' => fake()->numberBetween(0, 200_000),
            'currency' => 'NGN',
            'status' => HarvestRecordStatus::Recorded,
            'investor_visibility_status' => InvestorVisibilityStatus::Private,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
