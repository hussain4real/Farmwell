<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    ArrowRight,
    CalendarDays,
    ClipboardList,
    Droplets,
    HeartPulse,
    Landmark,
    ListTodo,
    MessageSquare,
    Package,
    Plus,
    PlusCircle,
    Share2,
    ShieldAlert,
    Sprout,
    Tractor,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import EmptyState from '@/components/farm/EmptyState.vue';
import FarmTypeBadge from '@/components/farm/FarmTypeBadge.vue';
import MetricCard from '@/components/farm/MetricCard.vue';
import PageHero from '@/components/farm/PageHero.vue';
import type { LogInventoryOption } from '@/components/farm/PoultryDailyLogForm.vue';
import QuickDailyLogModal from '@/components/farm/QuickDailyLogModal.vue';
import type { QuickLogFarm } from '@/components/farm/QuickDailyLogModal.vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatCurrency } from '@/lib/currency';
import { dashboard } from '@/routes';
import { index as farmTasksIndex } from '@/routes/farm-tasks';
import { index as farmsIndex, show as farmsShow } from '@/routes/farms';
import { show as showCycle } from '@/routes/farms/production-cycles';
import { index as fieldDiaryIndex } from '@/routes/field-diary';
import { index as financeIndex } from '@/routes/finance';
import { index as harvestsIndex } from '@/routes/harvests';
import { index as healthIndex } from '@/routes/health';
import { index as inventoryIndex } from '@/routes/inventory';
import { index as onboardingIndex } from '@/routes/onboarding';
import { index as whatsappIntakesIndex } from '@/routes/whatsapp-intakes';
import type {
    FarmActivity,
    FarmOperationPermissions,
    FarmOption,
    FarmStats,
    FarmTask,
    FinanceDashboardSummary,
    HarvestSummary,
    ProductionPlanChange,
    Team,
    WhatsappIntake,
} from '@/types';

type FarmHighlight = Omit<QuickLogFarm, 'activeCycles'> & {
    farmTypeLabel: string;
    location: string | null;
    status: string;
    productionUnitsCount: number;
    productionCyclesCount: number;
    liveStockTotal: number;
    activeCycles: {
        id: number;
        name: string;
        status: string;
        statusLabel: string;
        currentStockCount: number | null;
        currentGrowthStage: string | null;
        currentGrowthStageLabel: string | null;
    }[];
};

type Props = {
    permissions: FarmOperationPermissions;
    stats: FarmStats;
    farmHighlights: FarmHighlight[];
    alerts: {
        overdueTasks: number;
        lowStockCount: number;
        lowStockNames: {
            id: number;
            name: string;
            currentQuantity: string;
            unit: string;
        }[];
        upcomingHealthCount: number;
        upcomingHealthEvents: {
            id: number;
            title: string;
            farmName: string;
            cycleName: string | null;
            scheduledOn: string;
            isOverdue: boolean;
        }[];
        activeWithdrawals: number;
        waterQualityAlertCount?: number;
        whatsappDigestText?: string | null;
    };
    quickLogInventory: LogInventoryOption[];
    quickLogOptions: {
        cropGrowthStages: FarmOption[];
        pestPressures: FarmOption[];
    };
    latestActivities: FarmActivity[];
    upcomingTasks: FarmTask[];
    pendingIntakes: WhatsappIntake[];
    latestPlanChanges: ProductionPlanChange[];
    financeSummary: FinanceDashboardSummary | null;
    harvestSummary: HarvestSummary | null;
};

const props = defineProps<Props>();
const page = usePage();

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: props.currentTeam
                    ? dashboard(props.currentTeam.slug)
                    : '/',
            },
        ],
    }),
});

const currentTeam = computed(() => page.props.currentTeam as Team | null);
const currentTeamSlug = computed(() => currentTeam.value?.slug ?? '');
const investorMode = computed(
    () => page.props.currentTeamFeatures?.investorMode === true,
);

const quickLogOpen = ref(false);
const quickLogFarmId = ref<number | null>(null);

const openQuickLog = (farmId?: number) => {
    quickLogFarmId.value = farmId ?? props.farmHighlights[0]?.id ?? null;
    quickLogOpen.value = true;
};

const workspaceLinks = computed(() => {
    if (!currentTeamSlug.value) {
        return [];
    }

    const permissions = page.props.currentTeamPermissions;

    return [
        {
            title: 'Farms & Cycles',
            href: farmsIndex(currentTeamSlug.value).url,
            description: 'Houses, ponds, fields, batches & telemetry',
            icon: Tractor,
            visible: permissions?.canViewFarmOperations,
        },
        {
            title: 'Stock & Feed',
            href: inventoryIndex(currentTeamSlug.value).url,
            description: 'Feed bags, seeds, fertilizers & low-stock alerts',
            icon: Package,
            visible: true,
        },
        {
            title: 'Health & Vaccines',
            href: healthIndex(currentTeamSlug.value).url,
            description: 'Vaccination schedules, treatments & withdrawals',
            icon: HeartPulse,
            visible: true,
        },
        {
            title: 'Field Diary',
            href: fieldDiaryIndex(currentTeamSlug.value).url,
            description: 'Activities, photo evidence & operating notes',
            icon: ClipboardList,
            visible: true,
        },
        {
            title: 'Tasks',
            href: farmTasksIndex(currentTeamSlug.value).url,
            description: 'Daily checklists, due dates & delay tracking',
            icon: ListTodo,
            visible: true,
        },
        {
            title: 'Harvests & Sales',
            href: harvestsIndex(currentTeamSlug.value).url,
            description: 'Output tracking, buyer sales & margins',
            icon: Sprout,
            visible:
                permissions?.canViewFarmOperations ||
                permissions?.canViewFinance,
        },
        {
            title: 'Finance',
            href: financeIndex(currentTeamSlug.value).url,
            description: 'Budgets, expenses & variance tracking',
            icon: Landmark,
            visible: permissions?.canViewFinance,
        },
        {
            title: 'WhatsApp Intake',
            href: whatsappIntakesIndex(currentTeamSlug.value).url,
            description: 'Review & convert forwarded field messages',
            icon: MessageSquare,
            visible: permissions?.canViewFarmOperations,
        },
    ].filter((link) => link.visible);
});

const shareDailyAlerts = async () => {
    const text = props.alerts.whatsappDigestText;

    if (!text) {
        return;
    }

    if (
        typeof navigator !== 'undefined' &&
        typeof navigator.share === 'function'
    ) {
        try {
            await navigator.share({ title: 'Farmwell Daily Alerts', text });

            return;
        } catch {
            // Fall through to WhatsApp link / clipboard copy.
        }
    }

    if (typeof navigator !== 'undefined' && navigator.clipboard) {
        await navigator.clipboard.writeText(text).catch(() => {});
        toast.success('Daily alert summary copied — ready for WhatsApp.');
    }
};
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Head title="Dashboard" />

        <PageHero
            eyebrow="Farm Operations Hub"
            :title="currentTeam?.name ?? 'Dashboard'"
            :description="
                investorMode
                    ? 'Live poultry, crop & fish operations with investor transparency enabled.'
                    : 'Your daily command center for poultry, crop, and fish operations.'
            "
        >
            <template #actions>
                <Button
                    v-if="
                        permissions.canManageFieldWork &&
                        farmHighlights.length > 0
                    "
                    type="button"
                    class="bg-white text-[#1b4d3e] hover:bg-emerald-50"
                    @click="openQuickLog()"
                >
                    <Plus class="mr-1.5 size-4" />
                    Quick daily log
                </Button>
                <Link
                    v-if="
                        permissions.canManageFarmOperations && currentTeamSlug
                    "
                    :href="onboardingIndex(currentTeamSlug)"
                    class="inline-flex h-9 items-center gap-1.5 rounded-md border border-white/25 bg-white/10 px-4 text-sm font-medium text-white transition-colors hover:bg-white/20"
                >
                    <PlusCircle class="size-4" />
                    Onboard farm
                </Link>
            </template>
            <template #metrics>
                <div class="px-5 py-3.5 sm:px-7">
                    <span class="block text-xs text-emerald-200/80">Farms</span>
                    <strong
                        class="mt-0.5 block text-lg font-semibold tabular-nums"
                    >
                        {{ stats.farms }}
                    </strong>
                </div>
                <div class="border-l border-white/15 px-5 py-3.5 sm:px-7">
                    <span class="block text-xs text-emerald-200/80">
                        Active cycles
                    </span>
                    <strong
                        class="mt-0.5 block text-lg font-semibold tabular-nums"
                    >
                        {{ stats.productionCycles }}
                    </strong>
                </div>
                <div
                    class="border-t border-white/15 px-5 py-3.5 sm:border-t-0 sm:border-l sm:px-7"
                >
                    <span class="block text-xs text-emerald-200/80">
                        Open tasks
                    </span>
                    <strong
                        class="mt-0.5 block text-lg font-semibold tabular-nums"
                    >
                        {{ stats.openTasks }}
                    </strong>
                </div>
                <div
                    class="border-t border-l border-white/15 px-5 py-3.5 sm:border-t-0 sm:px-7"
                >
                    <span class="block text-xs text-emerald-200/80">
                        Diary entries
                    </span>
                    <strong
                        class="mt-0.5 block text-lg font-semibold tabular-nums"
                    >
                        {{ stats.activities }}
                    </strong>
                </div>
            </template>
        </PageHero>

        <!-- Empty Onboarding Callout -->
        <EmptyState
            v-if="stats.farms === 0"
            title="Welcome to Farmwell — launch your first farm in 2 minutes"
            description="Choose Poultry (Broilers/Layers), Crop Farming, or Fish Farming (Catfish/Tilapia) and Farmwell sets up your units, first batch, tasks, stock, and vaccine schedule."
            :icon="Tractor"
        >
            <template #action>
                <Link
                    v-if="
                        permissions.canManageFarmOperations && currentTeamSlug
                    "
                    :href="onboardingIndex(currentTeamSlug)"
                    class="inline-flex h-10 items-center gap-2 rounded-lg bg-primary px-5 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                >
                    <PlusCircle class="size-4" />
                    Start guided farm setup
                </Link>
            </template>
        </EmptyState>

        <!-- Operational Alerts Strip -->
        <section
            v-if="
                alerts.overdueTasks > 0 ||
                alerts.lowStockCount > 0 ||
                alerts.upcomingHealthCount > 0 ||
                alerts.activeWithdrawals > 0 ||
                (alerts.waterQualityAlertCount ?? 0) > 0
            "
            class="space-y-3"
        >
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2
                    class="text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    Today's alerts
                </h2>
                <Button
                    v-if="alerts.whatsappDigestText"
                    type="button"
                    size="sm"
                    variant="outline"
                    class="min-h-9"
                    @click="shareDailyAlerts"
                >
                    <Share2 class="mr-1.5 size-3.5" />
                    Share daily alerts
                </Button>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <Link
                    v-if="alerts.overdueTasks > 0 && currentTeamSlug"
                    :href="farmTasksIndex(currentTeamSlug)"
                    class="flex items-center gap-3 rounded-xl border border-rose-200 bg-rose-50/70 p-3.5 text-sm text-rose-900 transition-colors hover:bg-rose-50 dark:border-rose-900 dark:bg-rose-950/30 dark:text-rose-200"
                >
                    <AlertTriangle class="size-5 shrink-0" />
                    <div>
                        <p class="font-semibold">
                            {{ alerts.overdueTasks }} overdue task(s)
                        </p>
                        <p class="text-xs opacity-80">Review task calendar</p>
                    </div>
                </Link>
                <Link
                    v-if="alerts.lowStockCount > 0 && currentTeamSlug"
                    :href="inventoryIndex(currentTeamSlug)"
                    class="flex items-center gap-3 rounded-xl border border-amber-200 bg-amber-50/70 p-3.5 text-sm text-amber-900 transition-colors hover:bg-amber-50 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200"
                >
                    <Package class="size-5 shrink-0" />
                    <div>
                        <p class="font-semibold">
                            {{ alerts.lowStockCount }} low-stock item(s)
                        </p>
                        <p class="text-xs opacity-80">
                            {{
                                alerts.lowStockNames
                                    .map((i) => i.name)
                                    .join(', ')
                            }}
                        </p>
                    </div>
                </Link>
                <Link
                    v-if="alerts.upcomingHealthCount > 0 && currentTeamSlug"
                    :href="healthIndex(currentTeamSlug)"
                    class="flex items-center gap-3 rounded-xl border border-cyan-200 bg-cyan-50/70 p-3.5 text-sm text-cyan-900 transition-colors hover:bg-cyan-50 dark:border-cyan-900 dark:bg-cyan-950/30 dark:text-cyan-200"
                >
                    <HeartPulse class="size-5 shrink-0" />
                    <div>
                        <p class="font-semibold">
                            {{ alerts.upcomingHealthCount }} health event(s) due
                        </p>
                        <p class="text-xs opacity-80">Next 7 days</p>
                    </div>
                </Link>
                <Link
                    v-if="alerts.activeWithdrawals > 0 && currentTeamSlug"
                    :href="healthIndex(currentTeamSlug)"
                    class="flex items-center gap-3 rounded-xl border border-amber-300 bg-amber-50 p-3.5 text-sm text-amber-950 transition-colors hover:bg-amber-100/70 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
                >
                    <ShieldAlert class="size-5 shrink-0" />
                    <div>
                        <p class="font-semibold">
                            {{ alerts.activeWithdrawals }} withdrawal window(s)
                        </p>
                        <p class="text-xs opacity-80">
                            Check safe harvest dates
                        </p>
                    </div>
                </Link>
                <Link
                    v-if="
                        (alerts.waterQualityAlertCount ?? 0) > 0 &&
                        currentTeamSlug
                    "
                    :href="farmsIndex(currentTeamSlug)"
                    class="flex items-center gap-3 rounded-xl border border-rose-300 bg-rose-50 p-3.5 text-sm text-rose-950 transition-colors hover:bg-rose-100/70 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-200"
                >
                    <Droplets class="size-5 shrink-0" />
                    <div>
                        <p class="font-semibold">
                            {{ alerts.waterQualityAlertCount }} pond water
                            alert(s)
                        </p>
                        <p class="text-xs opacity-80">
                            Check aquaculture cycles
                        </p>
                    </div>
                </Link>
            </div>
        </section>

        <!-- Active Farms Command Cards -->
        <section v-if="farmHighlights.length > 0" class="space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-semibold">Active farms</h2>
                <Link
                    v-if="currentTeamSlug"
                    :href="farmsIndex(currentTeamSlug)"
                    class="text-sm font-medium text-primary hover:underline"
                >
                    View all farms
                </Link>
            </div>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                <article
                    v-for="farm in farmHighlights"
                    :key="farm.id"
                    class="flex flex-col justify-between rounded-2xl border bg-card p-5 shadow-xs transition-shadow hover:shadow-sm"
                >
                    <div class="space-y-3">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <Link
                                    v-if="currentTeamSlug"
                                    :href="
                                        farmsShow([currentTeamSlug, farm.id])
                                    "
                                    class="text-base font-semibold hover:text-primary hover:underline"
                                >
                                    {{ farm.name }}
                                </Link>
                                <p class="text-xs text-muted-foreground">
                                    {{ farm.location ?? 'No location set' }} ·
                                    {{ farm.productionUnitsCount }} unit(s)
                                </p>
                            </div>
                            <FarmTypeBadge
                                :farm-type="farm.farmType"
                                :label="farm.farmTypeLabel"
                            />
                        </div>

                        <div
                            v-if="farm.activeCycles.length > 0"
                            class="space-y-1.5 rounded-xl bg-muted/40 p-3 text-xs"
                        >
                            <div
                                v-for="cycle in farm.activeCycles.slice(0, 2)"
                                :key="cycle.id"
                                class="flex items-center justify-between gap-2"
                            >
                                <Link
                                    v-if="currentTeamSlug"
                                    :href="
                                        showCycle([
                                            currentTeamSlug,
                                            farm.id,
                                            cycle.id,
                                        ])
                                    "
                                    class="truncate font-medium hover:text-primary hover:underline"
                                >
                                    {{ cycle.name }}
                                </Link>
                                <span class="shrink-0 text-muted-foreground">
                                    {{
                                        cycle.currentStockCount !== null
                                            ? `${cycle.currentStockCount} live`
                                            : (cycle.currentGrowthStageLabel ??
                                              cycle.statusLabel)
                                    }}
                                </span>
                            </div>
                        </div>
                        <p v-else class="text-xs text-muted-foreground">
                            No active cycles yet.
                        </p>
                    </div>

                    <div
                        class="mt-4 flex items-center justify-between gap-2 border-t pt-3"
                    >
                        <Button
                            v-if="
                                permissions.canManageFieldWork &&
                                farm.activeCycles.length > 0
                            "
                            type="button"
                            size="sm"
                            variant="outline"
                            @click="openQuickLog(farm.id)"
                        >
                            <Plus class="mr-1 size-3.5" />
                            Quick log
                        </Button>
                        <Link
                            v-if="currentTeamSlug"
                            :href="farmsShow([currentTeamSlug, farm.id])"
                            class="ml-auto inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline"
                        >
                            Command center
                            <ArrowRight class="size-3.5" />
                        </Link>
                    </div>
                </article>
            </div>
        </section>

        <!-- Financial & Harvest KPI Strip -->
        <section
            v-if="financeSummary || harvestSummary"
            class="grid grid-cols-2 gap-4 xl:grid-cols-4"
        >
            <MetricCard
                v-if="financeSummary"
                label="Budgeted"
                :value="
                    formatCurrency(
                        financeSummary.budgeted,
                        financeSummary.currency,
                    )
                "
                :icon="Landmark"
            />
            <MetricCard
                v-if="financeSummary"
                label="Spent"
                :value="
                    formatCurrency(
                        financeSummary.spent,
                        financeSummary.currency,
                    )
                "
                :icon="Landmark"
                tone="amber"
            />
            <MetricCard
                v-if="harvestSummary"
                label="Net sales"
                :value="
                    formatCurrency(
                        harvestSummary.saleNet,
                        harvestSummary.currency ?? '',
                    )
                "
                :icon="Sprout"
                tone="emerald"
            />
            <MetricCard
                v-if="investorMode && harvestSummary"
                label="Capital recovered"
                :value="
                    formatCurrency(
                        harvestSummary.capitalRecovered,
                        harvestSummary.currency ?? '',
                    )
                "
                :subtext="`Investor share: ${formatCurrency(harvestSummary.investorShare, harvestSummary.currency ?? '')}`"
                :icon="Landmark"
            />
            <MetricCard
                v-else-if="financeSummary"
                label="Budget variance"
                :value="
                    formatCurrency(
                        financeSummary.variance.variance,
                        financeSummary.currency,
                    )
                "
                :icon="CalendarDays"
            />
        </section>

        <!-- Workspace Quick Links -->
        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <Link
                v-for="workspace in workspaceLinks"
                :key="workspace.title"
                :href="workspace.href"
                class="rounded-xl border bg-card p-4 transition-colors hover:bg-accent/50"
            >
                <div class="flex items-start gap-3">
                    <span
                        class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-accent text-accent-foreground"
                    >
                        <component :is="workspace.icon" class="size-4" />
                    </span>
                    <div>
                        <h3 class="text-sm font-semibold">
                            {{ workspace.title }}
                        </h3>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {{ workspace.description }}
                        </p>
                    </div>
                </div>
            </Link>
        </section>

        <!-- Activity & Task Feeds -->
        <section class="grid gap-6 xl:grid-cols-2">
            <div class="space-y-4 rounded-2xl border bg-card p-5">
                <div class="flex items-center gap-2">
                    <ClipboardList class="size-4 text-primary" />
                    <Heading
                        variant="small"
                        title="Recent activities"
                        description="Latest diary entries and evidence"
                    />
                </div>

                <div v-if="latestActivities.length > 0" class="space-y-3">
                    <article
                        v-for="activity in latestActivities"
                        :key="activity.id"
                        class="space-y-1.5 rounded-xl border p-3 text-sm"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-medium">
                                    {{ activity.farmName }} /
                                    {{ activity.activityType }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ activity.activityDate }}
                                </p>
                            </div>
                            <Badge variant="secondary">
                                {{ activity.statusLabel }}
                            </Badge>
                        </div>
                        <p class="text-muted-foreground">
                            {{ activity.description }}
                        </p>
                    </article>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    No activity entries yet.
                </p>
            </div>

            <div class="space-y-4 rounded-2xl border bg-card p-5">
                <div class="flex items-center gap-2">
                    <ListTodo class="size-4 text-primary" />
                    <Heading
                        variant="small"
                        title="Upcoming tasks"
                        description="Open work that still needs attention"
                    />
                </div>

                <div v-if="upcomingTasks.length > 0" class="space-y-3">
                    <article
                        v-for="task in upcomingTasks"
                        :key="task.id"
                        class="space-y-1.5 rounded-xl border p-3 text-sm"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-medium">{{ task.title }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ task.farmName }} / due {{ task.dueOn }}
                                </p>
                            </div>
                            <Badge variant="secondary">
                                {{ task.statusLabel }}
                            </Badge>
                        </div>
                        <p
                            v-if="task.statusReason"
                            class="text-xs text-muted-foreground"
                        >
                            Reason: {{ task.statusReason }}
                        </p>
                    </article>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    No open tasks yet.
                </p>
            </div>
        </section>

        <!-- Quick Daily Log Slide-Over -->
        <QuickDailyLogModal
            v-if="currentTeamSlug"
            :open="quickLogOpen"
            :team-slug="currentTeamSlug"
            :farms="farmHighlights"
            :inventory-items="quickLogInventory"
            :growth-stages="quickLogOptions.cropGrowthStages"
            :pest-pressures="quickLogOptions.pestPressures"
            :initial-farm-id="quickLogFarmId"
            @update:open="quickLogOpen = $event"
        />
    </div>
</template>
