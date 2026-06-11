<?php

namespace Database\Factories;

use App\Enums\ApprovalRequestType;
use App\Enums\ApprovalTriggerType;
use App\Models\ApprovalRule;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApprovalRule>
 */
class ApprovalRuleFactory extends Factory
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
            'investor_agreement_id' => null,
            'expense_category_id' => null,
            'funding_phase_id' => null,
            'created_by_id' => User::factory(),
            'name' => 'Investor threshold approval',
            'request_type' => ApprovalRequestType::Expense,
            'trigger_type' => ApprovalTriggerType::Threshold,
            'threshold_amount_minor' => 500_000_00,
            'currency' => 'NGN',
            'farm_type' => null,
            'is_active' => true,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
