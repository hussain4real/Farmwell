<?php

namespace App\Actions\Ai;

use App\Actions\Teams\ResolveWorkspacePlan;
use App\Enums\AiUsageFeature;
use App\Models\AiModelPrice;
use App\Models\AiUsageEvent;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class BuildAiUsageAnalytics
{
    public const array RangeDays = [
        '7d' => 7,
        '30d' => 30,
        '90d' => 90,
    ];

    public function __construct(
        private CalculateAiUsageCost $calculator,
        private ResolveWorkspacePlan $plans,
    ) {}

    /**
     * Build filtered KPIs, daily charts, breakdowns, catalog state, and recent AI calls.
     *
     * @param  array{
     *     range?: string,
     *     team_id?: int|null,
     *     user_id?: int|null,
     *     feature?: string|null,
     *     model?: string|null,
     *     status?: string|null,
     * }  $rawFilters
     * @return array<string, mixed>
     */
    public function handle(array $rawFilters): array
    {
        $filters = $this->normalizeFilters($rawFilters);
        [$windowStart, $windowEnd, $prevStart, $prevEnd] = $this->resolveWindow($filters['range']);

        $events = $this->filteredQuery($filters, $windowStart, $windowEnd)
            ->orderBy('occurred_at')
            ->get([
                'id',
                'team_id',
                'user_id',
                'feature',
                'provider',
                'model',
                'status',
                'steps_count',
                'tool_calls_count',
                'input_tokens',
                'uncached_input_tokens',
                'cache_read_tokens',
                'cache_write_tokens',
                'output_tokens',
                'reasoning_tokens',
                'total_tokens',
                'input_cost_microdollars',
                'output_cost_microdollars',
                'cache_cost_microdollars',
                'cost_microdollars',
                'pricing_status',
                'duration_ms',
                'occurred_at',
            ]);

        $prices = AiModelPrice::query()->get();
        $priceMap = $this->buildPriceMap($prices);

        $previousCost = null;
        $costDeltaPercent = null;
        if ($prevStart !== null && $prevEnd !== null) {
            $previousCost = (int) $this->filteredQuery($filters, $prevStart, $prevEnd)
                ->sum('cost_microdollars');

            if ($previousCost > 0) {
                $currentCost = (int) $events->sum('cost_microdollars');
                $costDeltaPercent = round((($currentCost - $previousCost) / $previousCost) * 100, 1);
            }
        }

        $recentCalls = $this->filteredQuery($filters, $windowStart, $windowEnd)
            ->with(['team:id,name,slug', 'user:id,name,email', 'impersonator:id,name'])
            ->latest('occurred_at')
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(static fn (AiUsageEvent $event): array => [
                'id' => $event->id,
                'feature' => $event->feature->value,
                'featureLabel' => $event->feature->label(),
                'provider' => $event->provider,
                'model' => $event->model,
                'status' => $event->status,
                'isStreamed' => $event->is_streamed,
                'stepsCount' => $event->steps_count,
                'toolCallsCount' => $event->tool_calls_count,
                'inputTokens' => $event->input_tokens,
                'cacheReadTokens' => $event->cache_read_tokens,
                'outputTokens' => $event->output_tokens,
                'totalTokens' => $event->total_tokens,
                'costMicrodollars' => $event->cost_microdollars,
                'pricingStatus' => $event->pricing_status,
                'durationMs' => $event->duration_ms,
                'errorMessage' => $event->error_message,
                'occurredAt' => $event->occurred_at->toIso8601String(),
                'occurredAtFormatted' => $event->occurred_at->format('M j, H:i'),
                'user' => $event->user ? [
                    'id' => $event->user->id,
                    'name' => $event->user->name,
                    'email' => $event->user->email,
                ] : null,
                'impersonator' => $event->impersonator ? [
                    'id' => $event->impersonator->id,
                    'name' => $event->impersonator->name,
                ] : null,
                'team' => $event->team ? [
                    'id' => $event->team->id,
                    'name' => $event->team->name,
                    'slug' => $event->team->slug,
                ] : null,
            ]);

        $lastSyncedAt = $prices->max('synced_at');
        $unpricedModels = AiUsageEvent::query()
            ->where('pricing_status', 'unpriced')
            ->select(['provider', 'model'])
            ->distinct()
            ->orderBy('model')
            ->get()
            ->map(static fn (AiUsageEvent $row): array => [
                'provider' => $row->provider,
                'model' => $row->model,
            ])
            ->values()
            ->all();

        $distinctModels = AiUsageEvent::query()
            ->select('model')
            ->distinct()
            ->orderBy('model')
            ->pluck('model')
            ->all();

        return [
            'filters' => $filters,
            'summary' => $this->buildSummary($events, $priceMap, $previousCost, $costDeltaPercent),
            'daily' => $this->buildDailySeries($events, $windowStart, $windowEnd),
            'byFeature' => $this->buildByFeature($events),
            'byModel' => $this->buildByModel($events, $priceMap),
            'byUser' => $this->buildByUser($events),
            'byWorkspace' => $this->buildByWorkspace($events),
            'recentCalls' => $recentCalls,
            'filterOptions' => [
                'workspaces' => Team::query()->orderBy('name')->get(['id', 'name', 'slug'])->map(static fn (Team $team): array => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'slug' => $team->slug,
                ])->all(),
                'features' => AiUsageFeature::options(),
                'models' => $distinctModels,
                'statuses' => [
                    ['value' => 'completed', 'label' => 'Completed'],
                    ['value' => 'paused', 'label' => 'Paused (approval)'],
                    ['value' => 'failed', 'label' => 'Failed'],
                ],
            ],
            'catalog' => [
                'syncedModelsCount' => $prices->count(),
                'lastSyncedAt' => $lastSyncedAt instanceof CarbonInterface ? $lastSyncedAt->toIso8601String() : null,
                'unpricedModels' => $unpricedModels,
                'activeModels' => [
                    'assistant' => (string) (config('ai.assistant_model') ?: 'gpt-6.2-sol'),
                    'summary' => (string) (config('ai.summary_model') ?: 'gpt-6.2-sol'),
                ],
            ],
        ];
    }

    /**
     * Stream sanitized CSV rows matching the active filters.
     *
     * @param  array{
     *     range?: string,
     *     team_id?: int|null,
     *     user_id?: int|null,
     *     feature?: string|null,
     *     model?: string|null,
     *     status?: string|null,
     * }  $rawFilters
     * @return iterable<int, list<string|int>>
     */
    public function exportRows(array $rawFilters): iterable
    {
        $filters = $this->normalizeFilters($rawFilters);
        [$windowStart, $windowEnd] = $this->resolveWindow($filters['range']);

        yield [
            'Timestamp (UTC)',
            'Feature',
            'Workspace',
            'User',
            'Impersonated By',
            'Provider',
            'Model',
            'Status',
            'Steps',
            'Tool Calls',
            'Input Tokens',
            'Cache Read Tokens',
            'Output Tokens',
            'Total Tokens',
            'Cost (USD)',
            'Pricing Status',
            'Latency (ms)',
        ];

        foreach ($this->filteredQuery($filters, $windowStart, $windowEnd)
            ->with(['team:id,name', 'user:id,name,email', 'impersonator:id,name'])
            ->latest('occurred_at')
            ->latest('id')
            ->cursor() as $event) {
            yield [
                $event->occurred_at->toIso8601String(),
                $this->sanitizeCsvCell($event->feature->label()),
                $this->sanitizeCsvCell($event->team?->name ?? 'Unassigned'),
                $this->sanitizeCsvCell($event->user?->name ?? 'System'),
                $this->sanitizeCsvCell($event->impersonator?->name ?? ''),
                $this->sanitizeCsvCell($event->provider),
                $this->sanitizeCsvCell($event->model),
                $this->sanitizeCsvCell($event->status),
                $event->steps_count,
                $event->tool_calls_count,
                $event->input_tokens,
                $event->cache_read_tokens,
                $event->output_tokens,
                $event->total_tokens,
                number_format($event->cost_microdollars / 1_000_000, 6, '.', ''),
                $event->pricing_status,
                $event->duration_ms ?? '',
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $rawFilters
     * @return array{range: string, team_id: ?int, user_id: ?int, feature: ?string, model: ?string, status: ?string}
     */
    public function normalizeFilters(array $rawFilters): array
    {
        $range = is_string($rawFilters['range'] ?? null) && in_array($rawFilters['range'], ['7d', '30d', '90d', 'all'], true)
            ? $rawFilters['range']
            : '30d';

        return [
            'range' => $range,
            'team_id' => is_numeric($rawFilters['team_id'] ?? null) ? (int) $rawFilters['team_id'] : null,
            'user_id' => is_numeric($rawFilters['user_id'] ?? null) ? (int) $rawFilters['user_id'] : null,
            'feature' => is_string($rawFilters['feature'] ?? null) && $rawFilters['feature'] !== '' ? $rawFilters['feature'] : null,
            'model' => is_string($rawFilters['model'] ?? null) && $rawFilters['model'] !== '' ? $rawFilters['model'] : null,
            'status' => is_string($rawFilters['status'] ?? null) && $rawFilters['status'] !== '' ? $rawFilters['status'] : null,
        ];
    }

    /**
     * @return array{0: ?CarbonImmutable, 1: CarbonImmutable, 2: ?CarbonImmutable, 3: ?CarbonImmutable}
     */
    private function resolveWindow(string $range): array
    {
        $end = CarbonImmutable::now()->endOfDay();

        if ($range === 'all') {
            return [null, $end, null, null];
        }

        $days = self::RangeDays[$range];
        $start = $end->subDays($days - 1)->startOfDay();
        $prevEnd = $start->subSecond();
        $prevStart = $start->subDays($days);

        return [$start, $end, $prevStart, $prevEnd];
    }

    /**
     * @param  array{range: string, team_id: ?int, user_id: ?int, feature: ?string, model: ?string, status: ?string}  $filters
     * @return Builder<AiUsageEvent>
     */
    private function filteredQuery(array $filters, ?CarbonImmutable $start, CarbonImmutable $end): Builder
    {
        return AiUsageEvent::query()
            ->when($start !== null, static fn (Builder $query): Builder => $query->whereBetween('occurred_at', [$start, $end]))
            ->when($start === null, static fn (Builder $query): Builder => $query->where('occurred_at', '<=', $end))
            ->when($filters['team_id'] !== null, static fn (Builder $query): Builder => $query->where('team_id', $filters['team_id']))
            ->when($filters['user_id'] !== null, static fn (Builder $query): Builder => $query->where('user_id', $filters['user_id']))
            ->when($filters['feature'] !== null, static fn (Builder $query): Builder => $query->where('feature', $filters['feature']))
            ->when($filters['model'] !== null, static fn (Builder $query): Builder => $query->where('model', $filters['model']))
            ->when($filters['status'] !== null, static fn (Builder $query): Builder => $query->where('status', $filters['status']));
    }

    /**
     * @param  Collection<int, AiModelPrice>  $prices
     * @return array<string, AiModelPrice>
     */
    private function buildPriceMap(Collection $prices): array
    {
        $map = [];

        foreach ($prices as $price) {
            $map[$price->provider.'|'.$price->model_id] = $price;
        }

        return $map;
    }

    /**
     * @param  array<string, AiModelPrice>  $priceMap
     */
    private function matchPrice(string $provider, string $model, array $priceMap): ?AiModelPrice
    {
        $normalized = $this->calculator->normalizeProvider($provider);

        return $priceMap[$normalized.'|'.$model] ?? $this->calculator->resolvePrice($normalized, $model);
    }

    /**
     * @param  Collection<int, AiUsageEvent>  $events
     * @param  array<string, AiModelPrice>  $priceMap
     * @return array<string, mixed>
     */
    private function buildSummary(Collection $events, array $priceMap, ?int $previousCost, ?float $costDeltaPercent): array
    {
        $totalCalls = $events->count();
        $completedCalls = $events->where('status', 'completed')->count();
        $pausedCalls = $events->where('status', 'paused')->count();
        $failedCalls = $events->where('status', 'failed')->count();
        $succeededCalls = $completedCalls + $pausedCalls;

        $totalCost = (int) $events->sum('cost_microdollars');
        $inputCost = (int) $events->sum('input_cost_microdollars');
        $outputCost = (int) $events->sum('output_cost_microdollars');
        $cacheCost = (int) $events->sum('cache_cost_microdollars');

        $totalInput = (int) $events->sum('input_tokens');
        $uncachedInput = (int) $events->sum('uncached_input_tokens');
        $cacheRead = (int) $events->sum('cache_read_tokens');
        $cacheWrite = (int) $events->sum('cache_write_tokens');
        $totalOutput = (int) $events->sum('output_tokens');
        $reasoningTokens = (int) $events->sum('reasoning_tokens');
        $totalTokens = (int) $events->sum('total_tokens');

        $cacheSavingsMicrodollars = 0;
        foreach ($events as $event) {
            if ($event->cache_read_tokens <= 0 || $event->pricing_status !== 'priced') {
                continue;
            }

            $price = $this->matchPrice($event->provider, $event->model, $priceMap);
            if ($price === null || $price->cache_read_cost_per_million === null) {
                continue;
            }

            $savedRate = max(0.0, $price->input_cost_per_million - $price->cache_read_cost_per_million);
            $cacheSavingsMicrodollars += (int) round($event->cache_read_tokens * $savedRate);
        }

        $latencies = $events->pluck('duration_ms')->filter(static fn (mixed $value): bool => is_int($value));

        return [
            'totalCostMicrodollars' => $totalCost,
            'previousCostMicrodollars' => $previousCost,
            'costDeltaPercent' => $costDeltaPercent,
            'inputCostMicrodollars' => $inputCost,
            'outputCostMicrodollars' => $outputCost,
            'cacheCostMicrodollars' => $cacheCost,
            'estimatedCacheSavingsMicrodollars' => $cacheSavingsMicrodollars,
            'totalCalls' => $totalCalls,
            'completedCalls' => $completedCalls,
            'pausedCalls' => $pausedCalls,
            'failedCalls' => $failedCalls,
            'successRatePercent' => $totalCalls > 0 ? round(($succeededCalls / $totalCalls) * 100, 1) : 100.0,
            'totalInputTokens' => $totalInput,
            'uncachedInputTokens' => $uncachedInput,
            'cacheReadTokens' => $cacheRead,
            'cacheWriteTokens' => $cacheWrite,
            'totalOutputTokens' => $totalOutput,
            'reasoningTokens' => $reasoningTokens,
            'totalTokens' => $totalTokens,
            'cacheHitRatePercent' => $totalInput > 0 ? round(($cacheRead / $totalInput) * 100, 1) : 0.0,
            'avgCostMicrodollars' => $totalCalls > 0 ? (int) round($totalCost / $totalCalls) : 0,
            'avgDurationMs' => $latencies->isNotEmpty() ? (int) round($latencies->avg()) : null,
            'avgStepsPerCall' => $totalCalls > 0 ? round($events->sum('steps_count') / $totalCalls, 1) : 0.0,
            'totalToolCalls' => (int) $events->sum('tool_calls_count'),
            'activeUsers' => $events->pluck('user_id')->filter()->unique()->count(),
            'activeWorkspaces' => $events->pluck('team_id')->filter()->unique()->count(),
            'unpricedCalls' => $events->where('pricing_status', 'unpriced')->count(),
        ];
    }

    /**
     * @param  Collection<int, AiUsageEvent>  $events
     * @return list<array<string, mixed>>
     */
    private function buildDailySeries(Collection $events, ?CarbonImmutable $windowStart, CarbonImmutable $windowEnd): array
    {
        if ($events->isEmpty() && $windowStart === null) {
            return [];
        }

        $grouped = $events->groupBy(static fn (AiUsageEvent $event): string => $event->occurred_at->toDateString());
        $startDay = $windowStart ?? CarbonImmutable::parse((string) $grouped->keys()->min())->startOfDay();
        $endDay = $windowEnd->startOfDay();

        $series = [];
        for ($cursor = $startDay; $cursor->lessThanOrEqualTo($endDay); $cursor = $cursor->addDay()) {
            $date = $cursor->toDateString();
            /** @var Collection<int, AiUsageEvent> $dayEvents */
            $dayEvents = $grouped->get($date, collect());

            $series[] = [
                'date' => $date,
                'label' => $cursor->format('j M'),
                'costMicrodollars' => (int) $dayEvents->sum('cost_microdollars'),
                'inputCostMicrodollars' => (int) $dayEvents->sum('input_cost_microdollars'),
                'outputCostMicrodollars' => (int) $dayEvents->sum('output_cost_microdollars'),
                'cacheCostMicrodollars' => (int) $dayEvents->sum('cache_cost_microdollars'),
                'calls' => $dayEvents->count(),
                'failedCalls' => $dayEvents->where('status', 'failed')->count(),
                'inputTokens' => (int) $dayEvents->sum('input_tokens'),
                'outputTokens' => (int) $dayEvents->sum('output_tokens'),
                'cacheReadTokens' => (int) $dayEvents->sum('cache_read_tokens'),
                'totalTokens' => (int) $dayEvents->sum('total_tokens'),
            ];
        }

        return $series;
    }

    /**
     * @param  Collection<int, AiUsageEvent>  $events
     * @return list<array<string, mixed>>
     */
    private function buildByFeature(Collection $events): array
    {
        $totalCost = max(1, (int) $events->sum('cost_microdollars'));

        return $events
            ->groupBy(static fn (AiUsageEvent $event): string => $event->feature->value)
            ->map(function (Collection $group, string $featureValue) use ($totalCost): array {
                $feature = AiUsageFeature::tryFrom($featureValue) ?? AiUsageFeature::Other;
                $cost = (int) $group->sum('cost_microdollars');

                return [
                    'feature' => $feature->value,
                    'label' => $feature->label(),
                    'calls' => $group->count(),
                    'failedCalls' => $group->where('status', 'failed')->count(),
                    'toolCalls' => (int) $group->sum('tool_calls_count'),
                    'inputTokens' => (int) $group->sum('input_tokens'),
                    'outputTokens' => (int) $group->sum('output_tokens'),
                    'totalTokens' => (int) $group->sum('total_tokens'),
                    'costMicrodollars' => $cost,
                    'sharePercent' => round(($cost / $totalCost) * 100, 1),
                ];
            })
            ->sortByDesc('calls')
            ->sortByDesc('costMicrodollars')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, AiUsageEvent>  $events
     * @param  array<string, AiModelPrice>  $priceMap
     * @return list<array<string, mixed>>
     */
    private function buildByModel(Collection $events, array $priceMap): array
    {
        $totalCost = max(1, (int) $events->sum('cost_microdollars'));

        return $events
            ->groupBy(static fn (AiUsageEvent $event): string => $event->provider.'|'.$event->model)
            ->map(function (Collection $group) use ($totalCost, $priceMap): array {
                /** @var AiUsageEvent $first */
                $first = $group->first();
                $price = $this->matchPrice($first->provider, $first->model, $priceMap);
                $cost = (int) $group->sum('cost_microdollars');

                return [
                    'provider' => $first->provider,
                    'model' => $first->model,
                    'displayName' => $price?->display_name ?? $first->model,
                    'calls' => $group->count(),
                    'inputTokens' => (int) $group->sum('input_tokens'),
                    'outputTokens' => (int) $group->sum('output_tokens'),
                    'cacheReadTokens' => (int) $group->sum('cache_read_tokens'),
                    'totalTokens' => (int) $group->sum('total_tokens'),
                    'costMicrodollars' => $cost,
                    'sharePercent' => round(($cost / $totalCost) * 100, 1),
                    'inputPerMillion' => $price?->input_cost_per_million,
                    'outputPerMillion' => $price?->output_cost_per_million,
                ];
            })
            ->sortByDesc('calls')
            ->sortByDesc('costMicrodollars')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, AiUsageEvent>  $events
     * @return list<array<string, mixed>>
     */
    private function buildByUser(Collection $events): array
    {
        $totalCost = max(1, (int) $events->sum('cost_microdollars'));
        $userIds = $events->pluck('user_id')->filter()->unique()->values()->all();
        $users = $userIds !== []
            ? User::query()->whereIn('id', $userIds)->get(['id', 'name', 'email'])->keyBy('id')
            : collect();

        return $events
            ->groupBy(static fn (AiUsageEvent $event): string => $event->user_id !== null ? (string) $event->user_id : 'system')
            ->map(function (Collection $group, string $key) use ($users, $totalCost): array {
                $user = $key !== 'system' ? $users->get((int) $key) : null;
                $cost = (int) $group->sum('cost_microdollars');
                $calls = $group->count();

                return [
                    'userId' => $user?->id,
                    'name' => $user?->name ?? 'System / Unattributed',
                    'email' => $user?->email,
                    'calls' => $calls,
                    'inputTokens' => (int) $group->sum('input_tokens'),
                    'outputTokens' => (int) $group->sum('output_tokens'),
                    'totalTokens' => (int) $group->sum('total_tokens'),
                    'avgCostMicrodollars' => (int) round($cost / max(1, $calls)),
                    'costMicrodollars' => $cost,
                    'sharePercent' => round(($cost / $totalCost) * 100, 1),
                ];
            })
            ->sortByDesc('calls')
            ->sortByDesc('costMicrodollars')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, AiUsageEvent>  $events
     * @return list<array<string, mixed>>
     */
    private function buildByWorkspace(Collection $events): array
    {
        $totalCost = max(1, (int) $events->sum('cost_microdollars'));
        $teamIds = $events->pluck('team_id')->filter()->unique()->values()->all();
        $teams = $teamIds !== []
            ? Team::query()->whereIn('id', $teamIds)->with('settings')->get()->keyBy('id')
            : collect();

        return $events
            ->groupBy(static fn (AiUsageEvent $event): string => $event->team_id !== null ? (string) $event->team_id : 'unassigned')
            ->map(function (Collection $group, string $key) use ($teams, $totalCost): array {
                $team = $key !== 'unassigned' ? $teams->get((int) $key) : null;
                $cost = (int) $group->sum('cost_microdollars');

                return [
                    'teamId' => $team?->id,
                    'name' => $team?->name ?? 'Unassigned Workspace',
                    'slug' => $team?->slug,
                    'planLabel' => $team instanceof Team ? $this->plans->handle($team)->label() : null,
                    'activeUsers' => $group->pluck('user_id')->filter()->unique()->count(),
                    'calls' => $group->count(),
                    'totalTokens' => (int) $group->sum('total_tokens'),
                    'costMicrodollars' => $cost,
                    'sharePercent' => round(($cost / $totalCost) * 100, 1),
                ];
            })
            ->sortByDesc('calls')
            ->sortByDesc('costMicrodollars')
            ->values()
            ->all();
    }

    private function sanitizeCsvCell(string $value): string
    {
        if ($value !== '' && str_contains("=+-@\t\r", $value[0])) {
            return "'".$value;
        }

        return $value;
    }
}
