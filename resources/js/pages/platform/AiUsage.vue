<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    Coins,
    Cpu,
    Download,
    History,
    RefreshCw,
    Sparkles,
    Users,
    Zap,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AiDailyUsageChart from '@/components/platform/AiDailyUsageChart.vue';
import type { AiDailyUsagePoint } from '@/components/platform/AiDailyUsageChart.vue';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    formatDurationMs,
    formatRatePerMillion,
    formatTokenCount,
    formatUsdFromMicrodollars,
} from '@/lib/aiUsageFormat';
import {
    backfill,
    exportMethod,
    index,
    syncPricing,
} from '@/routes/platform/ai-usage';

type Filters = {
    range: '7d' | '30d' | '90d' | 'all';
    team_id: number | null;
    user_id: number | null;
    feature: string | null;
    model: string | null;
    status: string | null;
};

type Summary = {
    totalCostMicrodollars: number;
    previousCostMicrodollars: number | null;
    costDeltaPercent: number | null;
    inputCostMicrodollars: number;
    outputCostMicrodollars: number;
    cacheCostMicrodollars: number;
    estimatedCacheSavingsMicrodollars: number;
    totalCalls: number;
    completedCalls: number;
    pausedCalls: number;
    failedCalls: number;
    successRatePercent: number;
    totalInputTokens: number;
    uncachedInputTokens: number;
    cacheReadTokens: number;
    cacheWriteTokens: number;
    totalOutputTokens: number;
    reasoningTokens: number;
    totalTokens: number;
    cacheHitRatePercent: number;
    avgCostMicrodollars: number;
    avgDurationMs: number | null;
    avgStepsPerCall: number;
    totalToolCalls: number;
    activeUsers: number;
    activeWorkspaces: number;
    unpricedCalls: number;
};

type FeatureBreakdown = {
    feature: string;
    label: string;
    calls: number;
    failedCalls: number;
    toolCalls: number;
    inputTokens: number;
    outputTokens: number;
    totalTokens: number;
    costMicrodollars: number;
    sharePercent: number;
};

type ModelBreakdown = {
    provider: string;
    model: string;
    displayName: string;
    calls: number;
    inputTokens: number;
    outputTokens: number;
    cacheReadTokens: number;
    totalTokens: number;
    costMicrodollars: number;
    sharePercent: number;
    inputPerMillion: number | null;
    outputPerMillion: number | null;
};

type UserBreakdown = {
    userId: number | null;
    name: string;
    email: string | null;
    calls: number;
    inputTokens: number;
    outputTokens: number;
    totalTokens: number;
    avgCostMicrodollars: number;
    costMicrodollars: number;
    sharePercent: number;
};

type WorkspaceBreakdown = {
    teamId: number | null;
    name: string;
    slug: string | null;
    planLabel: string | null;
    activeUsers: number;
    calls: number;
    totalTokens: number;
    costMicrodollars: number;
    sharePercent: number;
};

type RecentCall = {
    id: number;
    feature: string;
    featureLabel: string;
    provider: string;
    model: string;
    status: string;
    isStreamed: boolean;
    stepsCount: number;
    toolCallsCount: number;
    inputTokens: number;
    cacheReadTokens: number;
    outputTokens: number;
    totalTokens: number;
    costMicrodollars: number;
    pricingStatus: string;
    durationMs: number | null;
    errorMessage: string | null;
    occurredAt: string;
    occurredAtFormatted: string;
    user: { id: number; name: string; email: string | null } | null;
    impersonator: { id: number; name: string } | null;
    team: { id: number; name: string; slug: string } | null;
};

type PaginatedCalls = {
    data: RecentCall[];
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

const props = defineProps<{
    filters: Filters;
    summary: Summary;
    daily: AiDailyUsagePoint[];
    byFeature: FeatureBreakdown[];
    byModel: ModelBreakdown[];
    byUser: UserBreakdown[];
    byWorkspace: WorkspaceBreakdown[];
    recentCalls: PaginatedCalls;
    filterOptions: {
        workspaces: { id: number; name: string; slug: string }[];
        features: { value: string; label: string }[];
        models: string[];
        statuses: { value: string; label: string }[];
    };
    catalog: {
        syncedModelsCount: number;
        lastSyncedAt: string | null;
        unpricedModels: { provider: string; model: string }[];
        activeModels: { assistant: string; summary: string };
    };
}>();

const syncing = ref(false);
const backfilling = ref(false);

const activeQuery = computed(() => {
    const query: Record<string, string | number> = {
        range: props.filters.range,
    };

    if (props.filters.team_id) {
        query.team_id = props.filters.team_id;
    }

    if (props.filters.user_id) {
        query.user_id = props.filters.user_id;
    }

    if (props.filters.feature) {
        query.feature = props.filters.feature;
    }

    if (props.filters.model) {
        query.model = props.filters.model;
    }

    if (props.filters.status) {
        query.status = props.filters.status;
    }

    return query;
});

const hasSecondaryFilters = computed(() =>
    Boolean(
        props.filters.team_id ||
        props.filters.user_id ||
        props.filters.feature ||
        props.filters.model ||
        props.filters.status ||
        props.filters.range !== '30d',
    ),
);

const exportHref = computed(() =>
    exportMethod.url({ query: activeQuery.value }),
);

function applyFilter(patch: Partial<Filters>): void {
    const next: Filters = {
        ...props.filters,
        ...patch,
    };

    const query: Record<string, string | number> = {
        range: next.range,
    };

    if (next.team_id) {
        query.team_id = next.team_id;
    }

    if (next.user_id) {
        query.user_id = next.user_id;
    }

    if (next.feature) {
        query.feature = next.feature;
    }

    if (next.model) {
        query.model = next.model;
    }

    if (next.status) {
        query.status = next.status;
    }

    router.get(index.url({ query }), {}, { preserveScroll: true });
}

function resetFilters(): void {
    router.get(
        index.url({ query: { range: '30d' } }),
        {},
        { preserveScroll: true },
    );
}

function triggerSyncPricing(): void {
    if (syncing.value) {
        return;
    }

    syncing.value = true;
    router.post(
        syncPricing().url,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                syncing.value = false;
            },
        },
    );
}

function triggerBackfill(): void {
    if (backfilling.value) {
        return;
    }

    backfilling.value = true;
    router.post(
        backfill().url,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                backfilling.value = false;
            },
        },
    );
}

function statusBadgeVariant(
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    switch (status) {
        case 'completed':
            return 'default';
        case 'paused':
            return 'secondary';
        case 'failed':
            return 'destructive';
        default:
            return 'outline';
    }
}

const lastSyncedText = computed(() => {
    if (!props.catalog.lastSyncedAt) {
        return 'Catalog not synced yet';
    }

    return `${props.catalog.syncedModelsCount} models · synced ${new Date(props.catalog.lastSyncedAt).toLocaleString()}`;
});
</script>

<template>
    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 md:p-8">
        <Head title="AI usage analytics" />

        <header
            class="rounded-2xl bg-primary p-6 text-primary-foreground md:p-8"
        >
            <div
                class="flex flex-col justify-between gap-4 lg:flex-row lg:items-end"
            >
                <div class="space-y-2">
                    <div class="flex items-center gap-2 text-sm opacity-85">
                        <Sparkles class="size-4" />
                        <span>AI Telemetry &amp; Cost Attribution</span>
                    </div>
                    <h1
                        class="text-2xl font-semibold tracking-tight md:text-3xl"
                    >
                        AI Usage Analytics
                    </h1>
                    <p class="max-w-2xl text-sm opacity-85">
                        Track token consumption, agent calls, latency, and USD
                        spend calculated from <code>models.dev</code>. Active
                        model:
                        <strong class="font-medium">{{
                            catalog.activeModels.assistant
                        }}</strong>
                        ({{ lastSyncedText }}).
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        :disabled="syncing"
                        data-testid="sync-models-dev-button"
                        @click="triggerSyncPricing"
                    >
                        <RefreshCw
                            class="mr-1.5 size-3.5"
                            :class="{ 'animate-spin': syncing }"
                        />
                        {{ syncing ? 'Syncing…' : 'Sync models.dev prices' }}
                    </Button>

                    <Button
                        type="button"
                        variant="secondary"
                        size="sm"
                        :disabled="backfilling"
                        data-testid="backfill-ai-usage-button"
                        @click="triggerBackfill"
                    >
                        <History class="mr-1.5 size-3.5" />
                        {{
                            backfilling
                                ? 'Importing…'
                                : 'Backfill assistant history'
                        }}
                    </Button>

                    <Button as-child variant="secondary" size="sm">
                        <a :href="exportHref" data-testid="export-ai-usage-csv">
                            <Download class="mr-1.5 size-3.5" />
                            Export CSV
                        </a>
                    </Button>
                </div>
            </div>
        </header>

        <Alert
            v-if="summary.unpricedCalls > 0"
            class="border-amber-500/40 bg-amber-500/10 text-amber-950 dark:text-amber-100"
        >
            <AlertTriangle class="size-4 text-amber-600 dark:text-amber-400" />
            <AlertDescription
                class="flex flex-wrap items-center justify-between gap-2 text-xs"
            >
                <span>
                    {{ summary.unpricedCalls }} recorded call(s) use unpriced
                    models ({{
                        catalog.unpricedModels
                            .map((item) => `${item.provider}/${item.model}`)
                            .join(', ')
                    }}). Sync <code>models.dev</code> pricing to calculate exact
                    costs retroactively.
                </span>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    :disabled="syncing"
                    @click="triggerSyncPricing"
                >
                    Sync &amp; Reprice
                </Button>
            </AlertDescription>
        </Alert>

        <section
            aria-label="Analytics filters"
            class="flex flex-wrap items-end gap-3 rounded-xl border bg-card p-4"
        >
            <div class="flex flex-col gap-1.5">
                <span
                    class="text-[11px] font-medium tracking-wide text-muted-foreground uppercase"
                >
                    Window
                </span>
                <div class="inline-flex rounded-lg border bg-muted/40 p-0.5">
                    <Button
                        v-for="option in [
                            { id: '7d', label: '7D' },
                            { id: '30d', label: '30D' },
                            { id: '90d', label: '90D' },
                            { id: 'all', label: 'All' },
                        ] as const"
                        :key="option.id"
                        type="button"
                        size="sm"
                        :variant="
                            filters.range === option.id ? 'default' : 'ghost'
                        "
                        class="h-7 px-2.5 text-xs"
                        @click="applyFilter({ range: option.id })"
                    >
                        {{ option.label }}
                    </Button>
                </div>
            </div>

            <div class="flex min-w-[10rem] flex-1 flex-col gap-1.5">
                <Label for="filter-feature" class="text-xs">Feature</Label>
                <select
                    id="filter-feature"
                    :value="filters.feature ?? ''"
                    class="h-9 rounded-md border border-input bg-background px-2.5 text-xs"
                    @change="
                        applyFilter({
                            feature:
                                ($event.target as HTMLSelectElement).value ||
                                null,
                        })
                    "
                >
                    <option value="">All features</option>
                    <option
                        v-for="item in filterOptions.features"
                        :key="item.value"
                        :value="item.value"
                    >
                        {{ item.label }}
                    </option>
                </select>
            </div>

            <div class="flex min-w-[10rem] flex-1 flex-col gap-1.5">
                <Label for="filter-model" class="text-xs">Model</Label>
                <select
                    id="filter-model"
                    :value="filters.model ?? ''"
                    class="h-9 rounded-md border border-input bg-background px-2.5 text-xs"
                    @change="
                        applyFilter({
                            model:
                                ($event.target as HTMLSelectElement).value ||
                                null,
                        })
                    "
                >
                    <option value="">All models</option>
                    <option
                        v-for="modelName in filterOptions.models"
                        :key="modelName"
                        :value="modelName"
                    >
                        {{ modelName }}
                    </option>
                </select>
            </div>

            <div class="flex min-w-[10rem] flex-1 flex-col gap-1.5">
                <Label for="filter-workspace" class="text-xs">Workspace</Label>
                <select
                    id="filter-workspace"
                    :value="filters.team_id ? String(filters.team_id) : ''"
                    class="h-9 rounded-md border border-input bg-background px-2.5 text-xs"
                    @change="
                        applyFilter({
                            team_id: ($event.target as HTMLSelectElement).value
                                ? Number(
                                      ($event.target as HTMLSelectElement)
                                          .value,
                                  )
                                : null,
                        })
                    "
                >
                    <option value="">All workspaces</option>
                    <option
                        v-for="workspace in filterOptions.workspaces"
                        :key="workspace.id"
                        :value="String(workspace.id)"
                    >
                        {{ workspace.name }}
                    </option>
                </select>
            </div>

            <div class="flex min-w-[8.5rem] flex-col gap-1.5">
                <Label for="filter-status" class="text-xs">Status</Label>
                <select
                    id="filter-status"
                    :value="filters.status ?? ''"
                    class="h-9 rounded-md border border-input bg-background px-2.5 text-xs"
                    @change="
                        applyFilter({
                            status:
                                ($event.target as HTMLSelectElement).value ||
                                null,
                        })
                    "
                >
                    <option value="">All statuses</option>
                    <option
                        v-for="item in filterOptions.statuses"
                        :key="item.value"
                        :value="item.value"
                    >
                        {{ item.label }}
                    </option>
                </select>
            </div>

            <Button
                v-if="hasSecondaryFilters"
                type="button"
                variant="outline"
                size="sm"
                class="h-9"
                @click="resetFilters"
            >
                Reset
            </Button>
        </section>

        <section
            aria-label="Key AI usage metrics"
            class="grid grid-cols-2 gap-3 lg:grid-cols-3 xl:grid-cols-6"
        >
            <article class="rounded-xl border bg-card p-4">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-muted-foreground"
                        >Total Spend</span
                    >
                    <Coins class="size-4 text-primary" />
                </div>
                <div class="mt-2 text-2xl font-semibold tabular-nums">
                    {{
                        formatUsdFromMicrodollars(summary.totalCostMicrodollars)
                    }}
                </div>
                <p class="mt-1 text-xs text-muted-foreground tabular-nums">
                    <span v-if="summary.costDeltaPercent !== null">
                        {{ summary.costDeltaPercent > 0 ? '+' : ''
                        }}{{ summary.costDeltaPercent }}% vs prior window
                    </span>
                    <span v-else>
                        In
                        {{
                            formatUsdFromMicrodollars(
                                summary.inputCostMicrodollars,
                            )
                        }}
                        · Out
                        {{
                            formatUsdFromMicrodollars(
                                summary.outputCostMicrodollars,
                            )
                        }}
                    </span>
                </p>
            </article>

            <article class="rounded-xl border bg-card p-4">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-muted-foreground"
                        >Agent Calls</span
                    >
                    <Activity class="size-4 text-primary" />
                </div>
                <div class="mt-2 text-2xl font-semibold tabular-nums">
                    {{ summary.totalCalls.toLocaleString() }}
                </div>
                <p class="mt-1 text-xs text-muted-foreground tabular-nums">
                    {{ summary.successRatePercent }}% success ·
                    {{ summary.avgStepsPerCall }} avg steps ·
                    {{ summary.totalToolCalls }} tools
                </p>
            </article>

            <article class="rounded-xl border bg-card p-4">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-muted-foreground"
                        >Total Tokens</span
                    >
                    <Cpu class="size-4 text-primary" />
                </div>
                <div class="mt-2 text-2xl font-semibold tabular-nums">
                    {{ formatTokenCount(summary.totalTokens) }}
                </div>
                <p class="mt-1 text-xs text-muted-foreground tabular-nums">
                    In {{ formatTokenCount(summary.totalInputTokens) }} · Out
                    {{ formatTokenCount(summary.totalOutputTokens) }}
                </p>
            </article>

            <article class="rounded-xl border bg-card p-4">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-muted-foreground">
                        Cache Efficiency
                    </span>
                    <Zap class="size-4 text-emerald-500" />
                </div>
                <div class="mt-2 text-2xl font-semibold tabular-nums">
                    {{ summary.cacheHitRatePercent }}%
                </div>
                <p class="mt-1 text-xs text-muted-foreground tabular-nums">
                    Saved ~{{
                        formatUsdFromMicrodollars(
                            summary.estimatedCacheSavingsMicrodollars,
                        )
                    }}
                    ({{ formatTokenCount(summary.cacheReadTokens) }} read)
                </p>
            </article>

            <article class="rounded-xl border bg-card p-4">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-muted-foreground">
                        Avg Call &amp; Speed
                    </span>
                    <Sparkles class="size-4 text-primary" />
                </div>
                <div class="mt-2 text-2xl font-semibold tabular-nums">
                    {{ formatUsdFromMicrodollars(summary.avgCostMicrodollars) }}
                </div>
                <p class="mt-1 text-xs text-muted-foreground tabular-nums">
                    Avg latency: {{ formatDurationMs(summary.avgDurationMs) }}
                </p>
            </article>

            <article class="rounded-xl border bg-card p-4">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-muted-foreground">Reach</span>
                    <Users class="size-4 text-primary" />
                </div>
                <div class="mt-2 text-2xl font-semibold tabular-nums">
                    {{ summary.activeUsers }}
                    <span class="text-sm font-normal text-muted-foreground">
                        users
                    </span>
                </div>
                <p class="mt-1 text-xs text-muted-foreground tabular-nums">
                    Across {{ summary.activeWorkspaces }} workspace(s)
                </p>
            </article>
        </section>

        <section class="rounded-xl border bg-card p-5">
            <div class="mb-4">
                <h2 class="text-base font-semibold">
                    Cost &amp; Usage Per Day
                </h2>
                <p class="text-xs text-muted-foreground">
                    Daily spend, token volume, and agent invocations across the
                    selected window.
                </p>
            </div>
            <AiDailyUsageChart :days="daily" />
        </section>

        <section class="grid gap-6 lg:grid-cols-2">
            <article class="rounded-xl border bg-card p-5">
                <h2 class="text-base font-semibold">Cost by Feature</h2>
                <p class="mb-4 text-xs text-muted-foreground">
                    Spend and token volume attributed to each AI workflow.
                </p>
                <p
                    v-if="!byFeature.length"
                    class="rounded-lg border border-dashed p-6 text-center text-xs text-muted-foreground"
                >
                    No feature usage recorded in this window.
                </p>
                <div v-else class="space-y-3">
                    <div
                        v-for="row in byFeature"
                        :key="row.feature"
                        class="space-y-1.5"
                    >
                        <div
                            class="flex items-center justify-between gap-2 text-sm"
                        >
                            <span class="font-medium">{{ row.label }}</span>
                            <span class="font-semibold tabular-nums">
                                {{
                                    formatUsdFromMicrodollars(
                                        row.costMicrodollars,
                                    )
                                }}
                                <span
                                    class="ml-1 text-xs font-normal text-muted-foreground"
                                >
                                    ({{ row.sharePercent }}%)
                                </span>
                            </span>
                        </div>
                        <div
                            class="h-2 w-full overflow-hidden rounded-full bg-muted"
                        >
                            <div
                                class="h-full rounded-full bg-primary transition-all"
                                :style="{
                                    width: `${Math.max(4, Math.min(100, row.sharePercent))}%`,
                                }"
                            />
                        </div>
                        <p class="text-xs text-muted-foreground tabular-nums">
                            {{ row.calls }} calls ·
                            {{ formatTokenCount(row.totalTokens) }} tokens ·
                            {{ row.toolCalls }} tool calls
                        </p>
                    </div>
                </div>
            </article>

            <article class="rounded-xl border bg-card p-5">
                <h2 class="text-base font-semibold">Cost by Model</h2>
                <p class="mb-4 text-xs text-muted-foreground">
                    Per-model token volume and <code>models.dev</code> unit
                    rates (USD per 1M tokens).
                </p>
                <p
                    v-if="!byModel.length"
                    class="rounded-lg border border-dashed p-6 text-center text-xs text-muted-foreground"
                >
                    No model calls recorded in this window.
                </p>
                <div
                    v-else
                    role="region"
                    tabindex="0"
                    aria-label="Cost by model table"
                    class="overflow-x-auto"
                >
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b text-muted-foreground">
                                <th scope="col" class="py-2 pr-3 font-medium">
                                    Model
                                </th>
                                <th
                                    scope="col"
                                    class="px-2 py-2 text-right font-medium"
                                >
                                    Calls
                                </th>
                                <th
                                    scope="col"
                                    class="px-2 py-2 text-right font-medium"
                                >
                                    Tokens
                                </th>
                                <th
                                    scope="col"
                                    class="py-2 pl-2 text-right font-medium"
                                >
                                    Cost
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="row in byModel"
                                :key="`${row.provider}-${row.model}`"
                            >
                                <td class="py-2.5 pr-3">
                                    <div class="font-medium text-foreground">
                                        {{ row.model }}
                                    </div>
                                    <div
                                        class="text-[11px] text-muted-foreground"
                                    >
                                        {{ row.provider }} · In
                                        {{
                                            formatRatePerMillion(
                                                row.inputPerMillion,
                                            )
                                        }}
                                        / Out
                                        {{
                                            formatRatePerMillion(
                                                row.outputPerMillion,
                                            )
                                        }}
                                    </div>
                                </td>
                                <td class="px-2 py-2.5 text-right tabular-nums">
                                    {{ row.calls }}
                                </td>
                                <td class="px-2 py-2.5 text-right tabular-nums">
                                    {{ formatTokenCount(row.totalTokens) }}
                                </td>
                                <td
                                    class="py-2.5 pl-2 text-right font-semibold tabular-nums"
                                >
                                    {{
                                        formatUsdFromMicrodollars(
                                            row.costMicrodollars,
                                        )
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="rounded-xl border bg-card p-5">
                <h2 class="text-base font-semibold">Cost by User</h2>
                <p class="mb-4 text-xs text-muted-foreground">
                    Click a user to filter the dashboard to their calls.
                </p>
                <p
                    v-if="!byUser.length"
                    class="rounded-lg border border-dashed p-6 text-center text-xs text-muted-foreground"
                >
                    No user calls recorded in this window.
                </p>
                <div
                    v-else
                    role="region"
                    tabindex="0"
                    aria-label="Cost by user table"
                    class="overflow-x-auto"
                >
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b text-muted-foreground">
                                <th scope="col" class="py-2 pr-3 font-medium">
                                    User
                                </th>
                                <th
                                    scope="col"
                                    class="px-2 py-2 text-right font-medium"
                                >
                                    Calls
                                </th>
                                <th
                                    scope="col"
                                    class="px-2 py-2 text-right font-medium"
                                >
                                    Tokens
                                </th>
                                <th
                                    scope="col"
                                    class="py-2 pl-2 text-right font-medium"
                                >
                                    Cost
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="row in byUser"
                                :key="row.userId ?? 'system'"
                            >
                                <td class="py-2.5 pr-3">
                                    <button
                                        v-if="row.userId"
                                        type="button"
                                        class="text-left font-medium text-foreground underline-offset-2 hover:underline"
                                        @click="
                                            applyFilter({ user_id: row.userId })
                                        "
                                    >
                                        {{ row.name }}
                                    </button>
                                    <span v-else class="font-medium">
                                        {{ row.name }}
                                    </span>
                                    <div
                                        v-if="row.email"
                                        class="text-[11px] text-muted-foreground"
                                    >
                                        {{ row.email }}
                                    </div>
                                </td>
                                <td class="px-2 py-2.5 text-right tabular-nums">
                                    {{ row.calls }}
                                </td>
                                <td class="px-2 py-2.5 text-right tabular-nums">
                                    {{ formatTokenCount(row.totalTokens) }}
                                </td>
                                <td
                                    class="py-2.5 pl-2 text-right font-semibold tabular-nums"
                                >
                                    {{
                                        formatUsdFromMicrodollars(
                                            row.costMicrodollars,
                                        )
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="rounded-xl border bg-card p-5">
                <h2 class="text-base font-semibold">Cost by Workspace</h2>
                <p class="mb-4 text-xs text-muted-foreground">
                    Spend and active users grouped by farm workspace.
                </p>
                <p
                    v-if="!byWorkspace.length"
                    class="rounded-lg border border-dashed p-6 text-center text-xs text-muted-foreground"
                >
                    No workspace calls recorded in this window.
                </p>
                <div
                    v-else
                    role="region"
                    tabindex="0"
                    aria-label="Cost by workspace table"
                    class="overflow-x-auto"
                >
                    <table class="w-full text-left text-xs">
                        <thead>
                            <tr class="border-b text-muted-foreground">
                                <th scope="col" class="py-2 pr-3 font-medium">
                                    Workspace
                                </th>
                                <th
                                    scope="col"
                                    class="px-2 py-2 text-right font-medium"
                                >
                                    Calls
                                </th>
                                <th
                                    scope="col"
                                    class="px-2 py-2 text-right font-medium"
                                >
                                    Tokens
                                </th>
                                <th
                                    scope="col"
                                    class="py-2 pl-2 text-right font-medium"
                                >
                                    Cost
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            <tr
                                v-for="row in byWorkspace"
                                :key="row.teamId ?? 'unassigned'"
                            >
                                <td class="py-2.5 pr-3">
                                    <button
                                        v-if="row.teamId"
                                        type="button"
                                        class="text-left font-medium text-foreground underline-offset-2 hover:underline"
                                        @click="
                                            applyFilter({ team_id: row.teamId })
                                        "
                                    >
                                        {{ row.name }}
                                    </button>
                                    <span v-else class="font-medium">
                                        {{ row.name }}
                                    </span>
                                    <div
                                        class="text-[11px] text-muted-foreground"
                                    >
                                        {{ row.planLabel ?? '—' }} ·
                                        {{ row.activeUsers }} user(s)
                                    </div>
                                </td>
                                <td class="px-2 py-2.5 text-right tabular-nums">
                                    {{ row.calls }}
                                </td>
                                <td class="px-2 py-2.5 text-right tabular-nums">
                                    {{ formatTokenCount(row.totalTokens) }}
                                </td>
                                <td
                                    class="py-2.5 pl-2 text-right font-semibold tabular-nums"
                                >
                                    {{
                                        formatUsdFromMicrodollars(
                                            row.costMicrodollars,
                                        )
                                    }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        <section class="rounded-xl border bg-card p-5">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="text-base font-semibold">Recent AI Calls</h2>
                    <p class="text-xs text-muted-foreground">
                        {{ recentCalls.total }} invocation(s) matching current
                        filters.
                    </p>
                </div>
            </div>

            <p
                v-if="!recentCalls.data.length"
                class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
            >
                No AI calls match the selected filters.
            </p>

            <div
                v-else
                role="region"
                tabindex="0"
                aria-label="Recent AI calls ledger"
                class="overflow-x-auto"
            >
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b text-muted-foreground">
                            <th scope="col" class="py-2 pr-3 font-medium">
                                When
                            </th>
                            <th scope="col" class="px-2 py-2 font-medium">
                                Feature
                            </th>
                            <th scope="col" class="px-2 py-2 font-medium">
                                User / Workspace
                            </th>
                            <th scope="col" class="px-2 py-2 font-medium">
                                Model
                            </th>
                            <th
                                scope="col"
                                class="px-2 py-2 text-right font-medium"
                            >
                                Tokens (In / Out)
                            </th>
                            <th
                                scope="col"
                                class="px-2 py-2 text-right font-medium"
                            >
                                Latency
                            </th>
                            <th scope="col" class="px-2 py-2 font-medium">
                                Status
                            </th>
                            <th
                                scope="col"
                                class="py-2 pl-2 text-right font-medium"
                            >
                                Cost
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="call in recentCalls.data" :key="call.id">
                            <td
                                class="py-2.5 pr-3 whitespace-nowrap text-muted-foreground tabular-nums"
                            >
                                {{ call.occurredAtFormatted }}
                            </td>
                            <td class="px-2 py-2.5">
                                <div class="font-medium text-foreground">
                                    {{ call.featureLabel }}
                                </div>
                                <div class="text-[11px] text-muted-foreground">
                                    {{ call.stepsCount }} step(s) ·
                                    {{ call.toolCallsCount }} tool(s)
                                </div>
                            </td>
                            <td class="px-2 py-2.5">
                                <div class="font-medium text-foreground">
                                    {{ call.user?.name ?? 'System' }}
                                    <span
                                        v-if="call.impersonator"
                                        class="ml-1 text-[11px] font-normal text-sky-600 dark:text-sky-400"
                                    >
                                        (via {{ call.impersonator.name }})
                                    </span>
                                </div>
                                <div class="text-[11px] text-muted-foreground">
                                    {{ call.team?.name ?? 'Unassigned' }}
                                </div>
                            </td>
                            <td class="px-2 py-2.5 whitespace-nowrap">
                                <span class="font-medium">{{
                                    call.model
                                }}</span>
                                <span
                                    class="ml-1 text-[11px] text-muted-foreground"
                                >
                                    ({{ call.provider }})
                                </span>
                            </td>
                            <td
                                class="px-2 py-2.5 text-right whitespace-nowrap tabular-nums"
                            >
                                {{ formatTokenCount(call.inputTokens) }} /
                                {{ formatTokenCount(call.outputTokens) }}
                            </td>
                            <td
                                class="px-2 py-2.5 text-right whitespace-nowrap text-muted-foreground tabular-nums"
                            >
                                {{ formatDurationMs(call.durationMs) }}
                            </td>
                            <td class="px-2 py-2.5">
                                <Badge
                                    :variant="statusBadgeVariant(call.status)"
                                >
                                    {{ call.status }}
                                </Badge>
                            </td>
                            <td
                                class="py-2.5 pl-2 text-right font-semibold whitespace-nowrap tabular-nums"
                            >
                                <span v-if="call.pricingStatus === 'priced'">
                                    {{
                                        formatUsdFromMicrodollars(
                                            call.costMicrodollars,
                                        )
                                    }}
                                </span>
                                <span
                                    v-else
                                    class="font-normal text-amber-600 dark:text-amber-400"
                                >
                                    Unpriced
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <nav
                v-if="recentCalls.prev_page_url || recentCalls.next_page_url"
                aria-label="Recent AI calls pagination"
                class="mt-4 flex gap-4 text-xs"
            >
                <Link
                    v-if="recentCalls.prev_page_url"
                    :href="recentCalls.prev_page_url"
                    preserve-scroll
                    class="underline"
                >
                    Previous calls
                </Link>
                <Link
                    v-if="recentCalls.next_page_url"
                    :href="recentCalls.next_page_url"
                    preserve-scroll
                    class="underline"
                >
                    Next calls
                </Link>
            </nav>
        </section>
    </div>
</template>
