<?php

namespace Database\Factories;

use App\Enums\ProductionLogDomain;
use App\Models\Farm;
use App\Models\ProductionCycle;
use App\Models\ProductionLog;
use App\Models\ProductionUnit;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductionLog>
 */
class ProductionLogFactory extends Factory
{
    protected $model = ProductionLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $team = Team::factory();

        return [
            'team_id' => $team,
            'farm_id' => Farm::factory()->for($team),
            'production_cycle_id' => ProductionCycle::factory()->for($team),
            'production_unit_id' => ProductionUnit::factory()->for($team),
            'recorded_by_id' => User::factory(),
            'log_date' => now()->toDateString(),
            'domain' => ProductionLogDomain::General,
            'mortality_count' => 0,
            'cull_count' => 0,
            'graded_out_count' => 0,
            'water_exchanged' => false,
            'irrigation_applied' => false,
            'notes' => fake()->sentence(),
        ];
    }
}
