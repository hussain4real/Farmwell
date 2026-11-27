<?php

namespace App\Actions\Ai;

use App\Models\AiModelPrice;

class CalculateAiUsageCost
{
    public const array ProviderAliases = [
        'google' => 'gemini',
        'amazon-bedrock' => 'bedrock',
    ];

    public function normalizeProvider(string $provider): string
    {
        $normalized = strtolower(trim($provider));

        return self::ProviderAliases[$normalized] ?? $normalized;
    }

    public function resolvePrice(string $provider, string $model): ?AiModelPrice
    {
        $cleanModel = trim($model);
        if ($cleanModel === '') {
            return null;
        }

        $normalizedProvider = $this->normalizeProvider($provider);

        $exact = AiModelPrice::query()
            ->where('provider', $normalizedProvider)
            ->where('model_id', $cleanModel)
            ->first();

        if ($exact !== null) {
            return $exact;
        }

        $strippedModel = preg_replace('/-(?:20\d{2}-?\d{2}-?\d{2})$/', '', $cleanModel) ?? $cleanModel;

        if ($strippedModel !== $cleanModel) {
            $stripped = AiModelPrice::query()
                ->where('provider', $normalizedProvider)
                ->where('model_id', $strippedModel)
                ->first();

            if ($stripped !== null) {
                return $stripped;
            }
        }

        return AiModelPrice::query()
            ->where('canonical_id', $normalizedProvider.'/'.$cleanModel)
            ->orWhere('model_id', $cleanModel)
            ->first();
    }

    /**
     * Calculate exact USD microdollar cost components for a single invocation.
     *
     * @return array{
     *     pricing_status: 'priced'|'unpriced',
     *     input_cost_microdollars: int,
     *     output_cost_microdollars: int,
     *     cache_cost_microdollars: int,
     *     cost_microdollars: int,
     *     estimated_cache_savings_microdollars: int,
     *     uncached_input_tokens: int,
     *     price: ?AiModelPrice,
     * }
     */
    public function calculate(
        string $provider,
        string $model,
        int $inputTokens,
        int $outputTokens,
        int $cacheReadTokens = 0,
        int $cacheWriteTokens = 0,
        int $reasoningTokens = 0,
        ?AiModelPrice $resolvedPrice = null,
    ): array {
        $uncachedInput = max(0, $inputTokens - $cacheReadTokens - $cacheWriteTokens);
        $price = $resolvedPrice ?? $this->resolvePrice($provider, $model);

        if ($price === null) {
            return [
                'pricing_status' => 'unpriced',
                'input_cost_microdollars' => 0,
                'output_cost_microdollars' => 0,
                'cache_cost_microdollars' => 0,
                'cost_microdollars' => 0,
                'estimated_cache_savings_microdollars' => 0,
                'uncached_input_tokens' => $uncachedInput,
                'price' => null,
            ];
        }

        $rates = $this->effectiveRatesFor($price, $inputTokens);
        $inputRate = $rates['input'];
        $outputRate = $rates['output'];
        $cacheReadRate = $rates['cache_read'] ?? $inputRate;
        $cacheWriteRate = $rates['cache_write'] ?? $inputRate;
        $reasoningRate = $rates['reasoning'];

        // Rates are USD per 1M tokens, so (tokens * rate) equals exact microdollars ($1e-6).
        $inputMicrodollars = (int) round($uncachedInput * $inputRate);
        $cacheMicrodollars = (int) round(($cacheReadTokens * $cacheReadRate) + ($cacheWriteTokens * $cacheWriteRate));

        if ($reasoningRate !== null && $reasoningTokens > 0) {
            $nonReasoningOutput = max(0, $outputTokens - $reasoningTokens);
            $outputMicrodollars = (int) round(($nonReasoningOutput * $outputRate) + ($reasoningTokens * $reasoningRate));
        } else {
            $outputMicrodollars = (int) round($outputTokens * $outputRate);
        }

        $cacheSavingsMicrodollars = (int) round($cacheReadTokens * max(0.0, $inputRate - $cacheReadRate));

        return [
            'pricing_status' => 'priced',
            'input_cost_microdollars' => $inputMicrodollars,
            'output_cost_microdollars' => $outputMicrodollars,
            'cache_cost_microdollars' => $cacheMicrodollars,
            'cost_microdollars' => $inputMicrodollars + $cacheMicrodollars + $outputMicrodollars,
            'estimated_cache_savings_microdollars' => $cacheSavingsMicrodollars,
            'uncached_input_tokens' => $uncachedInput,
            'price' => $price,
        ];
    }

    /**
     * Resolve effective per-million USD rates, applying models.dev context-window tiers when exceeded.
     *
     * @return array{input: float, output: float, cache_read: ?float, cache_write: ?float, reasoning: ?float}
     */
    private function effectiveRatesFor(AiModelPrice $price, int $inputTokens): array
    {
        $rates = [
            'input' => $price->input_cost_per_million,
            'output' => $price->output_cost_per_million,
            'cache_read' => $price->cache_read_cost_per_million,
            'cache_write' => $price->cache_write_cost_per_million,
            'reasoning' => $price->reasoning_cost_per_million,
        ];

        $matchedThreshold = 0;

        foreach ($price->tiers ?? [] as $tier) {
            if (! is_array($tier)) {
                continue;
            }

            $meta = $tier['tier'] ?? null;
            if (! is_array($meta) || ($meta['type'] ?? null) !== 'context') {
                continue;
            }

            $threshold = is_numeric($meta['size'] ?? null) ? (int) $meta['size'] : 0;
            if ($threshold <= 0 || $inputTokens <= $threshold || $threshold < $matchedThreshold) {
                continue;
            }

            $matchedThreshold = $threshold;
            $rates['input'] = is_numeric($tier['input'] ?? null) ? (float) $tier['input'] : $rates['input'];
            $rates['output'] = is_numeric($tier['output'] ?? null) ? (float) $tier['output'] : $rates['output'];
            $rates['cache_read'] = is_numeric($tier['cache_read'] ?? null) ? (float) $tier['cache_read'] : $rates['cache_read'];
            $rates['cache_write'] = is_numeric($tier['cache_write'] ?? null) ? (float) $tier['cache_write'] : $rates['cache_write'];
            $rates['reasoning'] = is_numeric($tier['reasoning'] ?? null) ? (float) $tier['reasoning'] : $rates['reasoning'];
        }

        return $rates;
    }
}
