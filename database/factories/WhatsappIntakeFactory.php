<?php

namespace Database\Factories;

use App\Enums\WhatsappIntakeStatus;
use App\Models\Farm;
use App\Models\User;
use App\Models\WhatsappIntake;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsappIntake>
 */
class WhatsappIntakeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'farm_id' => Farm::factory(),
            'team_id' => fn (array $attributes) => Farm::query()->find($attributes['farm_id'])?->team_id,
            'imported_by_id' => User::factory(),
            'source_message' => fake()->paragraph(),
            'source_sender' => fake()->optional()->name(),
            'source_date' => fake()->optional()->dateTimeBetween('-1 week', 'now'),
            'normalized_activity_date' => null,
            'normalized_activity_type' => null,
            'normalized_description' => null,
            'normalized_cost' => null,
            'normalized_next_activity' => null,
            'normalized_investor_safe_summary' => null,
            'review_status' => WhatsappIntakeStatus::Pending,
            'reviewed_at' => null,
            'rejection_reason' => null,
        ];
    }
}
