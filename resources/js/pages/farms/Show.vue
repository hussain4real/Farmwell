<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import {
    Activity,
    ArrowRight,
    CalendarDays,
    Edit3,
    HeartPulse,
    MapPin,
    Package,
    Plus,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import CropDailyLogForm from '@/components/farm/CropDailyLogForm.vue';
import EmptyState from '@/components/farm/EmptyState.vue';
import FarmTypeBadge from '@/components/farm/FarmTypeBadge.vue';
import FishDailyLogForm from '@/components/farm/FishDailyLogForm.vue';
import MetricCard from '@/components/farm/MetricCard.vue';
import PageHero from '@/components/farm/PageHero.vue';
import PoultryDailyLogForm from '@/components/farm/PoultryDailyLogForm.vue';
import type { LogInventoryOption } from '@/components/farm/PoultryDailyLogForm.vue';
import SlideOverForm from '@/components/farm/SlideOverForm.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCurrency } from '@/lib/currency';
import { dashboard } from '@/routes';
import {
    index as farmsIndex,
    show as farmsShow,
    update as updateFarm,
} from '@/routes/farms';
import {
    show as showCycle,
    store as storeCycle,
} from '@/routes/farms/production-cycles';
import {
    store as storeUnit,
    update as updateUnit,
} from '@/routes/farms/production-units';
import type { FarmOption, FarmOperationPermissions, Team } from '@/types';

type UnitRow = {
    id: number;
    name: string;
    unitType: string;
    unitTypeLabel: string;
    size: string | null;
    sizeUnit: string | null;
    capacity: string | null;
    status: string;
    waterSource: string | null;
    housingSystem: string | null;
};

type CycleRow = {
    id: number;
    name: string;
    season: string | null;
    enterpriseSubtypeLabel: string | null;
    breedOrVariety: string | null;
    plannedStartOn: string;
    plannedEndOn: string | null;
    initialStockCount: number | null;
    currentStockCount: number | null;
    currentGrowthStage: string | null;
    currentGrowthStageLabel: string | null;
    status: string;
    statusLabel: string;
    productionUnits: { id: number; name: string }[];
    commodities: { id: number; name: string; role: string }[];
    telemetry: {
        ageDays: number;
        progressPercent: number;
        feeding: { totalFeedKg: number; fcr: number | null };
        poultry: { totalEggs: number };
        aquaculture: { waterStatus: string };
    };
};

const props = defineProps<{
    permissions: FarmOperationPermissions;
    currency: string;
    farm: {
        id: number;
        name: string;
        farmType: string;
        farmTypeLabel: string;
        domain: string;
        location: string | null;
        status: string;
        registrationNumber: string | null;
        contactName: string | null;
        contactPhone: string | null;
        description: string | null;
        productionUnits: UnitRow[];
    };
    cycles: CycleRow[];
    recentLogs: {
        id: number;
        logDate: string;
        productionCycleName: string | null;
        productionUnitName: string | null;
        mortalityCount: number;
        feedUsedKg: string | null;
        eggsCollected: number | null;
        growthStageLabel: string | null;
        notes: string | null;
    }[];
    openTasks: {
        id: number;
        title: string;
        dueOn: string;
        statusLabel: string;
        unitName: string | null;
    }[];
    healthEvents: {
        id: number;
        title: string;
        scheduledOn: string;
        statusLabel: string;
        isOverdue: boolean;
    }[];
    inventoryItems: LogInventoryOption[];
    commodities: { id: number; name: string; measurementUnit: string | null }[];
    financeSummary: {
        spent: string;
        netSales: string;
        netMargin: string;
        harvestCount: number;
        scheduledHealthCount: number;
    } | null;
    options: {
        farmTypes: FarmOption[];
        enterpriseSubtypes: FarmOption[];
        productionUnitTypes: FarmOption[];
        productionCycleStatuses: FarmOption[];
        commodityRoles: FarmOption[];
        cropGrowthStages: FarmOption[];
        pestPressures: FarmOption[];
    };
}>();

defineOptions({
    layout: (props: {
        currentTeam?: Team | null;
        farm?: { id: number; name: string };
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
        ],
    }),
});

const page = usePage();
const teamSlug = computed(
    () => (page.props.currentTeam as Team | null)?.slug ?? '',
);

const activeTab = ref<'overview' | 'cycles' | 'logs' | 'health' | 'stock'>(
    'overview',
);
const editFarmOpen = ref(false);
const addUnitOpen = ref(false);
const editingUnit = ref<UnitRow | null>(null);
const addCycleOpen = ref(false);
const quickLogOpen = ref(false);
const selectedLogCycleId = ref(
    props.cycles[0]?.id ? String(props.cycles[0].id) : '',
);

const selectedLogCycle = computed(() =>
    props.cycles.find((c) => String(c.id) === selectedLogCycleId.value),
);

const totalLiveStock = computed(() =>
    props.cycles.reduce((acc, c) => acc + (c.currentStockCount ?? 0), 0),
);
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Head :title="farm.name" />

        <PageHero
            eyebrow="Farm Command Center"
            :title="farm.name"
            :description="
                [farm.location, farm.contactName, farm.description]
                    .filter(Boolean)
                    .join(' · ') ||
                'Manage units, batches, daily logs, and profitability.'
            "
        >
            <template #badges>
                <FarmTypeBadge
                    :farm-type="farm.farmType"
                    :label="farm.farmTypeLabel"
                />
                <Badge variant="secondary" class="capitalize">
                    {{ farm.status }}
                </Badge>
            </template>
            <template #actions>
                <Button
                    v-if="permissions.canManageFieldWork && cycles.length > 0"
                    type="button"
                    class="bg-white text-[#1b4d3e] hover:bg-emerald-50"
                    @click="quickLogOpen = true"
                >
                    <Plus class="mr-1.5 size-4" />
                    Log daily entry
                </Button>
                <Button
                    v-if="permissions.canManageFarmOperations"
                    type="button"
                    variant="secondary"
                    @click="editFarmOpen = true"
                >
                    <Edit3 class="mr-1.5 size-4" />
                    Edit farm
                </Button>
            </template>
            <template #metrics>
                <div class="px-5 py-3.5 sm:px-7">
                    <span class="block text-xs text-emerald-200/80">
                        Production units
                    </span>
                    <strong
                        class="mt-0.5 block text-lg font-semibold tabular-nums"
                    >
                        {{ farm.productionUnits.length }}
                    </strong>
                </div>
                <div class="border-l border-white/15 px-5 py-3.5 sm:px-7">
                    <span class="block text-xs text-emerald-200/80">
                        Active cycles
                    </span>
                    <strong
                        class="mt-0.5 block text-lg font-semibold tabular-nums"
                    >
                        {{ cycles.filter((c) => c.status === 'active').length }}
                    </strong>
                </div>
                <div
                    class="border-t border-white/15 px-5 py-3.5 sm:border-t-0 sm:border-l sm:px-7"
                >
                    <span class="block text-xs text-emerald-200/80">
                        {{
                            farm.domain === 'crop'
                                ? 'Open tasks'
                                : 'Live stock count'
                        }}
                    </span>
                    <strong
                        class="mt-0.5 block text-lg font-semibold tabular-nums"
                    >
                        {{
                            farm.domain === 'crop'
                                ? openTasks.length
                                : totalLiveStock
                        }}
                    </strong>
                </div>
                <div
                    class="border-t border-l border-white/15 px-5 py-3.5 sm:border-t-0 sm:px-7"
                >
                    <span class="block text-xs text-emerald-200/80">
                        {{
                            financeSummary ? 'Net farm margin' : 'Health events'
                        }}
                    </span>
                    <strong
                        class="mt-0.5 block text-lg font-semibold tabular-nums"
                    >
                        {{
                            financeSummary
                                ? formatCurrency(
                                      financeSummary.netMargin,
                                      currency,
                                  )
                                : healthEvents.length
                        }}
                    </strong>
                </div>
            </template>
        </PageHero>

        <!-- Sub-navigation Tabs -->
        <div
            class="flex flex-col gap-3 border-b pb-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between"
        >
            <div
                class="no-scrollbar -mx-4 flex min-w-0 flex-nowrap gap-1.5 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:overflow-visible sm:px-0 sm:pb-0"
            >
                <button
                    v-for="tab in [
                        { key: 'overview', label: 'Units & Overview' },
                        { key: 'cycles', label: `Cycles (${cycles.length})` },
                        {
                            key: 'logs',
                            label: `Daily Logs (${recentLogs.length})`,
                        },
                        {
                            key: 'health',
                            label: `Health (${healthEvents.length})`,
                        },
                        {
                            key: 'stock',
                            label: `Stock (${inventoryItems.length})`,
                        },
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
            <div v-if="permissions.canManageFarmOperations" class="flex gap-2">
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    @click="addUnitOpen = true"
                >
                    <Plus class="mr-1 size-3.5" />
                    Add unit
                </Button>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    :disabled="
                        farm.productionUnits.length === 0 ||
                        commodities.length === 0
                    "
                    @click="addCycleOpen = true"
                >
                    <Plus class="mr-1 size-3.5" />
                    Start cycle
                </Button>
            </div>
        </div>

        <!-- Tab: Overview & Units -->
        <section v-show="activeTab === 'overview'" class="space-y-6">
            <div
                v-if="financeSummary"
                class="grid grid-cols-2 gap-4 xl:grid-cols-4"
            >
                <MetricCard
                    label="Total spent"
                    :value="formatCurrency(financeSummary.spent, currency)"
                    tone="amber"
                />
                <MetricCard
                    label="Net sales"
                    :value="formatCurrency(financeSummary.netSales, currency)"
                    tone="emerald"
                />
                <MetricCard
                    label="Net margin"
                    :value="formatCurrency(financeSummary.netMargin, currency)"
                />
                <MetricCard
                    label="Harvest records"
                    :value="financeSummary.harvestCount"
                />
            </div>

            <div class="space-y-3">
                <h2 class="text-base font-semibold">
                    Production units (Pens, Ponds & Fields)
                </h2>
                <div
                    v-if="farm.productionUnits.length > 0"
                    class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
                >
                    <article
                        v-for="unit in farm.productionUnits"
                        :key="unit.id"
                        class="flex flex-col justify-between rounded-2xl border bg-card p-5"
                    >
                        <div class="space-y-2">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <h3 class="font-semibold">
                                        {{ unit.name }}
                                    </h3>
                                    <p class="text-xs text-muted-foreground">
                                        {{ unit.unitTypeLabel }}
                                        <span v-if="unit.size">
                                            · {{ unit.size }}
                                            {{ unit.sizeUnit }}
                                        </span>
                                    </p>
                                </div>
                                <Badge variant="outline" class="capitalize">
                                    {{ unit.status.replace('_', ' ') }}
                                </Badge>
                            </div>
                            <p
                                v-if="
                                    unit.capacity ||
                                    unit.waterSource ||
                                    unit.housingSystem
                                "
                                class="text-xs text-muted-foreground"
                            >
                                {{
                                    [
                                        unit.capacity
                                            ? `Capacity: ${unit.capacity}`
                                            : null,
                                        unit.waterSource
                                            ? `Water: ${unit.waterSource}`
                                            : null,
                                        unit.housingSystem
                                            ? `Housing: ${unit.housingSystem}`
                                            : null,
                                    ]
                                        .filter(Boolean)
                                        .join(' · ')
                                }}
                            </p>
                        </div>
                        <div
                            v-if="permissions.canManageFarmOperations"
                            class="mt-4 flex justify-end border-t pt-3"
                        >
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                @click="editingUnit = unit"
                            >
                                <Edit3 class="mr-1 size-3.5" />
                                Edit unit
                            </Button>
                        </div>
                    </article>
                </div>
                <EmptyState
                    v-else
                    title="No production units yet"
                    description="Add houses, pens, ponds, or fields to assign cycles and record daily operations."
                    :icon="MapPin"
                />
            </div>
        </section>

        <!-- Tab: Cycles -->
        <section v-show="activeTab === 'cycles'" class="space-y-4">
            <div v-if="cycles.length > 0" class="grid gap-4 md:grid-cols-2">
                <article
                    v-for="cycle in cycles"
                    :key="cycle.id"
                    class="flex flex-col justify-between rounded-2xl border bg-card p-5 shadow-xs"
                >
                    <div class="space-y-3">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <h3 class="text-base font-semibold">
                                    {{ cycle.name }}
                                </h3>
                                <p class="text-xs text-muted-foreground">
                                    {{
                                        cycle.enterpriseSubtypeLabel ??
                                        farm.farmTypeLabel
                                    }}
                                    <span v-if="cycle.breedOrVariety">
                                        · {{ cycle.breedOrVariety }}
                                    </span>
                                    · Day {{ cycle.telemetry.ageDays }}
                                </p>
                            </div>
                            <Badge variant="secondary">
                                {{ cycle.statusLabel }}
                            </Badge>
                        </div>

                        <div
                            class="grid grid-cols-3 gap-2 rounded-xl bg-muted/40 p-3 text-xs"
                        >
                            <div>
                                <span class="block text-muted-foreground">
                                    {{
                                        farm.domain === 'crop'
                                            ? 'Stage'
                                            : 'Live stock'
                                    }}
                                </span>
                                <strong class="font-semibold">
                                    {{
                                        farm.domain === 'crop'
                                            ? (cycle.currentGrowthStageLabel ??
                                              'Planned')
                                            : (cycle.currentStockCount ?? '—')
                                    }}
                                </strong>
                            </div>
                            <div>
                                <span class="block text-muted-foreground">
                                    Feed / FCR
                                </span>
                                <strong class="font-semibold tabular-nums">
                                    {{ cycle.telemetry.feeding.totalFeedKg }} kg
                                    <span v-if="cycle.telemetry.feeding.fcr">
                                        ({{ cycle.telemetry.feeding.fcr }})
                                    </span>
                                </strong>
                            </div>
                            <div>
                                <span class="block text-muted-foreground">
                                    Progress
                                </span>
                                <strong class="font-semibold tabular-nums">
                                    {{ cycle.telemetry.progressPercent }}%
                                </strong>
                            </div>
                        </div>
                    </div>

                    <div
                        class="mt-4 flex items-center justify-between border-t pt-3"
                    >
                        <span class="text-xs text-muted-foreground">
                            {{ cycle.plannedStartOn }}
                            <span v-if="cycle.plannedEndOn">
                                → {{ cycle.plannedEndOn }}
                            </span>
                        </span>
                        <Link
                            v-if="teamSlug"
                            :href="showCycle([teamSlug, farm.id, cycle.id])"
                            class="inline-flex items-center gap-1 text-sm font-medium text-primary hover:underline"
                        >
                            Open command center
                            <ArrowRight class="size-3.5" />
                        </Link>
                    </div>
                </article>
            </div>
            <EmptyState
                v-else
                title="No production cycles yet"
                description="Start a batch or growing season to track daily feed, water quality, health, and profitability."
                :icon="CalendarDays"
            />
        </section>

        <!-- Tab: Daily Logs -->
        <section v-show="activeTab === 'logs'" class="space-y-3">
            <div
                v-if="recentLogs.length > 0"
                class="divide-y rounded-2xl border bg-card"
            >
                <div
                    v-for="log in recentLogs"
                    :key="log.id"
                    class="flex flex-wrap items-center justify-between gap-3 p-4 text-sm"
                >
                    <div>
                        <p class="font-medium">
                            {{ log.logDate }} ·
                            {{ log.productionCycleName ?? farm.name }}
                            <span v-if="log.productionUnitName">
                                ({{ log.productionUnitName }})
                            </span>
                        </p>
                        <p class="text-xs text-muted-foreground">
                            <span v-if="log.feedUsedKg">
                                Feed: {{ log.feedUsedKg }} kg ·
                            </span>
                            <span v-if="log.mortalityCount > 0">
                                Mortality: {{ log.mortalityCount }} ·
                            </span>
                            <span v-if="log.eggsCollected">
                                Eggs: {{ log.eggsCollected }} ·
                            </span>
                            <span v-if="log.growthStageLabel">
                                Stage: {{ log.growthStageLabel }} ·
                            </span>
                            {{ log.notes || 'Recorded' }}
                        </p>
                    </div>
                </div>
            </div>
            <EmptyState
                v-else
                title="No daily logs yet"
                description="Record daily poultry, fish, or crop observations to build live telemetry."
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
                    class="flex items-center justify-between gap-3 p-4 text-sm"
                >
                    <div>
                        <p class="font-medium">{{ ev.title }}</p>
                        <p class="text-xs text-muted-foreground">
                            Scheduled {{ ev.scheduledOn }}
                        </p>
                    </div>
                    <Badge
                        :variant="ev.isOverdue ? 'destructive' : 'secondary'"
                    >
                        {{ ev.isOverdue ? 'Overdue' : ev.statusLabel }}
                    </Badge>
                </div>
            </div>
            <EmptyState
                v-else
                title="No health events scheduled"
                description="Schedule vaccinations, treatments, or apply presets from the Health & Vaccines workspace."
                :icon="HeartPulse"
            />
        </section>

        <!-- Tab: Stock -->
        <section v-show="activeTab === 'stock'" class="space-y-3">
            <div
                v-if="inventoryItems.length > 0"
                class="divide-y rounded-2xl border bg-card"
            >
                <div
                    v-for="item in inventoryItems"
                    :key="item.id"
                    class="flex items-center justify-between gap-3 p-4 text-sm"
                >
                    <div>
                        <p class="font-medium">{{ item.name }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ item.category }}
                        </p>
                    </div>
                    <strong class="tabular-nums">
                        {{ item.currentQuantity }} {{ item.unit }}
                    </strong>
                </div>
            </div>
            <EmptyState
                v-else
                title="No stock linked to this farm"
                description="Add feed, seeds, or agrochemicals in Stock & Feed."
                :icon="Package"
            />
        </section>

        <!-- Edit Farm Drawer -->
        <SlideOverForm
            :open="editFarmOpen"
            title="Edit farm details"
            @update:open="editFarmOpen = $event"
        >
            <Form
                v-if="teamSlug"
                v-bind="updateFarm.form([teamSlug, farm.id])"
                class="grid gap-4"
                v-slot="{ errors, processing }"
                @success="editFarmOpen = false"
            >
                <div class="grid gap-1.5">
                    <Label for="edit-farm-name">Farm name</Label>
                    <Input
                        id="edit-farm-name"
                        name="name"
                        :default-value="farm.name"
                        required
                    />
                    <InputError :message="errors.name" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="edit-farm-type">Farm type</Label>
                    <select
                        id="edit-farm-type"
                        name="farm_type"
                        class="h-11 rounded-md border bg-background px-3 text-base md:h-9 md:text-sm"
                    >
                        <option
                            v-for="opt in options.farmTypes"
                            :key="opt.value"
                            :value="opt.value"
                            :selected="opt.value === farm.farmType"
                        >
                            {{ opt.label }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="edit-farm-status">Status</Label>
                    <select
                        id="edit-farm-status"
                        name="status"
                        class="h-11 rounded-md border bg-background px-3 text-base md:h-9 md:text-sm"
                    >
                        <option
                            value="active"
                            :selected="farm.status === 'active'"
                        >
                            Active
                        </option>
                        <option
                            value="inactive"
                            :selected="farm.status === 'inactive'"
                        >
                            Inactive
                        </option>
                        <option
                            value="archived"
                            :selected="farm.status === 'archived'"
                        >
                            Archived
                        </option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="edit-farm-location">Location</Label>
                    <Input
                        id="edit-farm-location"
                        name="location"
                        :default-value="farm.location ?? ''"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="edit-farm-contact">Contact name</Label>
                    <Input
                        id="edit-farm-contact"
                        name="contact_name"
                        :default-value="farm.contactName ?? ''"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="edit-farm-phone">Contact phone</Label>
                    <Input
                        id="edit-farm-phone"
                        name="contact_phone"
                        :default-value="farm.contactPhone ?? ''"
                    />
                </div>
                <Button
                    type="submit"
                    :disabled="processing"
                    class="min-h-11 w-full sm:min-h-9 sm:w-auto"
                    >Save changes</Button
                >
            </Form>
        </SlideOverForm>

        <!-- Add Unit Drawer -->
        <SlideOverForm
            :open="addUnitOpen"
            title="Add production unit"
            @update:open="addUnitOpen = $event"
        >
            <Form
                v-if="teamSlug"
                v-bind="storeUnit.form([teamSlug, farm.id])"
                class="grid gap-4"
                reset-on-success
                v-slot="{ errors, processing }"
                @success="addUnitOpen = false"
            >
                <input type="hidden" name="return_to" value="farm" />
                <div class="grid gap-1.5">
                    <Label for="new-unit-name">Unit name</Label>
                    <Input id="new-unit-name" name="name" required />
                    <InputError :message="errors.name" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="new-unit-type">Unit type</Label>
                    <select
                        id="new-unit-type"
                        name="unit_type"
                        class="h-11 rounded-md border bg-background px-3 text-base md:h-9 md:text-sm"
                    >
                        <option
                            v-for="opt in options.productionUnitTypes"
                            :key="opt.value"
                            :value="opt.value"
                        >
                            {{ opt.label }}
                        </option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-1.5">
                        <Label for="new-unit-size">Size</Label>
                        <Input
                            id="new-unit-size"
                            name="size"
                            type="number"
                            inputmode="decimal"
                            min="0"
                            step="0.01"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="new-unit-size-unit">Size unit</Label>
                        <Input
                            id="new-unit-size-unit"
                            name="size_unit"
                            placeholder="sqm / acres"
                        />
                    </div>
                </div>
                <div class="grid gap-1.5">
                    <Label for="new-unit-capacity">Capacity</Label>
                    <Input
                        id="new-unit-capacity"
                        name="capacity"
                        placeholder="e.g. 1000 birds"
                    />
                </div>
                <Button
                    type="submit"
                    :disabled="processing"
                    class="min-h-11 w-full sm:min-h-9 sm:w-auto"
                    >Create unit</Button
                >
            </Form>
        </SlideOverForm>

        <!-- Edit Unit Drawer -->
        <SlideOverForm
            :open="editingUnit !== null"
            :title="editingUnit ? `Edit ${editingUnit.name}` : 'Edit unit'"
            @update:open="if (!$event) editingUnit = null;"
        >
            <Form
                v-if="teamSlug && editingUnit"
                v-bind="updateUnit.form([teamSlug, farm.id, editingUnit.id])"
                class="grid gap-4"
                v-slot="{ errors, processing }"
                @success="editingUnit = null"
            >
                <div class="grid gap-1.5">
                    <Label for="edit-unit-name">Name</Label>
                    <Input
                        id="edit-unit-name"
                        name="name"
                        :default-value="editingUnit.name"
                        required
                    />
                    <InputError :message="errors.name" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="edit-unit-type">Unit type</Label>
                    <select
                        id="edit-unit-type"
                        name="unit_type"
                        class="h-11 rounded-md border bg-background px-3 text-base md:h-9 md:text-sm"
                    >
                        <option
                            v-for="opt in options.productionUnitTypes"
                            :key="opt.value"
                            :value="opt.value"
                            :selected="opt.value === editingUnit.unitType"
                        >
                            {{ opt.label }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="edit-unit-status">Status</Label>
                    <select
                        id="edit-unit-status"
                        name="status"
                        class="h-11 rounded-md border bg-background px-3 text-base md:h-9 md:text-sm"
                    >
                        <option
                            value="available"
                            :selected="editingUnit.status === 'available'"
                        >
                            Available
                        </option>
                        <option
                            value="in_use"
                            :selected="editingUnit.status === 'in_use'"
                        >
                            In Use
                        </option>
                        <option
                            value="resting"
                            :selected="editingUnit.status === 'resting'"
                        >
                            Resting
                        </option>
                        <option
                            value="maintenance"
                            :selected="editingUnit.status === 'maintenance'"
                        >
                            Maintenance
                        </option>
                        <option
                            value="retired"
                            :selected="editingUnit.status === 'retired'"
                        >
                            Retired
                        </option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-1.5">
                        <Label for="edit-unit-size">Size</Label>
                        <Input
                            id="edit-unit-size"
                            name="size"
                            type="number"
                            inputmode="decimal"
                            min="0"
                            step="0.01"
                            :default-value="editingUnit.size ?? ''"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="edit-unit-size-unit">Size unit</Label>
                        <Input
                            id="edit-unit-size-unit"
                            name="size_unit"
                            :default-value="editingUnit.sizeUnit ?? ''"
                        />
                    </div>
                </div>
                <div class="grid gap-1.5">
                    <Label for="edit-unit-capacity">Capacity</Label>
                    <Input
                        id="edit-unit-capacity"
                        name="capacity"
                        :default-value="editingUnit.capacity ?? ''"
                    />
                </div>
                <Button
                    type="submit"
                    :disabled="processing"
                    class="min-h-11 w-full sm:min-h-9 sm:w-auto"
                >
                    Save unit
                </Button>
            </Form>
        </SlideOverForm>

        <!-- Start Cycle Drawer -->
        <SlideOverForm
            :open="addCycleOpen"
            title="Start production cycle / batch"
            @update:open="addCycleOpen = $event"
        >
            <Form
                v-if="teamSlug"
                v-bind="storeCycle.form([teamSlug, farm.id])"
                class="grid gap-4"
                reset-on-success
                v-slot="{ errors, processing }"
                @success="addCycleOpen = false"
            >
                <input type="hidden" name="return_to" value="farm" />
                <input type="hidden" name="farm_type" :value="farm.farmType" />
                <div class="grid gap-1.5">
                    <Label for="fc-name">Cycle / batch name</Label>
                    <Input id="fc-name" name="name" required />
                    <InputError :message="errors.name" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-1.5">
                        <Label for="fc-start">Planned start</Label>
                        <Input
                            id="fc-start"
                            name="planned_start_on"
                            type="date"
                            required
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="fc-end">Planned end</Label>
                        <Input id="fc-end" name="planned_end_on" type="date" />
                    </div>
                </div>
                <div class="grid gap-1.5">
                    <Label for="fc-commodity">Primary commodity</Label>
                    <select
                        id="fc-commodity"
                        name="primary_commodity_id"
                        class="h-11 rounded-md border bg-background px-3 text-base md:h-9 md:text-sm"
                        required
                    >
                        <option
                            v-for="c in commodities"
                            :key="c.id"
                            :value="c.id"
                        >
                            {{ c.name }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="fc-units">Units</Label>
                    <select
                        id="fc-units"
                        name="production_unit_ids[]"
                        multiple
                        class="min-h-24 rounded-md border bg-background px-3 py-2 text-base md:text-sm"
                        required
                    >
                        <option
                            v-for="u in farm.productionUnits"
                            :key="u.id"
                            :value="u.id"
                            selected
                        >
                            {{ u.name }}
                        </option>
                    </select>
                </div>
                <div v-if="farm.domain !== 'crop'" class="grid gap-1.5">
                    <Label for="fc-stock">Initial stock count</Label>
                    <Input
                        id="fc-stock"
                        name="initial_stock_count"
                        type="number"
                        inputmode="numeric"
                        min="0"
                    />
                </div>
                <Button
                    type="submit"
                    :disabled="processing"
                    class="min-h-11 w-full sm:min-h-9 sm:w-auto"
                    >Create cycle</Button
                >
            </Form>
        </SlideOverForm>

        <!-- Quick Daily Log Drawer -->
        <SlideOverForm
            :open="quickLogOpen"
            title="Log daily operation"
            wide
            @update:open="quickLogOpen = $event"
        >
            <div v-if="cycles.length > 0 && teamSlug" class="space-y-4">
                <div class="grid gap-1.5">
                    <Label>Cycle / batch</Label>
                    <select
                        v-model="selectedLogCycleId"
                        class="h-11 rounded-md border bg-background px-3 text-base md:h-9 md:text-sm"
                    >
                        <option
                            v-for="c in cycles"
                            :key="c.id"
                            :value="String(c.id)"
                        >
                            {{ c.name }}
                        </option>
                    </select>
                </div>
                <template v-if="selectedLogCycle">
                    <PoultryDailyLogForm
                        v-if="farm.domain === 'poultry'"
                        :team-slug="teamSlug"
                        :farm-id="farm.id"
                        :cycle-id="selectedLogCycle.id"
                        :units="farm.productionUnits"
                        :inventory-items="inventoryItems"
                        @saved="quickLogOpen = false"
                    />
                    <FishDailyLogForm
                        v-else-if="farm.domain === 'aquaculture'"
                        :team-slug="teamSlug"
                        :farm-id="farm.id"
                        :cycle-id="selectedLogCycle.id"
                        :units="farm.productionUnits"
                        :inventory-items="inventoryItems"
                        @saved="quickLogOpen = false"
                    />
                    <CropDailyLogForm
                        v-else
                        :team-slug="teamSlug"
                        :farm-id="farm.id"
                        :cycle-id="selectedLogCycle.id"
                        :units="farm.productionUnits"
                        :inventory-items="inventoryItems"
                        :growth-stages="options.cropGrowthStages"
                        :pest-pressures="options.pestPressures"
                        :current-growth-stage="
                            selectedLogCycle.currentGrowthStage
                        "
                        @saved="quickLogOpen = false"
                    />
                </template>
            </div>
        </SlideOverForm>
    </div>
</template>
