<?php

namespace Database\Factories;

use App\Models\InvestorAgreement;
use App\Models\InvestorComment;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvestorComment>
 */
class InvestorCommentFactory extends Factory
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
            'investor_agreement_id' => fn (array $attributes): int => InvestorAgreement::factory()
                ->create(['team_id' => $attributes['team_id']])
                ->id,
            'author_id' => User::factory(),
            'subject_type' => null,
            'subject_id' => null,
            'body' => fake()->sentence(),
        ];
    }
}
