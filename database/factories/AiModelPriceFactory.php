<?php

namespace Database\Factories;

use App\Models\AiModelPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AiModelPrice> */
class AiModelPriceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $modelId = 'gpt-6.2-sol-'.fake()->unique()->lexify('????');

        return [
            'provider' => 'openai',
            'model_id' => $modelId,
            'canonical_id' => 'openai/'.$modelId,
            'display_name' => 'GPT-6.2 Sol',
            'input_cost_per_million' => 2.0,
            'output_cost_per_million' => 10.0,
            'cache_read_cost_per_million' => 0.1,
            'cache_write_cost_per_million' => 2.5,
            'reasoning_cost_per_million' => null,
            'context_limit' => 1_050_000,
            'output_limit' => 128_000,
            'tiers' => null,
            'source' => 'models.dev',
            'synced_at' => now(),
        ];
    }
}
