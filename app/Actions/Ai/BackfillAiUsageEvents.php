<?php

namespace App\Actions\Ai;

use App\Ai\Agents\FarmwellAssistant;
use App\Enums\AiUsageFeature;
use App\Models\AiUsageEvent;
use App\Models\Membership;
use Laravel\Ai\Models\ConversationMessage;

class BackfillAiUsageEvents
{
    public function __construct(
        private CalculateAiUsageCost $calculator,
    ) {}

    /**
     * Backfill assistant conversation messages stored before usage telemetry existed.
     *
     * @return array{imported: int, skipped: int}
     */
    public function handle(): array
    {
        $imported = 0;
        $skipped = 0;
        /** @var array<int, array{team_id: ?int, user_id: ?int}> $memberships */
        $memberships = [];
        $membershipMorph = (new Membership)->getMorphClass();

        ConversationMessage::query()
            ->where('role', 'assistant')
            ->orderBy('id')
            ->chunk(100, function ($messages) use (&$imported, &$skipped, &$memberships, $membershipMorph): void {
                $invocationIds = $messages
                    ->map(static fn (ConversationMessage $message): string => 'backfill:msg:'.$message->id)
                    ->all();

                $existingIds = AiUsageEvent::query()
                    ->whereIn('invocation_id', $invocationIds)
                    ->pluck('invocation_id')
                    ->all();
                $existingMap = array_flip($existingIds);

                foreach ($messages as $message) {
                    $invocationId = 'backfill:msg:'.$message->id;

                    if (isset($existingMap[$invocationId])) {
                        $skipped++;

                        continue;
                    }

                    $teamId = null;
                    $userId = null;
                    $participantType = $message->getAttribute('participant_type');
                    $participantId = $message->getAttribute('participant_id');

                    if ($participantType === $membershipMorph && is_numeric($participantId)) {
                        $membershipId = (int) $participantId;
                        if (! array_key_exists($membershipId, $memberships)) {
                            $membership = Membership::query()->find($membershipId, ['id', 'team_id', 'user_id']);
                            $memberships[$membershipId] = [
                                'team_id' => $membership?->team_id,
                                'user_id' => $membership?->user_id,
                            ];
                        }

                        $teamId = $memberships[$membershipId]['team_id'];
                        $userId = $memberships[$membershipId]['user_id'];
                    }

                    /** @var array<string, mixed> $usage */
                    $usage = is_array($message->getAttribute('usage')) ? $message->getAttribute('usage') : [];
                    /** @var array<string, mixed> $meta */
                    $meta = is_array($message->getAttribute('meta')) ? $message->getAttribute('meta') : [];
                    /** @var array<int, array<string, mixed>> $steps */
                    $steps = is_array($message->getAttribute('steps')) ? $message->getAttribute('steps') : [];

                    $rawProvider = is_string($meta['provider'] ?? null) && $meta['provider'] !== ''
                        ? $meta['provider']
                        : (string) config('ai.default', 'openai');
                    $provider = $this->calculator->normalizeProvider($rawProvider);

                    $configuredModel = config('ai.assistant_model');
                    $model = is_string($meta['model'] ?? null) && $meta['model'] !== ''
                        ? $meta['model']
                        : (is_string($configuredModel) && $configuredModel !== '' ? $configuredModel : 'unknown');

                    $inputTokens = max(0, (int) ($usage['input_tokens'] ?? 0));
                    $outputTokens = max(0, (int) ($usage['output_tokens'] ?? 0));
                    $cacheReadTokens = max(0, (int) ($usage['cache_read_input_tokens'] ?? 0));
                    $cacheWriteTokens = max(0, (int) ($usage['cache_write_input_tokens'] ?? 0));
                    $reasoningTokens = max(0, (int) ($usage['reasoning_tokens'] ?? 0));

                    $cost = $this->calculator->calculate(
                        provider: $provider,
                        model: $model,
                        inputTokens: $inputTokens,
                        outputTokens: $outputTokens,
                        cacheReadTokens: $cacheReadTokens,
                        cacheWriteTokens: $cacheWriteTokens,
                        reasoningTokens: $reasoningTokens,
                    );

                    $toolCalls = $message->tool_calls;
                    $toolNames = array_values(array_unique(array_filter(array_map(
                        static fn (array $call): string => (string) ($call['name'] ?? ''),
                        $toolCalls,
                    ))));

                    $agentClass = (string) ($message->getAttribute('agent') ?: FarmwellAssistant::class);
                    $feature = $agentClass === FarmwellAssistant::class
                        ? AiUsageFeature::Assistant
                        : AiUsageFeature::Other;

                    $error = is_string($meta['error'] ?? null) && $meta['error'] !== ''
                        ? substr($meta['error'], 0, 255)
                        : null;

                    $metadata = ['backfilled' => true];
                    if ($toolNames !== []) {
                        $metadata['tools'] = $toolNames;
                    }

                    AiUsageEvent::query()->create([
                        'invocation_id' => $invocationId,
                        'team_id' => $teamId,
                        'user_id' => $userId,
                        'impersonator_id' => null,
                        'feature' => $feature,
                        'agent_class' => $agentClass,
                        'provider' => $provider !== '' ? $provider : 'openai',
                        'model' => $model,
                        'conversation_id' => $message->getAttribute('conversation_id'),
                        'status' => $message->status->value,
                        'is_streamed' => true,
                        'steps_count' => max(1, count($steps)),
                        'tool_calls_count' => count($toolCalls),
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
                        'duration_ms' => null,
                        'error_message' => $error,
                        'metadata' => $metadata,
                        'occurred_at' => $message->created_at ?? now(),
                    ]);

                    $imported++;
                }
            });

        return [
            'imported' => $imported,
            'skipped' => $skipped,
        ];
    }
}
