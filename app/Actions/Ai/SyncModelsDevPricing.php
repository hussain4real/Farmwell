<?php

namespace App\Actions\Ai;

use App\Models\AiModelPrice;
use App\Models\AiUsageEvent;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SyncModelsDevPricing
{
    public const array ProviderMap = [
        'openai' => 'openai',
        'anthropic' => 'anthropic',
        'google' => 'gemini',
        'deepseek' => 'deepseek',
        'mistral' => 'mistral',
        'groq' => 'groq',
        'xai' => 'xai',
        'cohere' => 'cohere',
        'openrouter' => 'openrouter',
        'azure' => 'azure',
        'amazon-bedrock' => 'bedrock',
    ];

    public function __construct(
        private CalculateAiUsageCost $calculator,
    ) {}

    /**
     * Fetch the models.dev catalog, upsert token rates, and retroactively price unpriced events.
     *
     * @return array{synced_models: int, repriced_events: int, synced_at: string}
     */
    public function handle(): array
    {
        $url = (string) config('ai.pricing.models_dev_url', 'https://models.dev/api.json');
        $timeout = max(3, (int) config('ai.pricing.timeout', 15));

        $response = Http::timeout($timeout)
            ->acceptJson()
            ->get($url)
            ->throw();

        $catalog = $response->json();
        if (! is_array($catalog)) {
            throw new RuntimeException('Invalid models.dev catalog payload.');
        }

        $syncedAt = now();
        $rows = [];

        foreach (self::ProviderMap as $catalogProvider => $farmwellProvider) {
            $entry = $catalog[$catalogProvider] ?? null;
            if (! is_array($entry) || ! is_array($entry['models'] ?? null)) {
                continue;
            }

            foreach ($entry['models'] as $modelKey => $modelData) {
                if (! is_array($modelData)) {
                    continue;
                }

                $cost = $modelData['cost'] ?? null;
                if (! is_array($cost) || ! is_numeric($cost['input'] ?? null) || ! is_numeric($cost['output'] ?? null)) {
                    continue;
                }

                $modelId = is_string($modelData['id'] ?? null) && $modelData['id'] !== ''
                    ? $modelData['id']
                    : (string) $modelKey;

                $limit = is_array($modelData['limit'] ?? null) ? $modelData['limit'] : [];
                $tiers = is_array($cost['tiers'] ?? null) ? $cost['tiers'] : null;

                $rows[] = [
                    'provider' => $farmwellProvider,
                    'model_id' => $modelId,
                    'canonical_id' => is_string($modelData['canonical_model_id'] ?? null)
                        ? $modelData['canonical_model_id']
                        : $farmwellProvider.'/'.$modelId,
                    'display_name' => is_string($modelData['name'] ?? null) && $modelData['name'] !== ''
                        ? $modelData['name']
                        : $modelId,
                    'input_cost_per_million' => (float) $cost['input'],
                    'output_cost_per_million' => (float) $cost['output'],
                    'cache_read_cost_per_million' => is_numeric($cost['cache_read'] ?? null) ? (float) $cost['cache_read'] : null,
                    'cache_write_cost_per_million' => is_numeric($cost['cache_write'] ?? null) ? (float) $cost['cache_write'] : null,
                    'reasoning_cost_per_million' => is_numeric($cost['reasoning'] ?? null) ? (float) $cost['reasoning'] : null,
                    'context_limit' => is_numeric($limit['context'] ?? null) && (int) $limit['context'] > 0 ? (int) $limit['context'] : null,
                    'output_limit' => is_numeric($limit['output'] ?? null) && (int) $limit['output'] > 0 ? (int) $limit['output'] : null,
                    'tiers' => $tiers !== null ? json_encode($tiers, JSON_THROW_ON_ERROR) : null,
                    'source' => 'models.dev',
                    'synced_at' => $syncedAt,
                    'created_at' => $syncedAt,
                    'updated_at' => $syncedAt,
                ];
            }
        }

        foreach (array_chunk($rows, 100) as $chunk) {
            AiModelPrice::query()->upsert(
                $chunk,
                ['provider', 'model_id'],
                [
                    'canonical_id',
                    'display_name',
                    'input_cost_per_million',
                    'output_cost_per_million',
                    'cache_read_cost_per_million',
                    'cache_write_cost_per_million',
                    'reasoning_cost_per_million',
                    'context_limit',
                    'output_limit',
                    'tiers',
                    'source',
                    'synced_at',
                    'updated_at',
                ],
            );
        }

        $repricedEvents = $this->repriceUnpricedEvents();

        return [
            'synced_models' => count($rows),
            'repriced_events' => $repricedEvents,
            'synced_at' => $syncedAt->toIso8601String(),
        ];
    }

    private function repriceUnpricedEvents(): int
    {
        $repriced = 0;

        AiUsageEvent::query()
            ->where('pricing_status', 'unpriced')
            ->orderBy('id')
            ->chunkById(100, function ($events) use (&$repriced): void {
                foreach ($events as $event) {
                    $result = $this->calculator->calculate(
                        provider: $event->provider,
                        model: $event->model,
                        inputTokens: $event->input_tokens,
                        outputTokens: $event->output_tokens,
                        cacheReadTokens: $event->cache_read_tokens,
                        cacheWriteTokens: $event->cache_write_tokens,
                        reasoningTokens: $event->reasoning_tokens,
                    );

                    if ($result['pricing_status'] !== 'priced') {
                        continue;
                    }

                    $event->update([
                        'pricing_status' => 'priced',
                        'uncached_input_tokens' => $result['uncached_input_tokens'],
                        'input_cost_microdollars' => $result['input_cost_microdollars'],
                        'output_cost_microdollars' => $result['output_cost_microdollars'],
                        'cache_cost_microdollars' => $result['cache_cost_microdollars'],
                        'cost_microdollars' => $result['cost_microdollars'],
                    ]);
                    $repriced++;
                }
            });

        return $repriced;
    }
}
