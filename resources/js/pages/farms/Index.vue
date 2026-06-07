<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import {
    CalendarDays,
    ClipboardList,
    MapPin,
    Plus,
    Sprout,
    Tractor,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
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
import { index as farmsIndex, store as storeFarm } from '@/routes/farms';
import { store as storeProductionCycle } from '@/routes/farms/production-cycles';
import { store as storePlanChange } from '@/routes/farms/production-cycles/plan-changes';
import { store as storeProductionUnit } from '@/routes/farms/production-units';
import type {
    Commodity,
    Farm,
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
    latestPlanChanges: ProductionPlanChange[];
    options: Pick<
        FarmOperationOptions,
        | 'farmTypes'
        | 'productionUnitTypes'
        | 'productionCycleStatuses'
        | 'commodityRoles'
        | 'planChangeTypes'
    >;
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

const bumpFormKey = (key: keyof typeof formKeys.value) => {
    formKeys.value[key]++;
};

const syncFarmSelections = () => {
    const farmIds = props.farms.map((farm) => String(farm.id));
    const fallbackFarmId = farmIds[0] ?? '';

    if (!farmIds.includes(unitFarmId.value)) {
        unitFarmId.value = fallbackFarmId;
    }

    if (!farmIds.includes(cycleFarmId.value)) {
        cycleFarmId.value = fallbackFarmId;
    }
};

const syncCommoditySelections = () => {
    const commodityIds = props.commodities.map((commodity) =>
        String(commodity.id),
    );
    const fallbackCommodityId = commodityIds[0] ?? '';

    if (!commodityIds.includes(primaryCommodityId.value)) {
        primaryCommodityId.value = fallbackCommodityId;
    }

    if (
        secondaryCommodityId.value &&
        (!commodityIds.includes(secondaryCommodityId.value) ||
            secondaryCommodityId.value === primaryCommodityId.value)
    ) {
        secondaryCommodityId.value = '';
    }
};

watch(() => props.farms.map((farm) => farm.id), syncFarmSelections, {
    immediate: true,
});
watch(
    () => props.commodities.map((commodity) => commodity.id),
    syncCommoditySelections,
    { immediate: true },
);
</script>

<template>
    <Head title="Farms" />

    <div class="flex flex-1 flex-col gap-8 p-4 md:p-6">
        <Heading
            title="Farm operations"
            description="Farms, units, cycles, commodities, and plan changes"
        />

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-lg border p-4">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm text-muted-foreground">Farms</span>
                    <Tractor class="size-4 text-muted-foreground" />
                </div>
                <p class="mt-3 text-2xl font-semibold">{{ stats.farms }}</p>
            </div>
            <div class="rounded-lg border p-4">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm text-muted-foreground">Units</span>
                    <MapPin class="size-4 text-muted-foreground" />
                </div>
                <p class="mt-3 text-2xl font-semibold">
                    {{ stats.productionUnits }}
                </p>
            </div>
            <div class="rounded-lg border p-4">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm text-muted-foreground">Cycles</span>
                    <CalendarDays class="size-4 text-muted-foreground" />
                </div>
                <p class="mt-3 text-2xl font-semibold">
                    {{ stats.productionCycles }}
                </p>
            </div>
            <div class="rounded-lg border p-4">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm text-muted-foreground">
                        Commodities
                    </span>
                    <Sprout class="size-4 text-muted-foreground" />
                </div>
                <p class="mt-3 text-2xl font-semibold">
                    {{ stats.commodities }}
                </p>
            </div>
        </div>

        <div
            v-if="permissions.canManageFarmOperations && currentTeamSlug"
            class="grid gap-6 xl:grid-cols-2"
        >
            <section class="space-y-4 rounded-lg border p-4">
                <div class="flex items-center gap-2">
                    <Plus class="size-4" />
                    <Heading
                        variant="small"
                        title="Create farm"
                        description="Tenant-scoped farm record"
                    />
                </div>

                <Form
                    :key="formKeys.farm"
                    v-bind="storeFarm.form(currentTeamSlug)"
                    class="grid gap-4 md:grid-cols-2"
                    v-slot="{ errors, processing }"
                    @success="bumpFormKey('farm')"
                >
                    <div class="grid gap-2 md:col-span-2">
                        <Label for="farm-name">Name</Label>
                        <Input id="farm-name" name="name" required />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-2">
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
                    <div class="grid gap-2">
                        <Label for="farm-location">Location</Label>
                        <Input id="farm-location" name="location" />
                        <InputError :message="errors.location" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="farm-contact-name">Contact name</Label>
                        <Input id="farm-contact-name" name="contact_name" />
                        <InputError :message="errors.contact_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="farm-contact-phone">Contact phone</Label>
                        <Input id="farm-contact-phone" name="contact_phone" />
                        <InputError :message="errors.contact_phone" />
                    </div>
                    <div class="md:col-span-2">
                        <Button type="submit" :disabled="processing">
                            Create farm
                        </Button>
                    </div>
                </Form>
            </section>

            <section class="space-y-4 rounded-lg border p-4">
                <div class="flex items-center gap-2">
                    <Sprout class="size-4" />
                    <Heading
                        variant="small"
                        title="Create commodity"
                        description="Crop, livestock, aquaculture, or mixed commodity"
                    />
                </div>

                <Form
                    :key="formKeys.commodity"
                    v-bind="storeCommodity.form(currentTeamSlug)"
                    class="grid gap-4 md:grid-cols-2"
                    v-slot="{ errors, processing }"
                    @success="bumpFormKey('commodity')"
                >
                    <div class="grid gap-2 md:col-span-2">
                        <Label for="commodity-name">Name</Label>
                        <Input id="commodity-name" name="name" required />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-2">
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
                        <InputError :message="errors.farm_type" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="measurement-unit">Unit</Label>
                        <Input
                            id="measurement-unit"
                            name="measurement_unit"
                            placeholder="kg"
                        />
                        <InputError :message="errors.measurement_unit" />
                    </div>
                    <div class="md:col-span-2">
                        <Button type="submit" :disabled="processing">
                            Create commodity
                        </Button>
                    </div>
                </Form>
            </section>

            <section class="space-y-4 rounded-lg border p-4">
                <div class="flex items-center gap-2">
                    <MapPin class="size-4" />
                    <Heading
                        variant="small"
                        title="Create production unit"
                        description="Field, plot, pen, pond, house, or other unit"
                    />
                </div>

                <Form
                    v-if="farms.length > 0"
                    :key="formKeys.unit"
                    v-bind="
                        storeProductionUnit.form([
                            currentTeamSlug,
                            Number(unitFarmId),
                        ])
                    "
                    class="grid gap-4 md:grid-cols-2"
                    v-slot="{ errors, processing }"
                    @success="bumpFormKey('unit')"
                >
                    <div class="grid gap-2">
                        <Label for="unit-farm">Farm</Label>
                        <select
                            id="unit-farm"
                            v-model="unitFarmId"
                            class="h-9 rounded-md border bg-background px-3 text-sm"
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
                    <div class="grid gap-2">
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
                        <InputError :message="errors.unit_type" />
                    </div>
                    <div class="grid gap-2 md:col-span-2">
                        <Label for="unit-name">Name</Label>
                        <Input id="unit-name" name="name" required />
                        <InputError :message="errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="unit-size">Size</Label>
                        <Input
                            id="unit-size"
                            name="size"
                            type="number"
                            min="0"
                            step="0.01"
                        />
                        <InputError :message="errors.size" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="unit-size-unit">Size unit</Label>
                        <Input id="unit-size-unit" name="size_unit" />
                        <InputError :message="errors.size_unit" />
                    </div>
                    <div class="md:col-span-2">
                        <Button type="submit" :disabled="processing">
                            Create unit
                        </Button>
                    </div>
                </Form>

                <p v-else class="text-sm text-muted-foreground">
                    No farm records yet.
                </p>
            </section>

            <section class="space-y-4 rounded-lg border p-4">
                <div class="flex items-center gap-2">
                    <CalendarDays class="size-4" />
                    <Heading
                        variant="small"
                        title="Create production cycle"
                        description="Season plan with unit allocation and commodity mix"
                    />
                </div>

                <Form
                    v-if="farms.length > 0 && commodities.length > 0"
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
                    <div class="grid gap-2">
                        <Label for="cycle-farm">Farm</Label>
                        <select
                            id="cycle-farm"
                            v-model="cycleFarmId"
                            class="h-9 rounded-md border bg-background px-3 text-sm"
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
                    <div class="grid gap-2">
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
                        <InputError :message="errors.status" />
                    </div>
                    <div class="grid gap-2 md:col-span-2">
                        <Label for="cycle-name">Name</Label>
                        <Input id="cycle-name" name="name" required />
                        <InputError :message="errors.name" />
                    </div>
                    <input
                        type="hidden"
                        name="farm_type"
                        :value="selectedCycleFarm?.farmType ?? farmType"
                    />
                    <div class="grid gap-2">
                        <Label for="cycle-season">Season</Label>
                        <Input id="cycle-season" name="season" />
                        <InputError :message="errors.season" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="production-method">Method</Label>
                        <Input
                            id="production-method"
                            name="production_method"
                        />
                        <InputError :message="errors.production_method" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="planned-start">Planned start</Label>
                        <Input
                            id="planned-start"
                            name="planned_start_on"
                            type="date"
                            required
                        />
                        <InputError :message="errors.planned_start_on" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="planned-end">Planned end</Label>
                        <Input
                            id="planned-end"
                            name="planned_end_on"
                            type="date"
                        />
                        <InputError :message="errors.planned_end_on" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="primary-commodity">Primary commodity</Label>
                        <select
                            id="primary-commodity"
                            v-model="primaryCommodityId"
                            name="primary_commodity_id"
                            class="h-9 rounded-md border bg-background px-3 text-sm"
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
                        <InputError :message="errors.primary_commodity_id" />
                        <InputError :message="errors.commodities" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="secondary-commodity">Intercrop</Label>
                        <select
                            id="secondary-commodity"
                            v-model="secondaryCommodityId"
                            name="secondary_commodity_id"
                            class="h-9 rounded-md border bg-background px-3 text-sm"
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
                        <InputError :message="errors.secondary_commodity_id" />
                    </div>
                    <div class="grid gap-2 md:col-span-2">
                        <Label for="cycle-units">Production units</Label>
                        <select
                            id="cycle-units"
                            name="production_unit_ids[]"
                            multiple
                            class="min-h-24 rounded-md border bg-background px-3 py-2 text-sm"
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
                    <div class="grid gap-2">
                        <Label for="expected-output">Expected output</Label>
                        <Input
                            id="expected-output"
                            name="expected_output_quantity"
                            type="number"
                            min="0"
                            step="0.01"
                        />
                        <InputError
                            :message="errors.expected_output_quantity"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="expected-output-unit">Output unit</Label>
                        <Input
                            id="expected-output-unit"
                            name="expected_output_unit"
                        />
                        <InputError :message="errors.expected_output_unit" />
                    </div>
                    <div class="md:col-span-2">
                        <Button
                            type="submit"
                            :disabled="
                                processing ||
                                (selectedCycleFarm?.productionUnits.length ??
                                    0) === 0
                            "
                        >
                            Create cycle
                        </Button>
                    </div>
                </Form>

                <p v-else class="text-sm text-muted-foreground">
                    Add a farm and commodity first.
                </p>
            </section>
        </div>

        <section class="space-y-4">
            <Heading
                variant="small"
                title="Operating records"
                description="Current-team farms, units, cycles, and commodity mixes"
            />

            <div v-if="farms.length > 0" class="space-y-4">
                <article
                    v-for="farm in farms"
                    :key="farm.id"
                    class="space-y-4 rounded-lg border p-4"
                >
                    <div
                        class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between"
                    >
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="text-lg font-semibold">
                                    {{ farm.name }}
                                </h2>
                                <Badge variant="secondary">
                                    {{ farm.farmTypeLabel }}
                                </Badge>
                                <Badge>{{ farm.status }}</Badge>
                            </div>
                            <p
                                v-if="farm.location"
                                class="mt-1 text-sm text-muted-foreground"
                            >
                                {{ farm.location }}
                            </p>
                        </div>
                        <div class="grid grid-cols-2 gap-3 text-sm md:min-w-48">
                            <div>
                                <p class="text-muted-foreground">Units</p>
                                <p class="font-medium">
                                    {{ farm.productionUnitsCount }}
                                </p>
                            </div>
                            <div>
                                <p class="text-muted-foreground">Cycles</p>
                                <p class="font-medium">
                                    {{ farm.productionCyclesCount }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-4 xl:grid-cols-2">
                        <div class="space-y-2">
                            <h3 class="text-sm font-medium">
                                Production units
                            </h3>
                            <div
                                v-if="farm.productionUnits.length > 0"
                                class="grid gap-2"
                            >
                                <div
                                    v-for="unit in farm.productionUnits"
                                    :key="unit.id"
                                    class="flex items-center justify-between rounded-lg border px-3 py-2 text-sm"
                                >
                                    <span>{{ unit.name }}</span>
                                    <span class="text-muted-foreground">
                                        {{ unit.unitTypeLabel }}
                                    </span>
                                </div>
                            </div>
                            <p v-else class="text-sm text-muted-foreground">
                                No production units yet.
                            </p>
                        </div>

                        <div class="space-y-2">
                            <h3 class="text-sm font-medium">
                                Production cycles
                            </h3>
                            <div
                                v-if="farm.productionCycles.length > 0"
                                class="grid gap-2"
                            >
                                <div
                                    v-for="cycle in farm.productionCycles"
                                    :key="cycle.id"
                                    class="space-y-2 rounded-lg border px-3 py-2 text-sm"
                                >
                                    <div
                                        class="flex flex-wrap items-center justify-between gap-2"
                                    >
                                        <span class="font-medium">
                                            {{ cycle.name }}
                                        </span>
                                        <Badge variant="secondary">
                                            v{{ cycle.planVersion }}
                                        </Badge>
                                    </div>
                                    <div
                                        class="flex flex-wrap items-center gap-2 text-muted-foreground"
                                    >
                                        <span>{{ cycle.statusLabel }}</span>
                                        <span>{{ cycle.plannedStartOn }}</span>
                                        <span v-if="cycle.plannedEndOn">
                                            {{ cycle.plannedEndOn }}
                                        </span>
                                    </div>
                                    <div class="flex flex-wrap gap-2">
                                        <Badge
                                            v-for="commodity in cycle.commodities"
                                            :key="`${cycle.id}-${commodity.id}`"
                                            variant="outline"
                                        >
                                            {{ commodity.name }}:
                                            {{ commodity.roleLabel }}
                                        </Badge>
                                    </div>
                                </div>
                            </div>
                            <p v-else class="text-sm text-muted-foreground">
                                No production cycles yet.
                            </p>
                        </div>
                    </div>
                </article>
            </div>

            <div
                v-else
                class="rounded-lg border p-4 text-sm text-muted-foreground"
            >
                No farm records yet.
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            <div class="space-y-4 rounded-lg border p-4">
                <div class="flex items-center gap-2">
                    <ClipboardList class="size-4" />
                    <Heading
                        variant="small"
                        title="Plan changes"
                        description="Reason, impact, reviewer-safe summary, and audit trail"
                    />
                </div>

                <Form
                    v-if="
                        permissions.canManageFarmOperations &&
                        currentTeamSlug &&
                        cycleTargets.length > 0
                    "
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
                    <div class="grid gap-2">
                        <Label for="plan-change-target">Cycle</Label>
                        <select
                            id="plan-change-target"
                            v-model="planChangeTarget"
                            class="h-9 rounded-md border bg-background px-3 text-sm"
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
                    <div class="grid gap-2">
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
                        <InputError :message="errors.change_type" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="plan-reason">Reason</Label>
                        <Input id="plan-reason" name="reason" required />
                        <InputError :message="errors.reason" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="plan-impact">Impact</Label>
                        <Input id="plan-impact" name="impact" required />
                        <InputError :message="errors.impact" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="investor-summary">
                            Investor-safe summary
                        </Label>
                        <Input
                            id="investor-summary"
                            name="investor_safe_summary"
                        />
                        <InputError :message="errors.investor_safe_summary" />
                    </div>
                    <Button
                        type="submit"
                        :disabled="processing || !selectedPlanChangeTarget"
                    >
                        Record change
                    </Button>
                </Form>

                <p v-else class="text-sm text-muted-foreground">
                    No production cycles yet.
                </p>
            </div>

            <div class="space-y-4 rounded-lg border p-4">
                <div class="flex items-center gap-2">
                    <CalendarDays class="size-4" />
                    <Heading
                        variant="small"
                        title="Latest plan changes"
                        description="Captured reason and operating impact"
                    />
                </div>

                <div v-if="latestPlanChanges.length > 0" class="space-y-3">
                    <div
                        v-for="change in latestPlanChanges"
                        :key="change.id"
                        class="rounded-lg border p-3 text-sm"
                    >
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <p class="font-medium">
                                {{ change.farmName }} / {{ change.cycleName }}
                            </p>
                            <Badge variant="secondary">
                                {{ change.changeTypeLabel }}
                            </Badge>
                        </div>
                        <p class="mt-2 text-muted-foreground">
                            {{ change.reason }}
                        </p>
                        <p class="mt-1">{{ change.impact }}</p>
                    </div>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    No plan changes yet.
                </p>
            </div>
        </section>
    </div>
</template>
