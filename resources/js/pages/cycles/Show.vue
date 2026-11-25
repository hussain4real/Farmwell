<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import {
    Activity,
    AlertTriangle,
    ArrowLeftRight,
    Droplets,
    Edit3,
    Egg,
    FileDown,
    HeartPulse,
    Landmark,
    Plus,
    Scale,
    Share2,
    ShieldAlert,
    Sparkles,
    Sprout,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import CropDailyLogForm from '@/components/farm/CropDailyLogForm.vue';
import EmptyState from '@/components/farm/EmptyState.vue';
import FarmTypeBadge from '@/components/farm/FarmTypeBadge.vue';
import FishDailyLogForm from '@/components/farm/FishDailyLogForm.vue';
import GradeFishModal from '@/components/farm/GradeFishModal.vue';
import MetricCard from '@/components/farm/MetricCard.vue';
import MiniBarChart from '@/components/farm/MiniBarChart.vue';
import PageHero from '@/components/farm/PageHero.vue';
import PoultryDailyLogForm from '@/components/farm/PoultryDailyLogForm.vue';
import type { LogInventoryOption } from '@/components/farm/PoultryDailyLogForm.vue';
import SlideOverForm from '@/components/farm/SlideOverForm.vue';
import SparklineChart from '@/components/farm/SparklineChart.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCurrency } from '@/lib/currency';
import { dashboard } from '@/routes';
import { index as farmsIndex, show as farmsShow } from '@/routes/farms';
import {
    show as cycleShow,
    summaryPdf,
    update as updateCycle,
} from '@/routes/farms/production-cycles';
import { store as applyHealthPreset } from '@/routes/farms/production-cycles/health-presets';
import { destroy as destroyProductionLog } from '@/routes/farms/production-cycles/production-logs';
import { store as storeQuickEggSale } from '@/routes/farms/production-cycles/quick-egg-sale';
import { update as updateHealthEvent } from '@/routes/health';
import type { FarmOption, FarmOperationPermissions, Team } from '@/types';

const props = defineProps<{
    permissions: FarmOperationPermissions;
    farm: {
        id: number;
        name: string;
        farmType: string;
        farmTypeLabel: string;
        units: { id: number; name: string; unitTypeLabel: string }[];
    };
    cycle: {
        id: number;
        name: string;
        season: string | null;
        farmType: string;
        farmTypeLabel: string;
        domain: string;
        enterpriseSubtype: string | null;
        enterpriseSubtypeLabel: string | null;
        breedOrVariety: string | null;
        plannedStartOn: string;
        plannedEndOn: string | null;
        actualStartOn: string | null;
        actualEndOn: string | null;
        expectedOutputQuantity: string | null;
        expectedOutputUnit: string | null;
        initialStockCount: number | null;
        currentStockCount: number | null;
        currentGrowthStage: string | null;
        currentGrowthStageLabel: string | null;
        status: string;
        statusLabel: string;
        planVersion: number;
        planSummary: string | null;
        productionUnits: { id: number; name: string }[];
        commodities: { id: number; name: string }[];
    };
    telemetry: {
        domain: string;
        ageDays: number;
        progressPercent: number;
        logCount: number;
        stock: {
            initialCount: number | null;
            currentCount: number | null;
            totalMortality: number;
            totalCulled: number;
            totalGradedOut: number;
            mortalityRatePercent: number | null;
            survivalRatePercent: number | null;
        };
        feeding: {
            totalFeedKg: number;
            averageDailyFeedKg: number;
            latestAverageWeightG: number | null;
            estimatedBiomassKg: number | null;
            fcr: number | null;
        };
        poultry: {
            totalEggs: number;
            totalCrates: number;
            totalDamagedEggs: number;
            damagedRatePercent: number;
            recentLayRatePercent: number | null;
        };
        aquaculture: {
            latestPh: number | null;
            latestTempC: number | null;
            latestDissolvedOxygen: number | null;
            latestAmmonia: number | null;
            waterExchangeCount: number;
            waterStatus: string;
            waterAlerts: string[];
        };
        crop: {
            currentGrowthStage: string | null;
            currentGrowthStageLabel: string | null;
            stageProgressPercent: number;
            totalRainfallMm: number;
            irrigationCount: number;
            latestPestPressureLabel: string | null;
            hasHighPestAlert: boolean;
            activeDiseaseAlert: string | null;
            totalHarvestedQuantity: number;
            yieldPerUnitArea: number | null;
        };
        series: {
            dates: string[];
            feedKg: number[];
            mortality: number[];
            eggs: number[];
            dissolvedOxygen: (number | null)[];
            rainfallMm: number[];
            inputQuantity: number[];
        };
    };
    profitability: {
        currency: string;
        directExpenses: string;
        inventoryUsage: string;
        healthCosts: string;
        harvestLabour: string;
        totalCost: string;
        netRevenue: string;
        grossProfit: string;
        marginPercent: number | null;
        costPerLiveUnit: string | null;
        costPerHarvestedUnit: string | null;
    } | null;
    calculators: {
        aquaculture: {
            currentStock: number;
            avgWeightG: number;
            estimatedBiomassKg: number;
            bodyWeightPercent: number;
            pelletSizeMm: string;
            mealsPerDay: number;
            recommendedDailyFeedKg: number;
            actualDailyFeedKg: number;
            feedingStatus: string;
        } | null;
        poultry: {
            isLayer: boolean;
            ageDays: number;
            currentStock: number;
            currentPhase: string;
            nextPhase: string | null;
            daysToNextPhase: number | null;
            gramsPerBird: number;
            recommendedDailyFeedKg: number;
            actualDailyFeedKg: number;
        } | null;
        crop: {
            totalArea: number;
            areaUnit: string;
            currency: string;
            yieldPerArea: number | null;
            costPerArea: string | null;
            revenuePerArea: string | null;
            seasonComparisons: {
                id: number;
                name: string;
                season: string | null;
                statusLabel: string;
                isCurrent: boolean;
                totalArea: number;
                areaUnit: string;
                harvestedQuantity: number;
                yieldPerArea: number | null;
                costPerArea: string | null;
                marginPercent: number | null;
            }[];
        } | null;
    };
    summaryWhatsappText: string;
    logs: {
        id: number;
        logDate: string;
        productionUnitName: string | null;
        mortalityCount: number;
        cullCount: number;
        gradedOutCount: number;
        feedUsedKg: string | null;
        feedType: string | null;
        feedPelletSizeMm: string | null;
        averageWeightG: string | null;
        eggsCollected: number | null;
        waterPh: string | null;
        waterTempC: string | null;
        dissolvedOxygenMgL: string | null;
        ammoniaMgL: string | null;
        growthStageLabel: string | null;
        pestPressureLabel: string | null;
        inputAppliedName: string | null;
        inputAppliedQuantity: string | null;
        inputAppliedUnit: string | null;
        inventoryItemName: string | null;
        notes: string | null;
        recordedBy: string | null;
        evidence: { id: number; fileName: string; downloadUrl: string }[];
    }[];
    healthEvents: {
        id: number;
        title: string;
        eventTypeLabel: string;
        scheduledOn: string;
        status: string;
        statusLabel: string;
        isOverdue: boolean;
        isUnderWithdrawal: boolean;
        withdrawalEndsOn: string | null;
    }[];
    activeWithdrawals: {
        id: number;
        title: string;
        productName: string | null;
        withdrawalEndsOn: string | null;
    }[];
    inventoryMovements: {
        id: number;
        itemName: string;
        itemUnit: string;
        movementTypeLabel: string;
        quantity: string;
        totalCost: string;
        currency: string;
        movementDate: string;
    }[];
    inventoryItems: LogInventoryOption[];
    siblingCycles: {
        id: number;
        name: string;
        currentStockCount: number | null;
    }[];
    planChanges: {
        id: number;
        changeTypeLabel: string;
        reason: string;
        impact: string;
        createdAt: string | null;
    }[];
    options: {
        cycleStatuses: FarmOption[];
        enterpriseSubtypes: FarmOption[];
        cropGrowthStages: FarmOption[];
        pestPressures: FarmOption[];
        healthPresets: {
            key: string;
            label: string;
            description: string;
            eventCount: number;
        }[];
        paymentStatuses: FarmOption[];
    };
}>();

defineOptions({
    layout: (props: {
        currentTeam?: Team | null;
        farm?: { id: number; name: string };
        cycle?: { id: number; name: string };
    }) => ({
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: props.currentTeam
                    ? dashboard(props.currentTeam.slug)
                    : '/',
            },
            {
                title: 'Farms',
                href: props.currentTeam
                    ? farmsIndex(props.currentTeam.slug)
                    : '/',
            },
            {
                title: props.farm?.name ?? 'Farm',
                href:
                    props.currentTeam && props.farm
                        ? farmsShow([props.currentTeam.slug, props.farm.id])
                        : '/',
            },
            {
                title: props.cycle?.name ?? 'Cycle',
                href:
                    props.currentTeam && props.farm && props.cycle
                        ? cycleShow([
                              props.currentTeam.slug,
                              props.farm.id,
                              props.cycle.id,
                          ])
                        : '/',
            },
        ],
    }),
});

const page = usePage();
const teamSlug = computed(
    () => (page.props.currentTeam as Team | null)?.slug ?? '',
);

const activeTab = ref<'logs' | 'health' | 'stock' | 'profit'>('logs');
const logDrawerOpen = ref(false);
const editDrawerOpen = ref(false);
const gradeDrawerOpen = ref(false);
const presetDrawerOpen = ref(false);
const eggSaleDrawerOpen = ref(false);
const today = new Date().toISOString().slice(0, 10);

const shareSummaryWhatsapp = async () => {
    const text = props.summaryWhatsappText;

    if (!text) {
        return;
    }

    if (
        typeof navigator !== 'undefined' &&
        typeof navigator.share === 'function'
    ) {
        try {
            await navigator.share({
                title: `${props.farm.name} — ${props.cycle.name}`,
                text,
            });

            return;
        } catch {
            // Fall through to clipboard copy.
        }
    }

    if (typeof navigator !== 'undefined' && navigator.clipboard) {
        await navigator.clipboard.writeText(text).catch(() => {});
        toast.success('Cycle summary copied — ready to paste into WhatsApp.');
    }
};
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Head :title="`${cycle.name} — ${farm.name}`" />

        <PageHero
            :eyebrow="`${farm.name} · Day ${telemetry.ageDays}`"
            :title="cycle.name"
            :description="
                [
                    cycle.enterpriseSubtypeLabel ?? cycle.farmTypeLabel,
                    cycle.breedOrVariety,
                    cycle.commodities.map((c) => c.name).join(', '),
                ]
                    .filter(Boolean)
                    .join(' · ')
            "
        >
            <template #badges>
                <FarmTypeBadge
                    :farm-type="cycle.farmType"
                    :label="cycle.farmTypeLabel"
                />
                <Badge variant="secondary">{{ cycle.statusLabel }}</Badge>
            </template>
            <template #actions>
                <Button
                    v-if="permissions.canManageFieldWork"
                    type="button"
                    class="bg-white text-[#1b4d3e] hover:bg-emerald-50"
                    @click="logDrawerOpen = true"
                >
                    <Plus class="mr-1.5 size-4" />
                    Record daily log
                </Button>
                <Button
                    v-if="
                        cycle.domain === 'aquaculture' &&
                        permissions.canManageFieldWork
                    "
                    type="button"
                    variant="secondary"
                    @click="gradeDrawerOpen = true"
                >
                    <ArrowLeftRight class="mr-1.5 size-4" />
                    Grade fish
                </Button>
                <Button
                    v-if="
                        cycle.domain === 'poultry' &&
                        permissions.canManageFinance
                    "
                    type="button"
                    variant="secondary"
                    @click="eggSaleDrawerOpen = true"
                >
                    <Egg class="mr-1.5 size-4" />
                    Quick egg crate sale
                </Button>
                <a
                    v-if="teamSlug"
                    :href="summaryPdf.url([teamSlug, farm.id, cycle.id])"
                    class="inline-flex h-9 items-center justify-center rounded-md border border-white/25 bg-white/10 px-3 text-sm font-medium text-white transition-colors hover:bg-white/20"
                >
                    <FileDown class="mr-1.5 size-4" />
                    Export PDF
                </a>
                <Button
                    type="button"
                    variant="secondary"
                    @click="shareSummaryWhatsapp"
                >
                    <Share2 class="mr-1.5 size-4" />
                    Share summary
                </Button>
                <Button
                    v-if="permissions.canManageFarmOperations"
                    type="button"
                    variant="secondary"
                    @click="editDrawerOpen = true"
                >
                    <Edit3 class="mr-1.5 size-4" />
                    Edit cycle
                </Button>
            </template>
        </PageHero>

        <!-- Safety & Water Quality Alerts -->
        <section
            v-if="
                activeWithdrawals.length > 0 ||
                telemetry.aquaculture.waterAlerts.length > 0 ||
                telemetry.crop.hasHighPestAlert ||
                telemetry.crop.activeDiseaseAlert
            "
            class="grid gap-3 md:grid-cols-2"
        >
            <div
                v-if="activeWithdrawals.length > 0"
                class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
            >
                <div class="flex items-start gap-2.5">
                    <ShieldAlert class="mt-0.5 size-5 shrink-0" />
                    <div>
                        <p class="font-semibold">
                            Medication withdrawal active
                        </p>
                        <p
                            v-for="w in activeWithdrawals"
                            :key="w.id"
                            class="mt-1 text-xs"
                        >
                            {{ w.title }} — safe harvest after
                            <strong>{{ w.withdrawalEndsOn }}</strong>
                        </p>
                    </div>
                </div>
            </div>

            <div
                v-if="telemetry.aquaculture.waterAlerts.length > 0"
                class="rounded-2xl border border-rose-300 bg-rose-50 p-4 text-sm text-rose-950 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-200"
            >
                <div class="flex items-start gap-2.5">
                    <AlertTriangle class="mt-0.5 size-5 shrink-0" />
                    <div>
                        <p class="font-semibold">Pond water quality alert</p>
                        <ul class="mt-1 list-disc pl-4 text-xs">
                            <li
                                v-for="alert in telemetry.aquaculture
                                    .waterAlerts"
                                :key="alert"
                            >
                                {{ alert }}
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <div
                v-if="
                    telemetry.crop.hasHighPestAlert ||
                    telemetry.crop.activeDiseaseAlert
                "
                class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
            >
                <div class="flex items-start gap-2.5">
                    <AlertTriangle class="mt-0.5 size-5 shrink-0" />
                    <div>
                        <p class="font-semibold">Crop scouting alert</p>
                        <p
                            v-if="telemetry.crop.hasHighPestAlert"
                            class="text-xs"
                        >
                            High pest pressure reported in latest scouting log.
                        </p>
                        <p
                            v-if="telemetry.crop.activeDiseaseAlert"
                            class="text-xs"
                        >
                            Observed: {{ telemetry.crop.activeDiseaseAlert }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Domain-Specific Telemetry KPIs -->
        <section
            v-if="cycle.domain === 'poultry'"
            class="grid grid-cols-2 gap-4 xl:grid-cols-4"
        >
            <MetricCard
                label="Live birds"
                :value="telemetry.stock.currentCount ?? '—'"
                :subtext="
                    telemetry.stock.survivalRatePercent !== null
                        ? `${telemetry.stock.survivalRatePercent}% survival (${telemetry.stock.totalMortality} mortality)`
                        : undefined
                "
                tone="amber"
                :progress="telemetry.stock.survivalRatePercent"
            />
            <MetricCard
                label="Feed consumed & FCR"
                :value="`${telemetry.feeding.totalFeedKg} kg`"
                :subtext="
                    telemetry.feeding.fcr
                        ? `FCR: ${telemetry.feeding.fcr} · Avg ${telemetry.feeding.latestAverageWeightG ?? '—'}g`
                        : `Avg ${telemetry.feeding.averageDailyFeedKg} kg/day`
                "
                :icon="Scale"
            />
            <MetricCard
                label="Eggs collected"
                :value="`${telemetry.poultry.totalCrates} crates`"
                :subtext="
                    telemetry.poultry.recentLayRatePercent !== null
                        ? `${telemetry.poultry.recentLayRatePercent}% 7-day lay rate`
                        : `${telemetry.poultry.totalEggs} total eggs`
                "
                :icon="Egg"
                tone="emerald"
            />
            <MetricCard
                label="Cycle timeline"
                :value="`Day ${telemetry.ageDays}`"
                :subtext="`${telemetry.progressPercent}% of planned cycle`"
                :progress="telemetry.progressPercent"
            />
        </section>

        <section
            v-else-if="cycle.domain === 'aquaculture'"
            class="grid grid-cols-2 gap-4 xl:grid-cols-4"
        >
            <MetricCard
                label="Live fish count"
                :value="telemetry.stock.currentCount ?? '—'"
                :subtext="
                    telemetry.stock.survivalRatePercent !== null
                        ? `${telemetry.stock.survivalRatePercent}% survival · ${telemetry.stock.totalGradedOut} graded`
                        : undefined
                "
                tone="cyan"
                :progress="telemetry.stock.survivalRatePercent"
            />
            <MetricCard
                label="Estimated biomass & FCR"
                :value="
                    telemetry.feeding.estimatedBiomassKg !== null
                        ? `${telemetry.feeding.estimatedBiomassKg} kg`
                        : '—'
                "
                :subtext="
                    telemetry.feeding.fcr
                        ? `FCR: ${telemetry.feeding.fcr} · ${telemetry.feeding.totalFeedKg} kg fed`
                        : `${telemetry.feeding.totalFeedKg} kg feed given`
                "
                :icon="Scale"
            />
            <MetricCard
                label="Water quality"
                :value="telemetry.aquaculture.waterStatus.toUpperCase()"
                :subtext="`pH ${telemetry.aquaculture.latestPh ?? '—'} · DO ${telemetry.aquaculture.latestDissolvedOxygen ?? '—'} mg/L`"
                :icon="Droplets"
                :tone="
                    telemetry.aquaculture.waterStatus === 'critical'
                        ? 'rose'
                        : telemetry.aquaculture.waterStatus === 'warning'
                          ? 'amber'
                          : 'cyan'
                "
            />
            <MetricCard
                label="Days of culture"
                :value="`DOC ${telemetry.ageDays}`"
                :subtext="`${telemetry.aquaculture.waterExchangeCount} water exchanges`"
                :progress="telemetry.progressPercent"
            />
        </section>

        <section v-else class="grid grid-cols-2 gap-4 xl:grid-cols-4">
            <MetricCard
                label="Current growth stage"
                :value="telemetry.crop.currentGrowthStageLabel ?? 'Planned'"
                :subtext="`${telemetry.crop.stageProgressPercent}% stage progression`"
                :icon="Sprout"
                tone="emerald"
                :progress="telemetry.crop.stageProgressPercent"
            />
            <MetricCard
                label="Moisture & irrigation"
                :value="`${telemetry.crop.totalRainfallMm} mm`"
                :subtext="`${telemetry.crop.irrigationCount} irrigation events logged`"
                :icon="Droplets"
                tone="cyan"
            />
            <MetricCard
                label="Latest pest pressure"
                :value="telemetry.crop.latestPestPressureLabel ?? 'None'"
                :tone="telemetry.crop.hasHighPestAlert ? 'rose' : 'default'"
            />
            <MetricCard
                label="Harvested output"
                :value="telemetry.crop.totalHarvestedQuantity"
                :subtext="
                    telemetry.crop.yieldPerUnitArea
                        ? `${telemetry.crop.yieldPerUnitArea} per unit area`
                        : `Day ${telemetry.ageDays}`
                "
            />
        </section>

        <!-- Smart Enterprise Advisor -->
        <section
            v-if="
                calculators.aquaculture ||
                calculators.poultry ||
                calculators.crop
            "
            class="rounded-2xl border bg-card p-5"
        >
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <div class="flex items-center gap-2">
                    <Sparkles class="size-4 text-primary" />
                    <h2 class="text-sm font-semibold">
                        {{
                            calculators.aquaculture
                                ? 'Fish Feeding Ration Guide'
                                : calculators.poultry
                                  ? 'Poultry Feed Phase Advisor'
                                  : 'Cost, Yield & Season Benchmark'
                        }}
                    </h2>
                </div>
                <Badge
                    v-if="calculators.aquaculture"
                    :variant="
                        calculators.aquaculture.feedingStatus === 'on_track'
                            ? 'default'
                            : calculators.aquaculture.feedingStatus ===
                                'no_data'
                              ? 'secondary'
                              : 'destructive'
                    "
                >
                    {{
                        calculators.aquaculture.feedingStatus === 'on_track'
                            ? 'Feeding on track'
                            : calculators.aquaculture.feedingStatus ===
                                'underfeeding'
                              ? 'Below ration target'
                              : calculators.aquaculture.feedingStatus ===
                                  'overfeeding'
                                ? 'Above ration target'
                                : 'No feed logged yet'
                    }}
                </Badge>
                <Badge v-else-if="calculators.poultry" variant="outline">
                    {{ calculators.poultry.currentPhase }}
                </Badge>
                <Badge v-else-if="calculators.crop" variant="outline">
                    {{ calculators.crop.totalArea }}
                    {{ calculators.crop.areaUnit }} total
                </Badge>
            </div>

            <div
                v-if="calculators.aquaculture"
                class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4"
            >
                <div class="rounded-xl bg-muted/40 p-3">
                    <p class="text-xs text-muted-foreground">
                        Recommended daily feed
                    </p>
                    <p class="mt-0.5 text-base font-semibold tabular-nums">
                        {{ calculators.aquaculture.recommendedDailyFeedKg }} kg
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ calculators.aquaculture.bodyWeightPercent }}% body wt
                    </p>
                </div>
                <div class="rounded-xl bg-muted/40 p-3">
                    <p class="text-xs text-muted-foreground">
                        Pellet size & meals
                    </p>
                    <p class="mt-0.5 text-base font-semibold">
                        {{ calculators.aquaculture.pelletSizeMm }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ calculators.aquaculture.mealsPerDay }} meals/day
                    </p>
                </div>
                <div class="rounded-xl bg-muted/40 p-3">
                    <p class="text-xs text-muted-foreground">
                        Estimated pond biomass
                    </p>
                    <p class="mt-0.5 text-base font-semibold tabular-nums">
                        {{ calculators.aquaculture.estimatedBiomassKg }} kg
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Avg {{ calculators.aquaculture.avgWeightG }} g/fish
                    </p>
                </div>
                <div class="rounded-xl bg-muted/40 p-3">
                    <p class="text-xs text-muted-foreground">
                        Logged avg daily feed
                    </p>
                    <p class="mt-0.5 text-base font-semibold tabular-nums">
                        {{ calculators.aquaculture.actualDailyFeedKg }} kg
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ calculators.aquaculture.currentStock }} live fish
                    </p>
                </div>
            </div>

            <div
                v-else-if="calculators.poultry"
                class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4"
            >
                <div class="rounded-xl bg-muted/40 p-3">
                    <p class="text-xs text-muted-foreground">
                        Current feed phase
                    </p>
                    <p class="mt-0.5 text-base font-semibold">
                        {{ calculators.poultry.currentPhase }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        Day {{ calculators.poultry.ageDays }} ({{
                            calculators.poultry.isLayer ? 'Layers' : 'Broilers'
                        }})
                    </p>
                </div>
                <div class="rounded-xl bg-muted/40 p-3">
                    <p class="text-xs text-muted-foreground">
                        Next feed transition
                    </p>
                    <p class="mt-0.5 text-base font-semibold">
                        {{ calculators.poultry.nextPhase ?? 'Final phase' }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{
                            calculators.poultry.daysToNextPhase !== null
                                ? `In ${calculators.poultry.daysToNextPhase} day(s)`
                                : 'Hold until harvest'
                        }}
                    </p>
                </div>
                <div class="rounded-xl bg-muted/40 p-3">
                    <p class="text-xs text-muted-foreground">
                        Target daily flock feed
                    </p>
                    <p class="mt-0.5 text-base font-semibold tabular-nums">
                        {{ calculators.poultry.recommendedDailyFeedKg }} kg
                    </p>
                    <p class="text-xs text-muted-foreground">
                        ~{{ calculators.poultry.gramsPerBird }} g/bird/day
                    </p>
                </div>
                <div class="rounded-xl bg-muted/40 p-3">
                    <p class="text-xs text-muted-foreground">
                        Logged avg daily feed
                    </p>
                    <p class="mt-0.5 text-base font-semibold tabular-nums">
                        {{ calculators.poultry.actualDailyFeedKg }} kg
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ calculators.poultry.currentStock }} live birds
                    </p>
                </div>
            </div>

            <div v-else-if="calculators.crop" class="space-y-4">
                <div class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
                    <div class="rounded-xl bg-muted/40 p-3">
                        <p class="text-xs text-muted-foreground">
                            Yield per {{ calculators.crop.areaUnit }}
                        </p>
                        <p class="mt-0.5 text-base font-semibold tabular-nums">
                            {{ calculators.crop.yieldPerArea ?? '—' }}
                        </p>
                    </div>
                    <div class="rounded-xl bg-muted/40 p-3">
                        <p class="text-xs text-muted-foreground">
                            Cost per {{ calculators.crop.areaUnit }}
                        </p>
                        <p class="mt-0.5 text-base font-semibold tabular-nums">
                            {{
                                calculators.crop.costPerArea !== null
                                    ? formatCurrency(
                                          calculators.crop.costPerArea,
                                          calculators.crop.currency,
                                      )
                                    : '—'
                            }}
                        </p>
                    </div>
                    <div class="rounded-xl bg-muted/40 p-3">
                        <p class="text-xs text-muted-foreground">
                            Revenue per {{ calculators.crop.areaUnit }}
                        </p>
                        <p class="mt-0.5 text-base font-semibold tabular-nums">
                            {{
                                calculators.crop.revenuePerArea !== null
                                    ? formatCurrency(
                                          calculators.crop.revenuePerArea,
                                          calculators.crop.currency,
                                      )
                                    : '—'
                            }}
                        </p>
                    </div>
                </div>

                <div
                    v-if="calculators.crop.seasonComparisons.length > 0"
                    class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3"
                >
                    <div
                        v-for="row in calculators.crop.seasonComparisons"
                        :key="row.id"
                        class="rounded-xl border px-3.5 py-2.5 text-xs"
                        :class="
                            row.isCurrent
                                ? 'border-primary bg-primary/5'
                                : 'bg-muted/20'
                        "
                    >
                        <div class="flex items-center justify-between gap-2">
                            <strong class="truncate text-sm">{{
                                row.name
                            }}</strong>
                            <Badge variant="outline">{{
                                row.statusLabel
                            }}</Badge>
                        </div>
                        <p class="mt-1 text-muted-foreground">
                            {{ row.totalArea }} {{ row.areaUnit }} · Harvest:
                            {{ row.harvestedQuantity }} · Yield/unit:
                            {{ row.yieldPerArea ?? '—' }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Charts Row -->
        <section class="grid gap-4 md:grid-cols-2">
            <div class="rounded-2xl border bg-card p-5">
                <h3 class="mb-3 text-sm font-semibold">
                    {{
                        cycle.domain === 'crop'
                            ? '14-day rainfall (mm)'
                            : '14-day feed usage (kg)'
                    }}
                </h3>
                <MiniBarChart
                    v-if="cycle.domain === 'crop'"
                    :labels="telemetry.series.dates"
                    :values="telemetry.series.rainfallMm"
                    unit="mm"
                    tone="cyan"
                />
                <MiniBarChart
                    v-else
                    :labels="telemetry.series.dates"
                    :values="telemetry.series.feedKg"
                    unit="kg"
                    :tone="cycle.domain === 'aquaculture' ? 'cyan' : 'emerald'"
                />
            </div>
            <div class="rounded-2xl border bg-card p-5">
                <h3 class="mb-3 text-sm font-semibold">
                    {{
                        cycle.domain === 'aquaculture'
                            ? 'Dissolved oxygen trend (mg/L)'
                            : cycle.domain === 'crop'
                              ? '14-day inputs applied'
                              : cycle.domain === 'poultry' &&
                                  telemetry.poultry.totalEggs > 0
                                ? '14-day egg production'
                                : '14-day mortality trend'
                    }}
                </h3>
                <SparklineChart
                    v-if="cycle.domain === 'aquaculture'"
                    :values="telemetry.series.dissolvedOxygen"
                    tone="cyan"
                />
                <MiniBarChart
                    v-else-if="cycle.domain === 'crop'"
                    :labels="telemetry.series.dates"
                    :values="telemetry.series.inputQuantity"
                    tone="emerald"
                />
                <MiniBarChart
                    v-else-if="
                        cycle.domain === 'poultry' &&
                        telemetry.poultry.totalEggs > 0
                    "
                    :labels="telemetry.series.dates"
                    :values="telemetry.series.eggs"
                    unit="eggs"
                    tone="amber"
                />
                <MiniBarChart
                    v-else
                    :labels="telemetry.series.dates"
                    :values="telemetry.series.mortality"
                    tone="rose"
                />
            </div>
        </section>

        <!-- Tabs -->
        <div
            class="flex flex-col gap-2 border-b pb-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between"
        >
            <div
                class="no-scrollbar -mx-4 flex min-w-0 flex-nowrap gap-1.5 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:overflow-visible sm:px-0 sm:pb-0"
            >
                <button
                    v-for="tab in [
                        { key: 'logs', label: `Daily Logs (${logs.length})` },
                        {
                            key: 'health',
                            label: `Health & Vaccines (${healthEvents.length})`,
                        },
                        {
                            key: 'stock',
                            label: `Inputs Used (${inventoryMovements.length})`,
                        },
                        ...(profitability
                            ? [{ key: 'profit', label: 'Profitability & P&L' }]
                            : []),
                    ]"
                    :key="tab.key"
                    type="button"
                    class="min-h-9 shrink-0 rounded-lg px-3 py-1.5 text-sm font-medium transition-colors"
                    :class="
                        activeTab === tab.key
                            ? 'bg-primary text-primary-foreground'
                            : 'text-muted-foreground hover:bg-accent hover:text-foreground'
                    "
                    @click="activeTab = tab.key as typeof activeTab"
                >
                    {{ tab.label }}
                </button>
            </div>
            <Button
                v-if="permissions.canManageFarmOperations"
                type="button"
                size="sm"
                variant="outline"
                @click="presetDrawerOpen = true"
            >
                <Sparkles class="mr-1.5 size-3.5" />
                Apply health preset
            </Button>
        </div>

        <!-- Tab: Daily Logs -->
        <section v-show="activeTab === 'logs'" class="space-y-3">
            <div v-if="logs.length > 0" class="grid gap-3">
                <article
                    v-for="log in logs"
                    :key="log.id"
                    class="flex flex-col justify-between gap-3 rounded-2xl border bg-card p-4 text-sm sm:flex-row sm:items-center"
                >
                    <div class="space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <strong>{{ log.logDate }}</strong>
                            <Badge
                                v-if="log.productionUnitName"
                                variant="outline"
                            >
                                {{ log.productionUnitName }}
                            </Badge>
                            <Badge
                                v-if="log.growthStageLabel"
                                variant="secondary"
                            >
                                {{ log.growthStageLabel }}
                            </Badge>
                            <Badge
                                v-if="log.inventoryItemName"
                                variant="secondary"
                            >
                                Stock: {{ log.inventoryItemName }}
                            </Badge>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            <span v-if="log.feedUsedKg">
                                Feed: {{ log.feedUsedKg }} kg
                                <span v-if="log.feedPelletSizeMm">
                                    ({{ log.feedPelletSizeMm }})
                                </span>
                                ·
                            </span>
                            <span v-if="log.mortalityCount > 0">
                                Mortality: {{ log.mortalityCount }} ·
                            </span>
                            <span v-if="log.gradedOutCount > 0">
                                Graded out: {{ log.gradedOutCount }} ·
                            </span>
                            <span v-if="log.eggsCollected">
                                Eggs: {{ log.eggsCollected }} ·
                            </span>
                            <span v-if="log.waterPh || log.dissolvedOxygenMgL">
                                pH {{ log.waterPh ?? '—' }} / DO
                                {{ log.dissolvedOxygenMgL ?? '—' }} mg/L ·
                            </span>
                            <span v-if="log.averageWeightG">
                                Avg wt: {{ log.averageWeightG }}g ·
                            </span>
                            <span v-if="log.inputAppliedName">
                                Input: {{ log.inputAppliedName }}
                                {{ log.inputAppliedQuantity }}
                                {{ log.inputAppliedUnit }} ·
                            </span>
                            {{ log.notes || 'Logged' }}
                        </p>
                        <div
                            v-if="log.evidence.length"
                            class="flex flex-wrap gap-2 pt-1"
                        >
                            <a
                                v-for="file in log.evidence"
                                :key="file.id"
                                :href="file.downloadUrl"
                                class="rounded-md border px-2 py-0.5 text-xs text-primary"
                            >
                                {{ file.fileName }}
                            </a>
                        </div>
                    </div>
                    <Form
                        v-if="permissions.canManageFarmOperations && teamSlug"
                        v-bind="
                            destroyProductionLog.form([
                                teamSlug,
                                farm.id,
                                cycle.id,
                                log.id,
                            ])
                        "
                    >
                        <Button
                            type="submit"
                            size="icon"
                            variant="ghost"
                            class="text-muted-foreground hover:text-destructive"
                        >
                            <Trash2 class="size-4" />
                        </Button>
                    </Form>
                </article>
            </div>
            <EmptyState
                v-else
                title="No daily logs recorded yet"
                description="Start logging daily feed, mortality, water quality, or field scouting for this cycle."
                :icon="Activity"
            />
        </section>

        <!-- Tab: Health -->
        <section v-show="activeTab === 'health'" class="space-y-3">
            <div
                v-if="healthEvents.length > 0"
                class="divide-y rounded-2xl border bg-card"
            >
                <div
                    v-for="ev in healthEvents"
                    :key="ev.id"
                    class="flex flex-wrap items-center justify-between gap-3 p-4 text-sm"
                >
                    <div>
                        <p class="font-medium">{{ ev.title }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ ev.eventTypeLabel }} · Scheduled
                            {{ ev.scheduledOn }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Badge
                            :variant="
                                ev.isOverdue ? 'destructive' : 'secondary'
                            "
                        >
                            {{ ev.isOverdue ? 'Overdue' : ev.statusLabel }}
                        </Badge>
                        <Form
                            v-if="
                                ev.status === 'scheduled' &&
                                permissions.canManageFieldWork &&
                                teamSlug
                            "
                            v-bind="updateHealthEvent.form([teamSlug, ev.id])"
                        >
                            <input
                                type="hidden"
                                name="status"
                                value="completed"
                            />
                            <input
                                type="hidden"
                                name="completed_on"
                                :value="today"
                            />
                            <Button type="submit" size="sm">Done</Button>
                        </Form>
                    </div>
                </div>
            </div>
            <EmptyState
                v-else
                title="No health events for this cycle"
                description="Apply a vaccination or biosecurity schedule preset in one click."
                :icon="HeartPulse"
            />
        </section>

        <!-- Tab: Stock Used -->
        <section v-show="activeTab === 'stock'" class="space-y-3">
            <div
                v-if="inventoryMovements.length > 0"
                class="divide-y rounded-2xl border bg-card"
            >
                <div
                    v-for="move in inventoryMovements"
                    :key="move.id"
                    class="flex items-center justify-between gap-3 p-4 text-sm"
                >
                    <div>
                        <p class="font-medium">{{ move.itemName }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ move.movementDate }} ·
                            {{ move.movementTypeLabel }}
                        </p>
                    </div>
                    <div class="text-right tabular-nums">
                        <p class="font-semibold">
                            {{ move.quantity }} {{ move.itemUnit }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ formatCurrency(move.totalCost, move.currency) }}
                        </p>
                    </div>
                </div>
            </div>
            <p v-else class="text-sm text-muted-foreground">
                No inventory movements linked to this cycle yet.
            </p>
        </section>

        <!-- Tab: Profitability -->
        <section
            v-if="profitability"
            v-show="activeTab === 'profit'"
            class="space-y-4"
        >
            <div class="grid grid-cols-2 gap-4 xl:grid-cols-4">
                <MetricCard
                    label="Total cycle cost"
                    :value="
                        formatCurrency(
                            profitability.totalCost,
                            profitability.currency,
                        )
                    "
                    tone="amber"
                />
                <MetricCard
                    label="Net sales revenue"
                    :value="
                        formatCurrency(
                            profitability.netRevenue,
                            profitability.currency,
                        )
                    "
                    tone="emerald"
                />
                <MetricCard
                    label="Gross profit / loss"
                    :value="
                        formatCurrency(
                            profitability.grossProfit,
                            profitability.currency,
                        )
                    "
                    :subtext="
                        profitability.marginPercent !== null
                            ? `${profitability.marginPercent}% margin`
                            : undefined
                    "
                    :icon="Landmark"
                />
                <MetricCard
                    label="Unit economics"
                    :value="
                        profitability.costPerLiveUnit
                            ? formatCurrency(
                                  profitability.costPerLiveUnit,
                                  profitability.currency,
                              )
                            : profitability.costPerHarvestedUnit
                              ? formatCurrency(
                                    profitability.costPerHarvestedUnit,
                                    profitability.currency,
                                )
                              : '—'
                    "
                    :subtext="
                        profitability.costPerLiveUnit
                            ? 'Cost per live bird/fish'
                            : 'Cost per unit harvested'
                    "
                />
            </div>
            <div class="rounded-2xl border bg-card p-5 text-sm">
                <h3 class="font-semibold">Cost breakdown</h3>
                <div class="mt-3 grid gap-3 sm:grid-cols-4">
                    <div>
                        <span class="block text-xs text-muted-foreground">
                            Direct expenses
                        </span>
                        <strong class="tabular-nums">
                            {{
                                formatCurrency(
                                    profitability.directExpenses,
                                    profitability.currency,
                                )
                            }}
                        </strong>
                    </div>
                    <div>
                        <span class="block text-xs text-muted-foreground">
                            Un-expensed stock used
                        </span>
                        <strong class="tabular-nums">
                            {{
                                formatCurrency(
                                    profitability.inventoryUsage,
                                    profitability.currency,
                                )
                            }}
                        </strong>
                    </div>
                    <div>
                        <span class="block text-xs text-muted-foreground">
                            Health & vaccines
                        </span>
                        <strong class="tabular-nums">
                            {{
                                formatCurrency(
                                    profitability.healthCosts,
                                    profitability.currency,
                                )
                            }}
                        </strong>
                    </div>
                    <div>
                        <span class="block text-xs text-muted-foreground">
                            Harvest labour
                        </span>
                        <strong class="tabular-nums">
                            {{
                                formatCurrency(
                                    profitability.harvestLabour,
                                    profitability.currency,
                                )
                            }}
                        </strong>
                    </div>
                </div>
            </div>
        </section>

        <!-- Daily Log Drawer -->
        <SlideOverForm
            :open="logDrawerOpen"
            :title="`Record daily log — ${cycle.name}`"
            wide
            @update:open="logDrawerOpen = $event"
        >
            <template v-if="teamSlug">
                <PoultryDailyLogForm
                    v-if="cycle.domain === 'poultry'"
                    :team-slug="teamSlug"
                    :farm-id="farm.id"
                    :cycle-id="cycle.id"
                    :units="cycle.productionUnits"
                    :inventory-items="inventoryItems"
                    :is-layer="cycle.enterpriseSubtype === 'layer'"
                    @saved="logDrawerOpen = false"
                />
                <FishDailyLogForm
                    v-else-if="cycle.domain === 'aquaculture'"
                    :team-slug="teamSlug"
                    :farm-id="farm.id"
                    :cycle-id="cycle.id"
                    :units="cycle.productionUnits"
                    :inventory-items="inventoryItems"
                    @saved="logDrawerOpen = false"
                />
                <CropDailyLogForm
                    v-else
                    :team-slug="teamSlug"
                    :farm-id="farm.id"
                    :cycle-id="cycle.id"
                    :units="cycle.productionUnits"
                    :inventory-items="inventoryItems"
                    :growth-stages="options.cropGrowthStages"
                    :pest-pressures="options.pestPressures"
                    :current-growth-stage="cycle.currentGrowthStage"
                    @saved="logDrawerOpen = false"
                />
            </template>
        </SlideOverForm>

        <!-- Edit Cycle Drawer -->
        <SlideOverForm
            :open="editDrawerOpen"
            title="Edit production cycle"
            @update:open="editDrawerOpen = $event"
        >
            <Form
                v-if="teamSlug"
                v-bind="updateCycle.form([teamSlug, farm.id, cycle.id])"
                class="grid gap-4"
                v-slot="{ errors, processing }"
                @success="editDrawerOpen = false"
            >
                <div class="grid gap-1.5">
                    <Label for="ec-name">Cycle name</Label>
                    <Input
                        id="ec-name"
                        name="name"
                        :default-value="cycle.name"
                        required
                    />
                    <InputError :message="errors.name" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="ec-status">Status</Label>
                    <select
                        id="ec-status"
                        name="status"
                        class="h-11 rounded-md border bg-background px-3 text-base md:h-9 md:text-sm"
                    >
                        <option
                            v-for="opt in options.cycleStatuses"
                            :key="opt.value"
                            :value="opt.value"
                            :selected="opt.value === cycle.status"
                        >
                            {{ opt.label }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="ec-breed">Breed / variety</Label>
                    <Input
                        id="ec-breed"
                        name="breed_or_variety"
                        :default-value="cycle.breedOrVariety ?? ''"
                    />
                </div>
                <div v-if="cycle.domain !== 'crop'" class="grid gap-1.5">
                    <Label for="ec-stock">Initial stock count</Label>
                    <Input
                        id="ec-stock"
                        name="initial_stock_count"
                        type="number"
                        inputmode="numeric"
                        min="0"
                        :default-value="cycle.initialStockCount ?? ''"
                    />
                </div>
                <div v-else class="grid gap-1.5">
                    <Label for="ec-stage">Growth stage</Label>
                    <select
                        id="ec-stage"
                        name="current_growth_stage"
                        class="h-11 rounded-md border bg-background px-3 text-base md:h-9 md:text-sm"
                    >
                        <option value="">Not set</option>
                        <option
                            v-for="opt in options.cropGrowthStages"
                            :key="opt.value"
                            :value="opt.value"
                            :selected="opt.value === cycle.currentGrowthStage"
                        >
                            {{ opt.label }}
                        </option>
                    </select>
                </div>
                <Button
                    type="submit"
                    :disabled="processing"
                    class="min-h-11 w-full sm:min-h-9 sm:w-auto"
                >
                    Save cycle
                </Button>
            </Form>
        </SlideOverForm>

        <!-- Grade Fish Drawer -->
        <GradeFishModal
            v-if="teamSlug && cycle.domain === 'aquaculture'"
            :open="gradeDrawerOpen"
            :team-slug="teamSlug"
            :farm-id="farm.id"
            :cycle-id="cycle.id"
            :units="farm.units"
            :sibling-cycles="siblingCycles"
            @update:open="gradeDrawerOpen = $event"
        />

        <!-- Health Preset Drawer -->
        <SlideOverForm
            :open="presetDrawerOpen"
            title="Apply health schedule preset"
            @update:open="presetDrawerOpen = $event"
        >
            <Form
                v-if="teamSlug"
                v-bind="applyHealthPreset.form([teamSlug, farm.id, cycle.id])"
                class="grid gap-4"
                v-slot="{ processing }"
                @success="presetDrawerOpen = false"
            >
                <div class="grid gap-1.5">
                    <Label for="cycle-preset-key">Preset</Label>
                    <select
                        id="cycle-preset-key"
                        name="preset_key"
                        class="h-11 rounded-md border bg-background px-3 text-base md:h-9 md:text-sm"
                        required
                    >
                        <option
                            v-for="p in options.healthPresets"
                            :key="p.key"
                            :value="p.key"
                        >
                            {{ p.label }} ({{ p.eventCount }} events)
                        </option>
                    </select>
                </div>
                <Button
                    type="submit"
                    :disabled="processing"
                    class="min-h-11 w-full sm:min-h-9 sm:w-auto"
                >
                    Generate schedule
                </Button>
            </Form>
        </SlideOverForm>

        <!-- Quick Egg Crate Sale Drawer -->
        <SlideOverForm
            :open="eggSaleDrawerOpen"
            title="Quick egg crate sale"
            description="Records egg crate harvest and sales income in one step"
            @update:open="eggSaleDrawerOpen = $event"
        >
            <Form
                v-if="teamSlug && cycle.domain === 'poultry'"
                v-bind="storeQuickEggSale.form([teamSlug, farm.id, cycle.id])"
                class="grid gap-4"
                reset-on-success
                v-slot="{ errors, processing }"
                @success="eggSaleDrawerOpen = false"
            >
                <div class="grid gap-1.5">
                    <Label for="egg-sold-on">Sale date</Label>
                    <Input
                        id="egg-sold-on"
                        name="sold_on"
                        type="date"
                        :default-value="today"
                        required
                    />
                    <InputError :message="errors.sold_on" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-1.5">
                        <Label for="egg-crates">Crates sold (30 eggs)</Label>
                        <Input
                            id="egg-crates"
                            name="crates_sold"
                            type="number"
                            inputmode="decimal"
                            min="0.1"
                            step="0.01"
                            placeholder="10"
                            required
                        />
                        <InputError :message="errors.crates_sold" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="egg-price">Price per crate</Label>
                        <Input
                            id="egg-price"
                            name="price_per_crate"
                            type="number"
                            inputmode="decimal"
                            min="0"
                            step="0.01"
                            placeholder="4500"
                            required
                        />
                        <InputError :message="errors.price_per_crate" />
                    </div>
                </div>
                <div class="grid gap-1.5">
                    <Label for="egg-buyer">Buyer name</Label>
                    <Input
                        id="egg-buyer"
                        name="buyer_name"
                        placeholder="e.g. Mama Nkechi Stores"
                        required
                    />
                    <InputError :message="errors.buyer_name" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="egg-payment-status">Payment status</Label>
                    <select
                        id="egg-payment-status"
                        name="payment_status"
                        class="h-11 rounded-md border bg-background px-3 text-base md:h-9 md:text-sm"
                        required
                    >
                        <option
                            v-for="opt in options.paymentStatuses"
                            :key="opt.value"
                            :value="opt.value"
                        >
                            {{ opt.label }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="egg-sale-notes">Notes (optional)</Label>
                    <Input id="egg-sale-notes" name="notes" />
                </div>
                <Button
                    type="submit"
                    :disabled="processing"
                    class="min-h-11 w-full sm:min-h-9 sm:w-auto"
                >
                    Record crate sale
                </Button>
            </Form>
        </SlideOverForm>
    </div>
</template>
