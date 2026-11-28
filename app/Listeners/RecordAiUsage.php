<?php

namespace App\Listeners;

use App\Actions\Ai\CalculateAiUsageCost;
use App\Ai\Contracts\HasUsageContext;
use App\Enums\AiUsageFeature;
use App\Models\AiUsageEvent;
use App\Support\PlatformImpersonation;
use Illuminate\Support\Str;
use Laravel\Ai\Events\AgentFailed;
use Laravel\Ai\Events\AgentPrompted;
use Laravel\Ai\Events\AgentStreamed;
use Laravel\Ai\Events\PromptingAgent;
use Throwable;

class RecordAiUsage
{
    /**
     * Start timestamps keyed by invocation ID, using monotonic hrtime nanoseconds.
     *
     * @var array<string, int>
     */
    private array $startedAt = [];

    public function __construct(
        private CalculateAiUsageCost $calculator,
    ) {}

    public function handleStarting(PromptingAgent $event): void
    {
        if (count($this->startedAt) >= 200) {
            array_shift($this->startedAt);
        }

        $this->startedAt[$event->invocationId] = hrtime(true);
    }

    public function handleCompleted(AgentPrompted $event): void
    {
        $durationMs = $this->consumeDurationMs($event->invocationId);

        try {
            $agent = $event->prompt->agent;
            $context = $agent instanceof HasUsageContext
                ? $agent->usageContext()
                : ['team_id' => null, 'user_id' => null, 'feature' => AiUsageFeature::Other, 'metadata' => []];

            $response = $event->response;
            $provider = $this->calculator->normalizeProvider(
                (string) ($response->meta->provider ?: $event->prompt->provider->name()),
            );
            $model = (string) ($response->meta->model ?: $event->prompt->model);

            $usage = $response->usage;
            $inputTokens = max(0, (int) $usage->inputTokens);
            $outputTokens = max(0, (int) $usage->outputTokens);
            $cacheReadTokens = max(0, (int) ($usage->cacheReadInputTokens ?? 0));
            $cacheWriteTokens = max(0, (int) ($usage->cacheWriteInputTokens ?? 0));
            $reasoningTokens = max(0, (int) ($usage->reasoningTokens ?? 0));

            $cost = $this->calculator->calculate(
                provider: $provider,
                model: $model,
                inputTokens: $inputTokens,
                outputTokens: $outputTokens,
                cacheReadTokens: $cacheReadTokens,
                cacheWriteTokens: $cacheWriteTokens,
                reasoningTokens: $reasoningTokens,
            );

            $toolNames = array_values(array_unique(array_filter(array_map(
                static fn (object $call): string => (string) ($call->name ?? ''),
                $response->toolCalls->all(),
            ))));

            $metadata = [
                ...($context['metadata'] ?? []),
                ...($toolNames !== [] ? ['tools' => $toolNames] : []),
            ];

            AiUsageEvent::query()->firstOrCreate(
                ['invocation_id' => $event->invocationId],
                [
                    'team_id' => $context['team_id'],
                    'user_id' => $context['user_id'],
                    'impersonator_id' => $this->resolveImpersonatorId(),
                    'feature' => $context['feature'],
                    'agent_class' => $agent::class,
                    'provider' => $provider !== '' ? $provider : 'openai',
                    'model' => $model !== '' ? $model : 'unknown',
                    'conversation_id' => $response->conversationId,
                    'status' => $response->hasPendingApprovals() ? 'paused' : 'completed',
                    'is_streamed' => $event instanceof AgentStreamed,
                    'steps_count' => max(1, $response->steps->count()),
                    'tool_calls_count' => $response->toolCalls->count(),
                    'input_tokens' => $inputTokens,
                    'uncached_input_tokens' => $cost['uncached_input_tokens'],
                    'cache_read_tokens' => $cacheReadTokens,
                    'cache_write_tokens' => $cacheWriteTokens,
                    'output_tokens' => $outputTokens,
                    'reasoning_tokens' => $reasoningTokens,
                    'total_tokens' => $inputTokens + $outputTokens,
                    'input_cost_microdollars' => $cost['input_cost_microdollars'],
                    'output_cost_microdollars' => $cost['output_cost_microdollars'],
                    'cache_cost_microdollars' => $cost['cache_cost_microdollars'],
                    'cost_microdollars' => $cost['cost_microdollars'],
                    'pricing_status' => $cost['pricing_status'],
                    'duration_ms' => $durationMs,
                    'error_message' => null,
                    'metadata' => $metadata !== [] ? $metadata : null,
                    'occurred_at' => now(),
                ],
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function handleFailed(AgentFailed $event): void
    {
        $durationMs = $this->consumeDurationMs($event->invocationId);

        try {
            $agent = $event->prompt->agent;
            $context = $agent instanceof HasUsageContext
                ? $agent->usageContext()
                : ['team_id' => null, 'user_id' => null, 'feature' => AiUsageFeature::Other, 'metadata' => []];

            $provider = $this->calculator->normalizeProvider($event->prompt->provider->name());
            $model = $event->prompt->model;

            AiUsageEvent::query()->firstOrCreate(
                ['invocation_id' => $event->invocationId],
                [
                    'team_id' => $context['team_id'],
                    'user_id' => $context['user_id'],
                    'impersonator_id' => $this->resolveImpersonatorId(),
                    'feature' => $context['feature'],
                    'agent_class' => $agent::class,
                    'provider' => $provider !== '' ? $provider : 'openai',
                    'model' => $model !== '' ? $model : 'unknown',
                    'conversation_id' => null,
                    'status' => 'failed',
                    'is_streamed' => false,
                    'steps_count' => 1,
                    'tool_calls_count' => 0,
                    'input_tokens' => 0,
                    'uncached_input_tokens' => 0,
                    'cache_read_tokens' => 0,
                    'cache_write_tokens' => 0,
                    'output_tokens' => 0,
                    'reasoning_tokens' => 0,
                    'total_tokens' => 0,
                    'input_cost_microdollars' => 0,
                    'output_cost_microdollars' => 0,
                    'cache_cost_microdollars' => 0,
                    'cost_microdollars' => 0,
                    'pricing_status' => 'priced',
                    'duration_ms' => $durationMs,
                    'error_message' => Str::limit(trim($event->exception->getMessage()), 255),
                    'metadata' => ($context['metadata'] ?? []) !== [] ? $context['metadata'] : null,
                    'occurred_at' => now(),
                ],
            );
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    private function consumeDurationMs(string $invocationId): ?int
    {
        $started = $this->startedAt[$invocationId] ?? null;
        unset($this->startedAt[$invocationId]);

        if ($started === null) {
            return null;
        }

        return max(0, (int) round((hrtime(true) - $started) / 1_000_000));
    }

    private function resolveImpersonatorId(): ?int
    {
        if (! app()->bound('request')) {
            return null;
        }

        $request = request();
        if (! $request->hasSession()) {
            return null;
        }

        $context = PlatformImpersonation::context($request);

        return $context !== null ? $context['impersonator_id'] : null;
    }
}
