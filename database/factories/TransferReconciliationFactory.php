<?php

namespace Database\Factories;

use App\Enums\TransferReconciliationStatus;
use App\Models\ExternalTransfer;
use App\Models\TransferReconciliation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransferReconciliation>
 */
class TransferReconciliationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'external_transfer_id' => ExternalTransfer::factory(),
            'team_id' => fn (array $attributes): int => ExternalTransfer::query()->findOrFail($attributes['external_transfer_id'])->team_id,
            'reconciled_by_id' => User::factory(),
            'status' => TransferReconciliationStatus::Matched,
            'reconciled_amount_minor' => fn (array $attributes): int => ExternalTransfer::query()->findOrFail($attributes['external_transfer_id'])->amount_minor,
            'currency' => fn (array $attributes): string => ExternalTransfer::query()->findOrFail($attributes['external_transfer_id'])->currency,
            'reconciled_at' => now(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
