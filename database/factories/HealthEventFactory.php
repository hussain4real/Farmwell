<?php

namespace Database\Factories;

use App\Enums\HealthEventStatus;
use App\Enums\HealthEventType;
use App\Models\Farm;
use App\Models\HealthEvent;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HealthEvent>
 */
class HealthEventFactory extends Factory
{
    protected $model = HealthEvent::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $team = Team::factory();

        return [
            'team_id' => $team,
            'farm_id' => Farm::factory()->for($team),
            'event_type' => HealthEventType::Vaccination,
            'title' => 'Lasota Booster',
            'scheduled_on' => now()->addDays(3)->toDateString(),
            'status' => HealthEventStatus::Scheduled,
            'cost_minor' => 0,
            'currency' => 'NGN',
        ];
    }
}
