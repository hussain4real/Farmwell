<?php

namespace Database\Factories;

use App\Enums\ApprovalRequestStatus;
use App\Enums\ApprovalRequestType;
use App\Enums\ApprovalTriggerType;
use App\Models\ApprovalRequest;
use App\Models\InvestorAgreement;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApprovalRequest>
 */
class ApprovalRequestFactory extends Factory
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
            'approval_rule_id' => null,
            'farm_id' => null,
            'production_cycle_id' => null,
            'expense_category_id' => null,
            'requested_by_id' => null,
            'decided_by_id' => null,
            'subject_type' => null,
            'subject_id' => null,
            'request_type' => ApprovalRequestType::Expense,
            'trigger_type' => ApprovalTriggerType::Threshold,
            'status' => ApprovalRequestStatus::Pending,
            'threshold_amount_minor' => 500_000_00,
            'requested_amount_minor' => 750_000_00,
            'approved_amount_minor' => null,
            'currency' => 'NGN',
            'requester_comment' => fake()->optional()->sentence(),
            'decision_comment' => null,
            'requested_at' => now(),
            'decided_at' => null,
        ];
    }
}
