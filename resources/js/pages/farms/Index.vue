<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    CalendarDays,
    ClipboardList,
    MapPin,
    Plus,
    PlusCircle,
    Sprout,
    Tractor,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import EmptyState from '@/components/farm/EmptyState.vue';
import FarmTypeBadge from '@/components/farm/FarmTypeBadge.vue';
import PageHero from '@/components/farm/PageHero.vue';
import SlideOverForm from '@/components/farm/SlideOverForm.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes';
import { store as storeCommodity } from '@/routes/commodities';
import {
    index as farmsIndex,
    show as farmsShow,
    store as storeFarm,
} from '@/routes/farms';
import {
    show as showCycle,
    store as storeProductionCycle,
} from '@/routes/farms/production-cycles';
import { store as storePlanChange } from '@/routes/farms/production-cycles/plan-changes';
import { store as storeProductionUnit } from '@/routes/farms/production-units';
import { index as onboardingIndex } from '@/routes/onboarding';
import type {
    Commodity,
    Farm,
    FarmInvestorAgreement,
    FarmOperationOptions,
    FarmOperationPermissions,
    FarmStats,
    ProductionPlanChange,
    Team,
} from '@/types';

type Props = {
    permissions: FarmOperationPermissions;
    stats: FarmStats;
    farms: Farm[];
    commodities: Commodity[];
    investorAgreements: FarmInvestorAgreement[];
    latestPlanChanges: ProductionPlanChange[];
    options: Pick<
        FarmOperationOptions,
        | 'farmTypes'
        | 'productionUnitTypes'
        | 'productionCycleStatuses'
        | 'commodityRoles'
        | 'planChangeTypes'
        | 'investorVisibilityStatuses'
    >;
};

const props = defineProps<Props>();
const page = usePage();
const activeForm = ref<
    'farm' | 'commodity' | 'unit' | 'cycle' | 'planChange' | null
>(null);

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
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
        ],
    }),
});

const currentTeam = computed(() => page.props.currentTeam as Team | null);
const currentTeamSlug = computed(() => currentTeam.value?.slug ?? '');
const investorMode = computed(
    () => page.props.currentTeamFeatures?.investorMode === true,
);

const typeFilter = ref('all');
const searchQuery = ref('');

const filteredFarms = computed(() =>
    props.farms.filter((farm) => {
        if (typeFilter.value !== 'all' && farm.farmType !== typeFilter.value) {
            return false;
        }

        if (
            searchQuery.value.trim() &&
            !farm.name
                .toLowerCase()
                .includes(searchQuery.value.trim().toLowerCase())
        ) {
            return false;
        }

        return true;
    }),
);

const commodityFarmType = ref(props.options.farmTypes[0]?.value ?? 'crop');
const farmType = ref(props.options.farmTypes[0]?.value ?? 'crop');
const unitType = ref(props.options.productionUnitTypes[0]?.value ?? 'field');
const unitFarmId = ref(props.farms[0]?.id ? String(props.farms[0].id) : '');
const cycleFarmId = ref(props.farms[0]?.id ? String(props.farms[0].id) : '');
const cycleStatus = ref('planned');
const primaryCommodityId = ref(
    props.commodities[0]?.id ? String(props.commodities[0].id) : '',
);
const secondaryCommodityId = ref('');
const planChangeType = ref(props.options.planChangeTypes[0]?.value ?? 'other');
const planChangeTarget = ref('');
const planChangeInvestorAgreementId = ref('');
const planChangeInvestorVisibilityStatus = ref('private');
const formKeys = ref({
    commodity: 0,
    farm: 0,
    unit: 0,
    cycle: 0,
    planChange: 0,
});

const selectedCycleFarm = computed(() =>
    props.farms.find((farm) => String(farm.id) === cycleFarmId.value),
);

const cycleTargets = computed(() =>
    props.farms.flatMap((farm) =>
        farm.productionCycles.map((cycle) => ({
            key: `${farm.id}:${cycle.id}`,
            farmId: farm.id,
            cycleId: cycle.id,
            label: `${farm.name} / ${cycle.name}`,
        })),
    ),
);

const selectedPlanChangeTarget = computed(() =>
    cycleTargets.value.find((target) => target.key === planChangeTarget.value),
);

const planChangeInvestorAgreements = computed(() => {
    const target = selectedPlanChangeTarget.value;

    if (!target) {
        return [];
    }

    return props.investorAgreements.filter(
        (agreement) =>
            agreement.farmId === target.farmId &&
            (agreement.productionCycleId === null ||
                agreement.productionCycleId === target.cycleId),
    );
});

const bumpFormKey = (key: keyof typeof formKeys.value) => {
    formKeys.value[key]++;
    activeForm.value = null;
};

watch(
    () => props.farms.map((farm) => farm.id),
    () => {
        const farmIds = props.farms.map((farm) => String(farm.id));
        const fallback = farmIds[0] ?? '';

        if (!farmIds.includes(unitFarmId.value)) {
            unitFarmId.value = fallback;
        }

        if (!farmIds.includes(cycleFarmId.value)) {
            cycleFarmId.value = fallback;
        }
    },
    { immediate: true },
);

watch(planChangeTarget, () => {
    planChangeInvestorAgreementId.value = '';
});

watch(
    () => props.commodities.map((commodity) => commodity.id),
    () => {
        const ids = props.commodities.map((c) => String(c.id));
        const fallback = ids[0] ?? '';

        if (!ids.includes(primaryCommodityId.value)) {
            primaryCommodityId.value = fallback;
        }

        if (
            secondaryCommodityId.value &&
            (!ids.includes(secondaryCommodityId.value) ||
                secondaryCommodityId.value === primaryCommodityId.value)
        ) {
            secondaryCommodityId.value = '';
        }
    },
    { immediate: true },
);
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Head title="Farms" />

        <PageHero
            eyebrow="Farm Directory & Structure"
            title="Farms and production"
            description="Manage poultry houses, crop fields, fish ponds, commodities, and active production cycles."
        >
            <template #actions>
                <Link
                    v-if="
                        permissions.canManageFarmOperations && currentTeamSlug
                    "
                    :href="onboardingIndex(currentTeamSlug)"
                    class="inline-flex h-10 items-center gap-2 rounded-lg bg-white px-4 text-sm font-medium text-[#1b4d3e] hover:bg-emerald-50"
                >
                    <PlusCircle class="size-4" />
                    Guided farm setup
                </Link>
                <Button
                    v-if="
                        permissions.canManageFarmOperations && currentTeamSlug
                    "
                    type="button"
                    variant="secondary"
                    @click="activeForm = 'farm'"
                >
                    <Plus class="mr-1.5 size-4" />
                    Quick add farm
                </Button>
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
                        Production units
                    </span>
                    <strong
                        class="mt-0.5 block text-lg font-semibold tabular-nums"
                    >
                        {{ stats.productionUnits }}
                    </strong>
                </div>
                <div
                    class="border-t border-white/15 px-5 py-3.5 sm:border-t-0 sm:border-l sm:px-7"
                >
                    <span class="block text-xs text-emerald-200/80">
                        Production cycles
                    </span>
                    <strong
                        class="mt-0.5 block text-lg font-semibold tabular-nums"
                    >
                        {{ stats.productionCycles }}
                    </strong>
                </div>
                <div
                    class="border-t border-l border-white/15 px-5 py-3.5 sm:border-t-0 sm:px-7"
                >
                    <span class="block text-xs text-emerald-200/80">
                        Commodities
                    </span>
                    <strong
                        class="mt-0.5 block text-lg font-semibold tabular-nums"
                    >
                        {{ stats.commodities }}
                    </strong>
                </div>
            </template>
        </PageHero>

        <!-- Action & Filter Bar -->
        <div
            class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between"
        >
            <div
                class="no-scrollbar -mx-4 flex min-w-0 flex-nowrap items-center gap-1.5 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:overflow-visible sm:px-0 sm:pb-0"
            >
                <button
                    v-for="tab in [
                        { value: 'all', label: 'All farms' },
                        { value: 'poultry', label: 'Poultry' },
                        { value: 'crop', label: 'Crop' },
                        { value: 'aquaculture', label: 'Fish / Aquaculture' },
                        { value: 'mixed', label: 'Mixed' },
                    ]"
                    :key="tab.value"
                    type="button"
                    class="min-h-9 shrink-0 rounded-full border px-3.5 py-1.5 text-xs font-medium transition-colors"
                    :class="
                        typeFilter === tab.value
                            ? 'border-primary bg-primary text-primary-foreground'
                            : 'bg-card hover:bg-accent'
                    "
                    @click="typeFilter = tab.value"
                >
                    {{ tab.label }}
                </button>
            </div>
            <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto">
                <Input
                    v-model="searchQuery"
                    placeholder="Search farms..."
                    class="w-full sm:w-48"
                />
                <template
                    v-if="
                        permissions.canManageFarmOperations && currentTeamSlug
                    "
                >
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="activeForm = 'unit'"
                    >
                        <MapPin class="mr-1.5 size-3.5 text-primary" />
                        Add unit
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="activeForm = 'cycle'"
                    >
                        <CalendarDays class="mr-1.5 size-3.5 text-primary" />
                        Add cycle
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="activeForm = 'commodity'"
                    >
                        <Sprout class="mr-1.5 size-3.5 text-primary" />
                        Commodity
                    </Button>
                </template>
            </div>
        </div>

        <!-- Farm Cards -->
        <section v-if="filteredFarms.length > 0" class="space-y-4">
            <article
                v-for="farm in filteredFarms"
                :key="farm.id"
                class="overflow-hidden rounded-2xl border bg-card shadow-xs"
            >
                <div
                    class="flex flex-col justify-between gap-4 px-5 py-5 sm:px-6 md:flex-row md:items-center"
                >
                    <div class="flex min-w-0 items-start gap-4">
                        <div
                            class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-accent text-accent-foreground"
                        >
                            <Tractor class="size-5" />
                        </div>
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <Link
                                    v-if="currentTeamSlug"
                                    :href="
                                        farmsShow([currentTeamSlug, farm.id])
                                    "
                                    class="text-lg font-semibold hover:text-primary hover:underline"
                                >
                                    {{ farm.name }}
                                </Link>
                                <FarmTypeBadge
                                    :farm-type="farm.farmType"
                                    :label="farm.farmTypeLabel"
                                />
                                <Badge variant="secondary" class="capitalize">
                                    {{ farm.status }}
                                </Badge>
                            </div>
                            <p
                                v-if="farm.location"
                                class="mt-1 text-sm text-muted-foreground"
                            >
                                {{ farm.location }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-6 text-sm">
                        <div>
                            <span class="block text-xs text-muted-foreground">
                                Units
                            </span>
                            <strong class="font-semibold tabular-nums">
                                {{ farm.productionUnitsCount }}
                            </strong>
                        </div>
                        <div>
                            <span class="block text-xs text-muted-foreground">
                                Cycles
                            </span>
                            <strong class="font-semibold tabular-nums">
                                {{ farm.productionCyclesCount }}
                            </strong>
                        </div>
                        <Link
                            v-if="currentTeamSlug"
                            :href="farmsShow([currentTeamSlug, farm.id])"
                            class="inline-flex items-center gap-1.5 rounded-lg border bg-background px-3 py-1.5 text-xs font-medium hover:bg-accent"
                        >
                            Command center
                            <ArrowRight class="size-3.5" />
                        </Link>
                    </div>
                </div>

                <div class="grid border-t bg-muted/25 md:grid-cols-2">
                    <div class="px-5 py-4 sm:px-6">
                        <p
                            class="mb-2 flex items-center gap-1.5 text-xs font-semibold text-muted-foreground uppercase"
                        >
                            <MapPin class="size-3.5 text-primary" />
                            Production units
                        </p>
                        <ul
                            v-if="farm.productionUnits.length"
                            class="divide-y text-sm"
                        >
                            <li
                                v-for="unit in farm.productionUnits"
                                :key="unit.id"
                                class="flex items-center justify-between py-1.5"
                            >
                                <span>{{ unit.name }}</span>
                                <span class="text-xs text-muted-foreground">
                                    {{ unit.unitTypeLabel }}
                                    <span v-if="unit.size">
                                        · {{ unit.size }} {{ unit.sizeUnit }}
                                    </span>
                                </span>
                            </li>
                        </ul>
                        <p v-else class="text-xs text-muted-foreground">
                            No production units added yet.
                        </p>
                    </div>
                    <div
                        class="border-t px-5 py-4 sm:px-6 md:border-t-0 md:border-l"
                    >
                        <p
                            class="mb-2 flex items-center gap-1.5 text-xs font-semibold text-muted-foreground uppercase"
                        >
                            <CalendarDays class="size-3.5 text-primary" />
                            Production cycles
                        </p>
                        <ul
                            v-if="farm.productionCycles.length"
                            class="space-y-2.5"
                        >
                            <li
                                v-for="cycle in farm.productionCycles"
                                :key="cycle.id"
                                class="rounded-lg border bg-card px-3 py-2 text-sm"
                            >
                                <div
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
                                        class="font-medium hover:text-primary hover:underline"
                                    >
                                        {{ cycle.name }}
                                    </Link>
                                    <span class="text-xs text-muted-foreground">
                                        {{ cycle.statusLabel }} · v{{
                                            cycle.planVersion
                                        }}
                                    </span>
                                </div>
                                <div
                                    v-if="cycle.commodities.length"
                                    class="mt-1.5 flex flex-wrap gap-1"
                                >
                                    <span
                                        v-for="commodity in cycle.commodities"
                                        :key="`${cycle.id}-${commodity.id}`"
                                        class="rounded bg-muted px-1.5 py-0.5 text-[11px]"
                                    >
                                        {{ commodity.name }} ({{
                                            commodity.roleLabel
                                        }})
                                    </span>
                                </div>
                            </li>
                        </ul>
                        <p v-else class="text-xs text-muted-foreground">
                            No production cycles planned yet.
                        </p>
                    </div>
                </div>
            </article>
        </section>

        <EmptyState
            v-else
            title="Start with your first farm"
            description="Launch our guided onboarding wizard for Poultry, Crop, or Fish farming—or add a farm manually."
            :icon="Sprout"
        >
            <template #action>
                <Link
                    v-if="
                        permissions.canManageFarmOperations && currentTeamSlug
                    "
                    :href="onboardingIndex(currentTeamSlug)"
                    class="inline-flex h-10 items-center gap-2 rounded-lg bg-primary px-4 text-sm font-medium text-primary-foreground"
                >
                    <PlusCircle class="size-4" />
                    Guided farm setup
                </Link>
            </template>
        </EmptyState>

        <!-- Plan Changes Section -->
        <section class="rounded-2xl border bg-card p-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <ClipboardList class="size-4 text-primary" />
                    <Heading
                        variant="small"
                        title="Plan changes"
                        description="Audit trail of cycle adjustments, reasons, and operational impact"
                    />
                </div>
                <Button
                    v-if="
                        permissions.canManageFarmOperations &&
                        currentTeamSlug &&
                        cycleTargets.length > 0
                    "
                    type="button"
                    size="sm"
                    variant="outline"
                    @click="activeForm = 'planChange'"
                >
                    Record plan change
                </Button>
            </div>

            <div
                v-if="latestPlanChanges.length > 0"
                class="mt-4 grid gap-3 md:grid-cols-2"
            >
                <div
                    v-for="change in latestPlanChanges"
                    :key="change.id"
                    class="rounded-xl border p-3.5 text-sm"
                >
                    <div class="flex items-center justify-between gap-2">
                        <p class="font-medium">
                            {{ change.farmName }} / {{ change.cycleName }}
                        </p>
                        <Badge variant="secondary">
                            {{ change.changeTypeLabel }}
                        </Badge>
                    </div>
                    <p class="mt-1.5 text-xs text-muted-foreground">
                        {{ change.reason }}
                    </p>
                    <p class="mt-1 text-xs">{{ change.impact }}</p>
                </div>
            </div>
            <p v-else class="mt-3 text-sm text-muted-foreground">
                No plan changes recorded yet.
            </p>
        </section>

        <!-- Slide-Over Drawers -->
        <SlideOverForm
            :open="activeForm === 'farm'"
            title="Create farm"
            description="Add a farm to your workspace"
            @update:open="if (!$event) activeForm = null;"
        >
            <Form
                v-if="currentTeamSlug"
                :key="formKeys.farm"
                v-bind="storeFarm.form(currentTeamSlug)"
                class="grid gap-4"
                v-slot="{ errors, processing }"
                @success="bumpFormKey('farm')"
            >
                <div class="grid gap-1.5">
                    <Label for="farm-name">Name</Label>
                    <Input id="farm-name" name="name" required />
                    <InputError :message="errors.name" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="farm-type">Farm type</Label>
                    <Select v-model="farmType" name="farm_type">
                        <SelectTrigger id="farm-type" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in options.farmTypes"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="errors.farm_type" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="farm-location">Location</Label>
                    <Input id="farm-location" name="location" />
                    <InputError :message="errors.location" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="farm-contact-name">Contact name</Label>
                    <Input id="farm-contact-name" name="contact_name" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="farm-contact-phone">Contact phone</Label>
                    <Input id="farm-contact-phone" name="contact_phone" />
                </div>
                <Button type="submit" :disabled="processing"
                    >Create farm</Button
                >
            </Form>
        </SlideOverForm>

        <SlideOverForm
            :open="activeForm === 'commodity'"
            title="Create commodity"
            description="Crop, poultry, fish, or livestock commodity"
            @update:open="if (!$event) activeForm = null;"
        >
            <Form
                v-if="currentTeamSlug"
                :key="formKeys.commodity"
                v-bind="storeCommodity.form(currentTeamSlug)"
                class="grid gap-4"
                v-slot="{ errors, processing }"
                @success="bumpFormKey('commodity')"
            >
                <div class="grid gap-1.5">
                    <Label for="commodity-name">Name</Label>
                    <Input id="commodity-name" name="name" required />
                    <InputError :message="errors.name" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="commodity-type">Farm type</Label>
                    <Select v-model="commodityFarmType" name="farm_type">
                        <SelectTrigger id="commodity-type" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in options.farmTypes"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="measurement-unit">Unit</Label>
                    <Input
                        id="measurement-unit"
                        name="measurement_unit"
                        placeholder="kg / birds / crates"
                    />
                </div>
                <Button type="submit" :disabled="processing">
                    Create commodity
                </Button>
            </Form>
        </SlideOverForm>

        <SlideOverForm
            :open="activeForm === 'unit'"
            title="Create production unit"
            description="House, pen, pond, field, or plot"
            @update:open="if (!$event) activeForm = null;"
        >
            <Form
                v-if="farms.length > 0 && currentTeamSlug"
                :key="formKeys.unit"
                v-bind="
                    storeProductionUnit.form([
                        currentTeamSlug,
                        Number(unitFarmId),
                    ])
                "
                class="grid gap-4"
                v-slot="{ errors, processing }"
                @success="bumpFormKey('unit')"
            >
                <div class="grid gap-1.5">
                    <Label for="unit-farm">Farm</Label>
                    <select
                        id="unit-farm"
                        v-model="unitFarmId"
                        class="h-11 rounded-md border bg-background px-3 text-base md:h-9 md:text-sm"
                    >
                        <option
                            v-for="farm in farms"
                            :key="farm.id"
                            :value="String(farm.id)"
                        >
                            {{ farm.name }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="unit-type">Unit type</Label>
                    <Select v-model="unitType" name="unit_type">
                        <SelectTrigger id="unit-type" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in options.productionUnitTypes"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="unit-name">Name</Label>
                    <Input id="unit-name" name="name" required />
                    <InputError :message="errors.name" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-1.5">
                        <Label for="unit-size">Size</Label>
                        <Input
                            id="unit-size"
                            name="size"
                            type="number"
                            inputmode="decimal"
                            min="0"
                            step="0.01"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="unit-size-unit">Size unit</Label>
                        <Input id="unit-size-unit" name="size_unit" />
                    </div>
                </div>
                <Button
                    type="submit"
                    :disabled="processing"
                    class="min-h-11 w-full sm:min-h-9 sm:w-auto"
                    >Create unit</Button
                >
            </Form>
            <p v-else class="text-sm text-muted-foreground">
                Add a farm first.
            </p>
        </SlideOverForm>

        <SlideOverForm
            :open="activeForm === 'cycle'"
            title="Create production cycle"
            description="Batch or season plan with assigned units and commodities"
            wide
            @update:open="if (!$event) activeForm = null;"
        >
            <Form
                v-if="
                    farms.length > 0 &&
                    commodities.length > 0 &&
                    currentTeamSlug
                "
                :key="formKeys.cycle"
                v-bind="
                    storeProductionCycle.form([
                        currentTeamSlug,
                        Number(cycleFarmId),
                    ])
                "
                class="grid gap-4 md:grid-cols-2"
                v-slot="{ errors, processing }"
                @success="bumpFormKey('cycle')"
            >
                <div class="grid gap-1.5">
                    <Label for="cycle-farm">Farm</Label>
                    <select
                        id="cycle-farm"
                        v-model="cycleFarmId"
                        class="h-11 rounded-md border bg-background px-3 text-base md:h-9 md:text-sm"
                    >
                        <option
                            v-for="farm in farms"
                            :key="farm.id"
                            :value="String(farm.id)"
                        >
                            {{ farm.name }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="cycle-status">Status</Label>
                    <Select v-model="cycleStatus" name="status">
                        <SelectTrigger id="cycle-status" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in options.productionCycleStatuses"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-1.5 md:col-span-2">
                    <Label for="cycle-name">Name</Label>
                    <Input id="cycle-name" name="name" required />
                    <InputError :message="errors.name" />
                </div>
                <input
                    type="hidden"
                    name="farm_type"
                    :value="selectedCycleFarm?.farmType ?? farmType"
                />
                <div class="grid gap-1.5">
                    <Label for="planned-start">Planned start</Label>
                    <Input
                        id="planned-start"
                        name="planned_start_on"
                        type="date"
                        required
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="planned-end">Planned end</Label>
                    <Input id="planned-end" name="planned_end_on" type="date" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="primary-commodity">Primary commodity</Label>
                    <select
                        id="primary-commodity"
                        v-model="primaryCommodityId"
                        name="primary_commodity_id"
                        class="h-11 rounded-md border bg-background px-3 text-base md:h-9 md:text-sm"
                        required
                    >
                        <option
                            v-for="commodity in commodities"
                            :key="commodity.id"
                            :value="String(commodity.id)"
                        >
                            {{ commodity.name }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="secondary-commodity">Intercrop</Label>
                    <select
                        id="secondary-commodity"
                        v-model="secondaryCommodityId"
                        name="secondary_commodity_id"
                        class="h-11 rounded-md border bg-background px-3 text-base md:h-9 md:text-sm"
                    >
                        <option value="">None</option>
                        <option
                            v-for="commodity in commodities"
                            :key="commodity.id"
                            :value="String(commodity.id)"
                        >
                            {{ commodity.name }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-1.5 md:col-span-2">
                    <Label for="cycle-units">Production units</Label>
                    <select
                        id="cycle-units"
                        name="production_unit_ids[]"
                        multiple
                        class="min-h-24 rounded-md border bg-background px-3 py-2 text-base md:text-sm"
                        required
                    >
                        <option
                            v-for="unit in selectedCycleFarm?.productionUnits ??
                            []"
                            :key="unit.id"
                            :value="unit.id"
                        >
                            {{ unit.name }}
                        </option>
                    </select>
                    <InputError :message="errors.production_unit_ids" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="cycle-stock"
                        >Initial stock count (optional)</Label
                    >
                    <Input
                        id="cycle-stock"
                        name="initial_stock_count"
                        type="number"
                        inputmode="numeric"
                        min="0"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="expected-output">Expected output</Label>
                    <Input
                        id="expected-output"
                        name="expected_output_quantity"
                        type="number"
                        inputmode="decimal"
                        min="0"
                        step="0.01"
                    />
                </div>
                <div class="md:col-span-2">
                    <Button
                        type="submit"
                        class="min-h-11 w-full sm:min-h-9 sm:w-auto"
                        :disabled="
                            processing ||
                            (selectedCycleFarm?.productionUnits.length ?? 0) ===
                                0
                        "
                    >
                        Create cycle
                    </Button>
                </div>
            </Form>
            <p v-else class="text-sm text-muted-foreground">
                Add a farm and commodity first.
            </p>
        </SlideOverForm>

        <SlideOverForm
            :open="activeForm === 'planChange'"
            title="Record a plan change"
            @update:open="if (!$event) activeForm = null;"
        >
            <Form
                v-if="currentTeamSlug && cycleTargets.length > 0"
                :key="formKeys.planChange"
                v-bind="
                    storePlanChange.form([
                        currentTeamSlug,
                        selectedPlanChangeTarget?.farmId ?? 0,
                        selectedPlanChangeTarget?.cycleId ?? 0,
                    ])
                "
                class="grid gap-4"
                v-slot="{ errors, processing }"
                @success="bumpFormKey('planChange')"
            >
                <div class="grid gap-1.5">
                    <Label for="plan-change-target">Cycle</Label>
                    <select
                        id="plan-change-target"
                        v-model="planChangeTarget"
                        class="h-11 rounded-md border bg-background px-3 text-base md:h-9 md:text-sm"
                        required
                    >
                        <option value="">Select cycle</option>
                        <option
                            v-for="target in cycleTargets"
                            :key="target.key"
                            :value="target.key"
                        >
                            {{ target.label }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="plan-change-type">Type</Label>
                    <Select v-model="planChangeType" name="change_type">
                        <SelectTrigger id="plan-change-type" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in options.planChangeTypes"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="plan-reason">Reason</Label>
                    <Input id="plan-reason" name="reason" required />
                    <InputError :message="errors.reason" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="plan-impact">Impact</Label>
                    <Input id="plan-impact" name="impact" required />
                    <InputError :message="errors.impact" />
                </div>
                <template v-if="investorMode">
                    <div class="grid gap-1.5">
                        <Label for="investor-summary"
                            >Investor-safe summary</Label
                        >
                        <Input
                            id="investor-summary"
                            name="investor_safe_summary"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="plan-investor-agreement"
                            >Investor agreement</Label
                        >
                        <select
                            id="plan-investor-agreement"
                            v-model="planChangeInvestorAgreementId"
                            name="investor_agreement_id"
                            class="h-11 rounded-md border bg-background px-3 text-base md:h-9 md:text-sm"
                        >
                            <option value="">Not linked</option>
                            <option
                                v-for="agreement in planChangeInvestorAgreements"
                                :key="agreement.id"
                                :value="String(agreement.id)"
                            >
                                {{ agreement.title }} /
                                {{ agreement.investorName }}
                            </option>
                        </select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="plan-investor-visibility"
                            >Investor visibility</Label
                        >
                        <select
                            id="plan-investor-visibility"
                            v-model="planChangeInvestorVisibilityStatus"
                            name="investor_visibility_status"
                            class="h-11 rounded-md border bg-background px-3 text-base md:h-9 md:text-sm"
                        >
                            <option
                                v-for="option in options.investorVisibilityStatuses"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </option>
                        </select>
                    </div>
                </template>
                <Button
                    type="submit"
                    :disabled="processing || !selectedPlanChangeTarget"
                >
                    Record change
                </Button>
            </Form>
        </SlideOverForm>
    </div>
</template>
