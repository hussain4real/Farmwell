<?php

namespace Database\Factories;

use App\Models\AiSummary;
use App\Models\InvestorAgreement;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AiSummary> */
class AiSummaryFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'investor_agreement_id' => fn (array $attributes): int => InvestorAgreement::factory()->create(['team_id' => $attributes['team_id']])->id,
            'requested_by_id' => User::factory(),
            'status' => 'queued',
        ];
    }
}
