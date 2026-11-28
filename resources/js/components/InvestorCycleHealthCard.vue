<script setup lang="ts">
import {
    Activity,
    CloudRain,
    Droplets,
    Egg,
    HeartPulse,
    Scale,
    Sprout,
    Wheat,
} from 'lucide-vue-next';
import { computed } from 'vue';
import FarmTypeBadge from '@/components/farm/FarmTypeBadge.vue';
import MetricCard from '@/components/farm/MetricCard.vue';
import MiniBarChart from '@/components/farm/MiniBarChart.vue';
import { Badge } from '@/components/ui/badge';
import { formatInvestorDate } from '@/lib/investor-portal';
import type { InvestorCycleSnapshot } from '@/types';

const props = defineProps<{
    snapshot: InvestorCycleSnapshot;
}>();

const waterBadge = computed(() => {
    switch (props.snapshot.aquaculture.waterStatus) {
        case 'optimal':
            return {
                label: 'Optimal water',
                classes:
                    'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800/60 dark:bg-emerald-950/60 dark:text-emerald-200',
            };
        case 'warning':
            return {
                label: 'Water attention',
                classes:
                    'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800/60 dark:bg-amber-950/60 dark:text-amber-200',
            };
        case 'critical':
            return {
                label: 'Critical water alert',
                classes:
                    'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-800/60 dark:bg-rose-950/60 dark:text-rose-200',
            };
        default:
            return {
                label: 'No water readings',
                classes: 'border-border bg-muted text-muted-foreground',
            };
    }
});

const pestTone = computed<'emerald' | 'amber' | 'rose' | 'default'>(() => {
    switch (props.snapshot.crop.latestPestPressure) {
        case 'none':
        case 'low':
            return 'emerald';
        case 'moderate':
            return 'amber';
        case 'high':
            return 'rose';
        default:
            return 'default';
    }
});

const chartSeries = computed(() => {
    if (
        props.snapshot.domain === 'poultry' &&
        props.snapshot.series.eggs.some((value) => value > 0)
    ) {
        return {
            title: 'Daily egg collection (last 14 logs)',
            values: props.snapshot.series.eggs,
            unit: 'eggs',
            tone: 'amber' as const,
        };
    }

    if (props.snapshot.domain === 'crop') {
        return {
            title: 'Rainfall recorded (last 14 logs)',
            values: props.snapshot.series.rainfallMm,
            unit: 'mm',
            tone: 'cyan' as const,
        };
    }

    return {
        title: 'Daily feed used (last 14 logs)',
        values: props.snapshot.series.feedKg,
        unit: 'kg',
        tone: 'emerald' as const,
    };
});
</script>

<template>
    <section
        aria-label="Live cycle health and progress"
        class="space-y-4 rounded-2xl border bg-card p-4 shadow-xs sm:p-6"
    >
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                    <FarmTypeBadge
                        :farm-type="snapshot.farmType"
                        :label="snapshot.farmTypeLabel"
                    />
                    <Badge variant="outline">{{ snapshot.statusLabel }}</Badge>
                    <Badge
                        v-if="snapshot.domain === 'aquaculture'"
                        variant="outline"
                        :class="waterBadge.classes"
                    >
                        {{ waterBadge.label }}
                    </Badge>
                </div>
                <h3 class="text-base font-semibold tracking-tight sm:text-lg">
                    {{ snapshot.cycleName }}
                    <span
                        v-if="snapshot.season"
                        class="text-sm font-normal text-muted-foreground"
                    >
                        · {{ snapshot.season }}
                    </span>
                </h3>
                <p class="text-xs text-muted-foreground">
                    Live operational snapshot · Day {{ snapshot.ageDays }}
                    <template v-if="snapshot.lastLoggedOn">
                        · Last daily log
                        {{ formatInvestorDate(snapshot.lastLoggedOn) }}
                    </template>
                </p>
            </div>
            <div class="min-w-36 text-start sm:text-end">
                <p class="text-xs text-muted-foreground">Cycle progress</p>
                <p class="text-xl font-semibold tabular-nums">
                    {{ snapshot.progressPercent }}%
                </p>
                <div
                    class="mt-1.5 h-1.5 w-36 overflow-hidden rounded-full bg-muted"
                >
                    <div
                        class="h-full rounded-full bg-primary transition-all"
                        :style="{
                            width: `${Math.min(100, Math.max(0, snapshot.progressPercent))}%`,
                        }"
                    />
                </div>
            </div>
        </div>

        <div
            v-if="snapshot.domain === 'poultry'"
            class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
        >
            <MetricCard
                label="Current flock"
                :value="
                    snapshot.stock.currentCount !== null
                        ? snapshot.stock.currentCount.toLocaleString()
                        : '—'
                "
                :subtext="
                    snapshot.stock.survivalRatePercent !== null
                        ? `${snapshot.stock.survivalRatePercent}% survival`
                        : 'Stock count not tracked'
                "
                :progress="snapshot.stock.survivalRatePercent"
                tone="emerald"
                :icon="HeartPulse"
            />
            <MetricCard
                label="Eggs collected"
                :value="snapshot.poultry.totalEggs.toLocaleString()"
                :subtext="`${snapshot.poultry.totalCrates} crates total`"
                tone="amber"
                :icon="Egg"
            />
            <MetricCard
                label="7-day lay rate"
                :value="
                    snapshot.poultry.recentLayRatePercent !== null
                        ? `${snapshot.poultry.recentLayRatePercent}%`
                        : '—'
                "
                subtext="Rolling weekly flock average"
                tone="cyan"
                :icon="Activity"
            />
            <MetricCard
                label="Feed consumed"
                :value="`${snapshot.feeding.totalFeedKg.toLocaleString()} kg`"
                :subtext="
                    snapshot.feeding.fcr !== null
                        ? `FCR ${snapshot.feeding.fcr} · ${snapshot.feeding.averageDailyFeedKg} kg/day`
                        : `${snapshot.feeding.averageDailyFeedKg} kg/day avg`
                "
                :icon="Scale"
            />
        </div>

        <div v-else-if="snapshot.domain === 'aquaculture'" class="space-y-3">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <MetricCard
                    label="Current stock"
                    :value="
                        snapshot.stock.currentCount !== null
                            ? snapshot.stock.currentCount.toLocaleString()
                            : '—'
                    "
                    :subtext="
                        snapshot.stock.survivalRatePercent !== null
                            ? `${snapshot.stock.survivalRatePercent}% survival`
                            : 'Stocking count pending'
                    "
                    :progress="snapshot.stock.survivalRatePercent"
                    tone="cyan"
                    :icon="HeartPulse"
                />
                <MetricCard
                    label="Estimated biomass"
                    :value="
                        snapshot.feeding.estimatedBiomassKg !== null
                            ? `${snapshot.feeding.estimatedBiomassKg.toLocaleString()} kg`
                            : '—'
                    "
                    :subtext="
                        snapshot.feeding.latestAverageWeightG !== null
                            ? `${snapshot.feeding.latestAverageWeightG} g avg weight`
                            : 'Weight sampling pending'
                    "
                    tone="emerald"
                    :icon="Scale"
                />
                <MetricCard
                    label="Feed consumed"
                    :value="`${snapshot.feeding.totalFeedKg.toLocaleString()} kg`"
                    :subtext="
                        snapshot.feeding.fcr !== null
                            ? `FCR ${snapshot.feeding.fcr}`
                            : `${snapshot.feeding.averageDailyFeedKg} kg/day avg`
                    "
                    tone="amber"
                    :icon="Activity"
                />
                <MetricCard
                    label="Water exchanges"
                    :value="snapshot.aquaculture.waterExchangeCount"
                    :subtext="`${snapshot.logCount} daily logs recorded`"
                    tone="cyan"
                    :icon="Droplets"
                />
            </div>

            <dl
                class="grid grid-cols-2 gap-3 rounded-xl border bg-muted/30 p-3 text-xs sm:grid-cols-4"
            >
                <div>
                    <dt class="text-muted-foreground">Water pH</dt>
                    <dd class="mt-0.5 text-sm font-semibold tabular-nums">
                        {{ snapshot.aquaculture.latestPh ?? '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Temperature</dt>
                    <dd class="mt-0.5 text-sm font-semibold tabular-nums">
                        {{
                            snapshot.aquaculture.latestTempC !== null
                                ? `${snapshot.aquaculture.latestTempC} °C`
                                : '—'
                        }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Dissolved O₂</dt>
                    <dd class="mt-0.5 text-sm font-semibold tabular-nums">
                        {{
                            snapshot.aquaculture.latestDissolvedOxygen !== null
                                ? `${snapshot.aquaculture.latestDissolvedOxygen} mg/L`
                                : '—'
                        }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">Ammonia</dt>
                    <dd class="mt-0.5 text-sm font-semibold tabular-nums">
                        {{
                            snapshot.aquaculture.latestAmmonia !== null
                                ? `${snapshot.aquaculture.latestAmmonia} mg/L`
                                : '—'
                        }}
                    </dd>
                </div>
            </dl>
        </div>

        <div
            v-else-if="snapshot.domain === 'crop'"
            class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
        >
            <MetricCard
                label="Growth stage"
                :value="snapshot.crop.currentGrowthStageLabel ?? 'Recorded'"
                :subtext="`${snapshot.crop.stageProgressPercent}% of stage timeline`"
                :progress="snapshot.crop.stageProgressPercent"
                tone="emerald"
                :icon="Sprout"
            />
            <MetricCard
                label="Cumulative rainfall"
                :value="`${snapshot.crop.totalRainfallMm} mm`"
                :subtext="`${snapshot.logCount} field logs recorded`"
                tone="cyan"
                :icon="CloudRain"
            />
            <MetricCard
                label="Irrigation runs"
                :value="snapshot.crop.irrigationCount"
                subtext="Recorded irrigation sessions"
                tone="emerald"
                :icon="Droplets"
            />
            <MetricCard
                label="Pest pressure"
                :value="snapshot.crop.latestPestPressureLabel ?? 'Not logged'"
                subtext="Latest field assessment"
                :tone="pestTone"
                :icon="Wheat"
            />
        </div>

        <div v-else class="grid gap-3 sm:grid-cols-3">
            <MetricCard
                label="Daily logs"
                :value="snapshot.logCount"
                :subtext="`Cycle day ${snapshot.ageDays}`"
                tone="emerald"
                :icon="Activity"
            />
            <MetricCard
                label="Current stock"
                :value="
                    snapshot.stock.currentCount !== null
                        ? snapshot.stock.currentCount.toLocaleString()
                        : '—'
                "
                :subtext="
                    snapshot.stock.survivalRatePercent !== null
                        ? `${snapshot.stock.survivalRatePercent}% survival`
                        : undefined
                "
                :icon="HeartPulse"
            />
            <MetricCard
                label="Feed consumed"
                :value="`${snapshot.feeding.totalFeedKg.toLocaleString()} kg`"
                :icon="Scale"
            />
        </div>

        <div
            v-if="snapshot.series.dates.length > 0"
            class="rounded-xl border bg-muted/20 p-3.5"
        >
            <p class="mb-2 text-xs font-medium text-muted-foreground">
                {{ chartSeries.title }}
            </p>
            <MiniBarChart
                :labels="snapshot.series.dates"
                :values="chartSeries.values"
                :unit="chartSeries.unit"
                :tone="chartSeries.tone"
                :height="88"
            />
        </div>
    </section>
</template>
