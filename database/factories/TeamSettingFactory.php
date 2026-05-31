<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\TeamSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamSetting>
 */
class TeamSettingFactory extends Factory
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
            'setting_group' => 'operations',
            'setting_key' => fake()->unique()->slug(2),
            'value' => [
                'enabled' => fake()->boolean(),
            ],
            'updated_by' => User::factory(),
        ];
    }
}
