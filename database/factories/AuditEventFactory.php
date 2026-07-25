<?php

namespace Database\Factories;

use App\Models\AuditEvent;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditEvent>
 */
class AuditEventFactory extends Factory
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
            'actor_id' => User::factory(),
            'action' => 'team_setting.updated',
            'old_values' => [
                'value' => [
                    'enabled' => false,
                ],
            ],
            'new_values' => [
                'value' => [
                    'enabled' => true,
                ],
            ],
            'metadata' => [],
            'reason' => fake()->sentence(),
        ];
    }
}
