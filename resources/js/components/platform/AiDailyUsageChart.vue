<script setup lang="ts">
import { computed, ref } from 'vue';
import { Button } from '@/components/ui/button';
import {
    formatTokenCount,
    formatUsdFromMicrodollars,
} from '@/lib/aiUsageFormat';

export type AiDailyUsagePoint = {
    date: string;
    label: string;
    costMicrodollars: number;
    inputCostMicrodollars: number;
    outputCostMicrodollars: number;
    cacheCostMicrodollars: number;
    calls: number;
    failedCalls: number;
    inputTokens: number;
    outputTokens: number;
    cacheReadTokens: number;
    totalTokens: number;
};

const props = defineProps<{
    days: AiDailyUsagePoint[];
}>();

const metric = ref<'cost' | 'tokens' | 'calls'>('cost');
const activeIndex = ref<number | null>(null);

const hasActivity = computed(() =>
    props.days.some(
        (day) =>
            day.calls > 0 || day.totalTokens > 0 || day.costMicrodollars > 0,
    ),
);

const maxMetricValue = computed(() => {
    const peak = Math.max(
        0,
        ...props.days.map((day) => {
            if (metric.value === 'cost') {
                return day.costMicrodollars;
            }

            if (metric.value === 'tokens') {
                return day.totalTokens;
            }

            return day.calls;
        }),
    );

    return Math.max(1, peak);
});

const highlightedDay = computed<AiDailyUsagePoint | null>(() => {
    if (props.days.length === 0) {
        return null;
    }

    if (
        activeIndex.value !== null &&
        activeIndex.value >= 0 &&
        activeIndex.value < props.days.length
    ) {
        return props.days[activeIndex.value];
    }

    const lastNonZero = [...props.days]
        .reverse()
        .find((day) => day.calls > 0 || day.costMicrodollars > 0);

    return lastNonZero ?? props.days[props.days.length - 1];
});

const chartWidth = computed(() => Math.max(420, props.days.length * 22));
const chartHeight = 168;
const plotTop = 10;
const plotBottom = 148;
const plotHeight = plotBottom - plotTop;

const slotWidth = computed(() =>
    props.days.length > 0 ? chartWidth.value / props.days.length : 22,
);

const barWidth = computed(() =>
    Math.max(5, Math.min(20, slotWidth.value * 0.64)),
);

function barX(index: number): number {
    return index * slotWidth.value + (slotWidth.value - barWidth.value) / 2;
}

function scaledHeight(value: number): number {
    if (value <= 0) {
        return 0;
    }

    return Math.max(2, (value / maxMetricValue.value) * plotHeight);
}

const yTicks = computed(() => [
    maxMetricValue.value,
    Math.round(maxMetricValue.value / 2),
    0,
]);

function formatYTick(value: number): string {
    if (metric.value === 'cost') {
        return formatUsdFromMicrodollars(value, { compact: true });
    }

    if (metric.value === 'tokens') {
        return formatTokenCount(value);
    }

    return String(value);
}

const xTickIndices = computed(() => {
    const count = props.days.length;

    if (count === 0) {
        return [];
    }

    if (count <= 7) {
        return props.days.map((_, idx) => idx);
    }

    const mid = Math.floor((count - 1) / 2);

    return [0, mid, count - 1];
});
</script>

<template>
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <span
                    class="inline-flex items-center gap-1.5 text-xs text-muted-foreground"
                >
                    <span class="size-2.5 rounded-xs bg-emerald-500" />
                    {{
                        metric === 'cost'
                            ? 'Input / Cache'
                            : metric === 'tokens'
                              ? 'Input tokens'
                              : 'Succeeded'
                    }}
                </span>
                <span
                    class="inline-flex items-center gap-1.5 text-xs text-muted-foreground"
                >
                    <span
                        class="size-2.5 rounded-xs"
                        :class="
                            metric === 'calls' ? 'bg-rose-500' : 'bg-cyan-500'
                        "
                    />
                    {{
                        metric === 'cost'
                            ? 'Output'
                            : metric === 'tokens'
                              ? 'Output tokens'
                              : 'Failed'
                    }}
                </span>
            </div>

            <div
                class="inline-flex rounded-lg border bg-muted/40 p-0.5"
                role="group"
                aria-label="Chart metric"
            >
                <Button
                    v-for="item in [
                        { id: 'cost', label: 'Cost (USD)' },
                        { id: 'tokens', label: 'Tokens' },
                        { id: 'calls', label: 'Agent Calls' },
                    ] as const"
                    :key="item.id"
                    type="button"
                    size="sm"
                    :variant="metric === item.id ? 'default' : 'ghost'"
                    class="h-7 px-2.5 text-xs"
                    :aria-pressed="metric === item.id"
                    @click="metric = item.id"
                >
                    {{ item.label }}
                </Button>
            </div>
        </div>

        <div
            v-if="highlightedDay && hasActivity"
            class="flex flex-wrap items-center justify-between gap-2 rounded-lg border bg-muted/30 px-3 py-2 text-xs tabular-nums"
        >
            <span class="font-medium text-foreground">
                {{ highlightedDay.label }}
                <span class="ml-1 font-normal text-muted-foreground">
                    ({{ highlightedDay.date }})
                </span>
            </span>
            <div
                class="flex flex-wrap items-center gap-4 text-muted-foreground"
            >
                <span>
                    Cost:
                    <strong class="text-foreground">{{
                        formatUsdFromMicrodollars(
                            highlightedDay.costMicrodollars,
                        )
                    }}</strong>
                </span>
                <span>
                    Calls:
                    <strong class="text-foreground">{{
                        highlightedDay.calls
                    }}</strong>
                    <span
                        v-if="highlightedDay.failedCalls > 0"
                        class="ml-1 text-rose-600 dark:text-rose-400"
                    >
                        ({{ highlightedDay.failedCalls }} failed)
                    </span>
                </span>
                <span>
                    In:
                    <strong class="text-foreground">{{
                        formatTokenCount(highlightedDay.inputTokens)
                    }}</strong>
                </span>
                <span>
                    Out:
                    <strong class="text-foreground">{{
                        formatTokenCount(highlightedDay.outputTokens)
                    }}</strong>
                </span>
            </div>
        </div>

        <div
            v-if="!hasActivity"
            class="flex h-44 items-center justify-center rounded-xl border border-dashed text-sm text-muted-foreground"
        >
            No AI calls recorded in this date window yet.
        </div>

        <div v-else class="grid grid-cols-[auto_1fr] items-end gap-2">
            <div
                class="flex h-[168px] flex-col justify-between pb-5 text-right text-[11px] text-muted-foreground tabular-nums"
            >
                <span v-for="(tick, idx) in yTicks" :key="idx">
                    {{ formatYTick(tick) }}
                </span>
            </div>

            <div class="space-y-1">
                <svg
                    :viewBox="`0 0 ${chartWidth} ${chartHeight}`"
                    class="h-[168px] w-full overflow-visible"
                    role="img"
                    aria-label="Daily AI usage bar chart"
                >
                    <line
                        v-for="ratio in [0, 0.5, 1]"
                        :key="ratio"
                        x1="0"
                        :x2="chartWidth"
                        :y1="plotTop + plotHeight * ratio"
                        :y2="plotTop + plotHeight * ratio"
                        stroke="currentColor"
                        class="text-border/70"
                        stroke-dasharray="3 3"
                    />

                    <g
                        v-for="(day, index) in days"
                        :key="day.date"
                        tabindex="0"
                        class="cursor-pointer outline-none"
                        @mouseenter="activeIndex = index"
                        @focus="activeIndex = index"
                        @mouseleave="activeIndex = null"
                    >
                        <rect
                            :x="index * slotWidth"
                            :y="plotTop"
                            :width="slotWidth"
                            :height="plotHeight"
                            class="fill-transparent"
                        />

                        <template v-if="metric === 'cost'">
                            <rect
                                v-if="
                                    day.inputCostMicrodollars +
                                        day.cacheCostMicrodollars >
                                    0
                                "
                                :x="barX(index)"
                                :y="
                                    plotBottom -
                                    scaledHeight(
                                        day.inputCostMicrodollars +
                                            day.cacheCostMicrodollars,
                                    ) -
                                    scaledHeight(day.outputCostMicrodollars)
                                "
                                :width="barWidth"
                                :height="
                                    scaledHeight(
                                        day.inputCostMicrodollars +
                                            day.cacheCostMicrodollars,
                                    )
                                "
                                rx="2"
                                class="fill-emerald-500/90 transition-opacity hover:opacity-100"
                                :class="
                                    activeIndex !== null &&
                                    activeIndex !== index
                                        ? 'opacity-50'
                                        : 'opacity-95'
                                "
                            />
                            <rect
                                v-if="day.outputCostMicrodollars > 0"
                                :x="barX(index)"
                                :y="
                                    plotBottom -
                                    scaledHeight(day.outputCostMicrodollars)
                                "
                                :width="barWidth"
                                :height="
                                    scaledHeight(day.outputCostMicrodollars)
                                "
                                rx="2"
                                class="fill-cyan-500/90 transition-opacity hover:opacity-100"
                                :class="
                                    activeIndex !== null &&
                                    activeIndex !== index
                                        ? 'opacity-50'
                                        : 'opacity-95'
                                "
                            />
                        </template>

                        <template v-else-if="metric === 'tokens'">
                            <rect
                                v-if="day.inputTokens > 0"
                                :x="barX(index)"
                                :y="
                                    plotBottom -
                                    scaledHeight(day.inputTokens) -
                                    scaledHeight(day.outputTokens)
                                "
                                :width="barWidth"
                                :height="scaledHeight(day.inputTokens)"
                                rx="2"
                                class="fill-emerald-500/90 transition-opacity"
                                :class="
                                    activeIndex !== null &&
                                    activeIndex !== index
                                        ? 'opacity-50'
                                        : 'opacity-95'
                                "
                            />
                            <rect
                                v-if="day.outputTokens > 0"
                                :x="barX(index)"
                                :y="plotBottom - scaledHeight(day.outputTokens)"
                                :width="barWidth"
                                :height="scaledHeight(day.outputTokens)"
                                rx="2"
                                class="fill-cyan-500/90 transition-opacity"
                                :class="
                                    activeIndex !== null &&
                                    activeIndex !== index
                                        ? 'opacity-50'
                                        : 'opacity-95'
                                "
                            />
                        </template>

                        <template v-else>
                            <rect
                                v-if="day.calls - day.failedCalls > 0"
                                :x="barX(index)"
                                :y="
                                    plotBottom -
                                    scaledHeight(day.calls - day.failedCalls) -
                                    scaledHeight(day.failedCalls)
                                "
                                :width="barWidth"
                                :height="
                                    scaledHeight(day.calls - day.failedCalls)
                                "
                                rx="2"
                                class="fill-emerald-500/90 transition-opacity"
                                :class="
                                    activeIndex !== null &&
                                    activeIndex !== index
                                        ? 'opacity-50'
                                        : 'opacity-95'
                                "
                            />
                            <rect
                                v-if="day.failedCalls > 0"
                                :x="barX(index)"
                                :y="plotBottom - scaledHeight(day.failedCalls)"
                                :width="barWidth"
                                :height="scaledHeight(day.failedCalls)"
                                rx="2"
                                class="fill-rose-500/90 transition-opacity"
                                :class="
                                    activeIndex !== null &&
                                    activeIndex !== index
                                        ? 'opacity-50'
                                        : 'opacity-95'
                                "
                            />
                        </template>

                        <title>
                            {{ day.date }} · {{ day.calls }} calls ·
                            {{
                                formatUsdFromMicrodollars(day.costMicrodollars)
                            }}
                            · {{ formatTokenCount(day.totalTokens) }} tokens
                        </title>
                    </g>
                </svg>

                <div
                    class="flex items-center justify-between text-[11px] text-muted-foreground tabular-nums"
                >
                    <span v-for="idx in xTickIndices" :key="idx">
                        {{ days[idx]?.label }}
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
