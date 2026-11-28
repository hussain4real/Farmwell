<?php

namespace Database\Factories;

use App\Ai\Agents\FarmwellAssistant;
use App\Enums\AiUsageFeature;
use App\Models\AiUsageEvent;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<AiUsageEvent> */
class AiUsageEventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invocation_id' => (string) Str::uuid(),
            'team_id' => Team::factory(),
            'user_id' => User::factory(),
            'impersonator_id' => null,
            'feature' => AiUsageFeature::Assistant,
            'agent_class' => FarmwellAssistant::class,
            'provider' => 'openai',
            'model' => 'gpt-6.2-sol',
            'conversation_id' => (string) Str::uuid(),
            'status' => 'completed',
            'is_streamed' => true,
            'steps_count' => 1,
            'tool_calls_count' => 0,
            'input_tokens' => 1000,
            'uncached_input_tokens' => 1000,
            'cache_read_tokens' => 0,
            'cache_write_tokens' => 0,
            'output_tokens' => 500,
            'reasoning_tokens' => 0,
            'total_tokens' => 1500,
            'input_cost_microdollars' => 2000,
            'output_cost_microdollars' => 5000,
            'cache_cost_microdollars' => 0,
            'cost_microdollars' => 7000,
            'pricing_status' => 'priced',
            'duration_ms' => 450,
            'error_message' => null,
            'metadata' => null,
            'occurred_at' => now(),
        ];
    }
}
