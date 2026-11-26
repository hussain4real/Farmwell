<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    Bird,
    CheckCircle2,
    Fish,
    Layers,
    Plus,
    Sprout,
    Trash2,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import PageHero from '@/components/farm/PageHero.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import {
    index as onboardingIndex,
    store as storeOnboarding,
} from '@/routes/onboarding';
import type { FarmOption, Team } from '@/types';

type FarmTemplate = {
    key: string;
    name: string;
    tagline: string;
    farmType: string;
    enterpriseSubtype: string;
    defaultUnitType: string;
    defaultUnitName: string;
    defaultSizeUnit: string;
    defaultCommodityName: string;
    defaultCommodityUnit: string;
    defaultBreed: string | null;
    defaultDurationDays: number;
    defaultInitialStock: number | null;
    defaultInitialWeightG: number | null;
    defaultTargetWeightG: number | null;
    defaultGrowthStage: string | null;
    defaultExpectedOutputUnit: string;
    healthPreset: string | null;
    starterTasks: {
        title: string;
        activity_type: string;
        day_offset: number;
    }[];
    starterInventory: { name: string; category: string; unit: string }[];
};

const props = defineProps<{
    currency: string;
    investorMode: boolean;
    templates: FarmTemplate[];
    options: {
        farmTypes: FarmOption[];
        productionUnitTypes: FarmOption[];
        enterpriseSubtypes: (FarmOption & { farmType: string })[];
        cropGrowthStages: FarmOption[];
    };
}>();

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
                title: 'Onboard Farm',
                href: props.currentTeam
                    ? onboardingIndex(props.currentTeam.slug)
                    : '/',
            },
        ],
    }),
});

const page = usePage();
const teamSlug = computed(
    () => (page.props.currentTeam as Team | null)?.slug ?? '',
);
const step = ref(1);

const initialTemplate = props.templates[0];
const today = new Date().toISOString().slice(0, 10);

const addDays = (dateStr: string, days: number) => {
    const d = new Date(dateStr);
    d.setDate(d.getDate() + days);

    return d.toISOString().slice(0, 10);
};

const form = useForm({
    template_key: initialTemplate?.key ?? 'poultry_broiler',
    currency: props.currency || 'NGN',
    investor_mode: props.investorMode,
    farm_name: '',
    farm_type: initialTemplate?.farmType ?? 'poultry',
    location: '',
    contact_name: '',
    contact_phone: '',
    description: '',
    units: [
        {
            name: initialTemplate?.defaultUnitName ?? 'Unit 1',
            unit_type: initialTemplate?.defaultUnitType ?? 'house',
            size: '',
            size_unit: initialTemplate?.defaultSizeUnit ?? 'sqm',
            capacity: '',
            water_source: '',
            housing_system: '',
        },
    ],
    commodity_name: initialTemplate?.defaultCommodityName ?? 'Broiler Chicken',
    commodity_unit: initialTemplate?.defaultCommodityUnit ?? 'birds',
    cycle_name: '',
    season: String(new Date().getFullYear()),
    enterprise_subtype: initialTemplate?.enterpriseSubtype ?? 'broiler',
    breed_or_variety: initialTemplate?.defaultBreed ?? '',
    planned_start_on: today,
    planned_end_on: addDays(today, initialTemplate?.defaultDurationDays ?? 49),
    expected_output_quantity: '',
    expected_output_unit: initialTemplate?.defaultExpectedOutputUnit ?? 'birds',
    initial_stock_count: initialTemplate?.defaultInitialStock ?? undefined,
    initial_average_weight_g:
        initialTemplate?.defaultInitialWeightG ?? undefined,
    target_weight_g: initialTemplate?.defaultTargetWeightG ?? undefined,
    current_growth_stage: initialTemplate?.defaultGrowthStage ?? '',
    cycle_status: 'active',
    create_starter_tasks: true,
    create_starter_inventory: true,
    create_health_schedule: true,
});

const selectedTemplate = computed(
    () =>
        props.templates.find((t) => t.key === form.template_key) ??
        props.templates[0],
);

const applyTemplate = (template: FarmTemplate) => {
    form.template_key = template.key;
    form.farm_type = template.farmType;
    form.enterprise_subtype = template.enterpriseSubtype;
    form.commodity_name = template.defaultCommodityName;
    form.commodity_unit = template.defaultCommodityUnit;
    form.breed_or_variety = template.defaultBreed ?? '';
    form.planned_end_on = addDays(
        form.planned_start_on || today,
        template.defaultDurationDays,
    );
    form.initial_stock_count = template.defaultInitialStock ?? undefined;
    form.initial_average_weight_g = template.defaultInitialWeightG ?? undefined;
    form.target_weight_g = template.defaultTargetWeightG ?? undefined;
    form.current_growth_stage = template.defaultGrowthStage ?? '';
    form.expected_output_unit = template.defaultExpectedOutputUnit;
    form.units = [
        {
            name: template.defaultUnitName,
            unit_type: template.defaultUnitType,
            size: '',
            size_unit: template.defaultSizeUnit,
            capacity: '',
            water_source: '',
            housing_system: '',
        },
    ];
};

const addUnit = () => {
    const template = selectedTemplate.value;
    form.units.push({
        name: `${template?.defaultUnitType === 'pond' ? 'Pond' : 'Unit'} ${form.units.length + 1}`,
        unit_type: template?.defaultUnitType ?? 'plot',
        size: '',
        size_unit: template?.defaultSizeUnit ?? 'sqm',
        capacity: '',
        water_source: '',
        housing_system: '',
    });
};

const removeUnit = (index: number) => {
    if (form.units.length > 1) {
        form.units.splice(index, 1);
    }
};

const templateIcon = (farmType: string) => {
    if (farmType === 'poultry') {
        return Bird;
    }

    if (farmType === 'aquaculture') {
        return Fish;
    }

    if (farmType === 'crop') {
        return Sprout;
    }

    return Layers;
};

const isStockFarm = computed(
    () =>
        form.farm_type === 'poultry' ||
        form.farm_type === 'aquaculture' ||
        form.farm_type === 'livestock',
);

const submit = () => {
    if (!teamSlug.value) {
        return;
    }

    form.post(storeOnboarding.url(teamSlug.value));
};
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Head title="Onboard Farm" />

        <PageHero
            eyebrow="Guided Farm Setup"
            title="Launch your farm workspace"
            description="Choose your enterprise type and Farmwell automatically sets up your pens/ponds/fields, initial batch or season, starter tasks, and stock items."
        />

        <!-- Stepper -->
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
            <button
                v-for="s in [
                    { n: 1, label: 'Enterprise' },
                    { n: 2, label: 'Farm Profile' },
                    { n: 3, label: 'Units & Batch' },
                    { n: 4, label: 'Starter Kit' },
                ]"
                :key="s.n"
                type="button"
                class="flex items-center gap-2 rounded-xl border px-3 py-2.5 text-left text-xs font-medium transition-colors sm:text-sm"
                :class="
                    step === s.n
                        ? 'border-primary bg-primary/10 text-primary'
                        : step > s.n
                          ? 'border-emerald-200 bg-emerald-50/70 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-300'
                          : 'bg-card text-muted-foreground'
                "
                @click="step = s.n"
            >
                <span
                    class="flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-semibold"
                    :class="
                        step >= s.n
                            ? 'bg-primary text-primary-foreground'
                            : 'bg-muted text-muted-foreground'
                    "
                >
                    {{ s.n }}
                </span>
                <span class="truncate">{{ s.label }}</span>
            </button>
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <!-- Step 1: Enterprise Template -->
            <section v-show="step === 1" class="space-y-4">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <button
                        v-for="template in templates"
                        :key="template.key"
                        type="button"
                        class="flex flex-col justify-between rounded-2xl border p-5 text-left transition-all hover:shadow-sm"
                        :class="
                            form.template_key === template.key
                                ? 'border-primary bg-primary/5 ring-2 ring-primary/20'
                                : 'bg-card hover:border-primary/40'
                        "
                        @click="applyTemplate(template)"
                    >
                        <div>
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <span
                                    class="flex size-10 items-center justify-center rounded-xl bg-accent text-accent-foreground"
                                >
                                    <component
                                        :is="templateIcon(template.farmType)"
                                        class="size-5"
                                    />
                                </span>
                                <CheckCircle2
                                    v-if="form.template_key === template.key"
                                    class="size-5 text-primary"
                                />
                            </div>
                            <h3 class="mt-4 font-semibold">
                                {{ template.name }}
                            </h3>
                            <p class="mt-1.5 text-sm text-muted-foreground">
                                {{ template.tagline }}
                            </p>
                        </div>
                        <div
                            class="mt-4 flex flex-wrap gap-1.5 text-[11px] text-muted-foreground"
                        >
                            <span class="rounded-md bg-muted px-2 py-0.5">
                                {{ template.starterTasks.length }}
                                {{
                                    template.starterTasks.length === 1
                                        ? 'starter task'
                                        : 'starter tasks'
                                }}
                            </span>
                            <span
                                v-if="template.starterInventory.length"
                                class="rounded-md bg-muted px-2 py-0.5"
                            >
                                {{ template.starterInventory.length }} stock
                                presets
                            </span>
                        </div>
                    </button>
                </div>

                <div class="flex justify-end">
                    <Button
                        type="button"
                        class="min-h-11 w-full sm:min-h-9 sm:w-auto"
                        @click="step = 2"
                    >
                        Continue to Farm Profile
                        <ArrowRight class="ml-2 size-4" />
                    </Button>
                </div>
            </section>

            <!-- Step 2: Farm Profile -->
            <section
                v-show="step === 2"
                class="space-y-5 rounded-2xl border bg-card p-6"
            >
                <h2 class="text-lg font-semibold">
                    Farm details & workspace mode
                </h2>
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-1.5 md:col-span-2">
                        <Label for="onboard-farm-name">Farm name</Label>
                        <Input
                            id="onboard-farm-name"
                            v-model="form.farm_name"
                            placeholder="e.g. Sunrise Poultry Farm"
                            required
                        />
                        <InputError :message="form.errors.farm_name" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="onboard-location">Location / Town</Label>
                        <Input
                            id="onboard-location"
                            v-model="form.location"
                            placeholder="e.g. Ibadan, Oyo State"
                        />
                        <InputError :message="form.errors.location" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="onboard-currency">Default currency</Label>
                        <Input
                            id="onboard-currency"
                            v-model="form.currency"
                            maxlength="3"
                            placeholder="NGN"
                        />
                        <InputError :message="form.errors.currency" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="onboard-contact"
                            >Farm manager / contact</Label
                        >
                        <Input
                            id="onboard-contact"
                            v-model="form.contact_name"
                            placeholder="Optional"
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="onboard-phone">Contact phone</Label>
                        <Input
                            id="onboard-phone"
                            v-model="form.contact_phone"
                            placeholder="Optional"
                        />
                    </div>
                </div>

                <div class="rounded-xl border bg-muted/40 p-4">
                    <label class="flex items-start gap-3">
                        <input
                            v-model="form.investor_mode"
                            type="checkbox"
                            class="mt-1 size-4"
                        />
                        <span class="text-sm">
                            <strong class="block font-medium">
                                Enable Investor Transparency Mode
                            </strong>
                            <span class="text-muted-foreground">
                                Leave unchecked if this is a standalone
                                self-funded farm. You can always enable it later
                                in Team Settings.
                            </span>
                        </span>
                    </label>
                </div>

                <div
                    class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-between"
                >
                    <Button
                        type="button"
                        variant="outline"
                        class="min-h-11 w-full sm:min-h-9 sm:w-auto"
                        @click="step = 1"
                    >
                        <ArrowLeft class="mr-2 size-4" />
                        Back
                    </Button>
                    <Button
                        type="button"
                        class="min-h-11 w-full sm:min-h-9 sm:w-auto"
                        @click="step = 3"
                    >
                        Continue to Units & Batch
                        <ArrowRight class="ml-2 size-4" />
                    </Button>
                </div>
            </section>

            <!-- Step 3: Production Units & First Cycle -->
            <section
                v-show="step === 3"
                class="space-y-6 rounded-2xl border bg-card p-6"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h2 class="text-lg font-semibold">
                            Production units ({{
                                form.farm_type === 'aquaculture'
                                    ? 'Ponds / Tanks'
                                    : form.farm_type === 'poultry'
                                      ? 'Houses / Pens'
                                      : 'Fields / Plots'
                            }})
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            Add one or more physical units on this farm.
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="addUnit"
                    >
                        <Plus class="mr-1.5 size-4" />
                        Add unit
                    </Button>
                </div>

                <div class="space-y-3">
                    <div
                        v-for="(unit, idx) in form.units"
                        :key="idx"
                        class="grid gap-3 rounded-xl border bg-muted/30 p-4 md:grid-cols-4"
                    >
                        <div class="grid gap-1.5 md:col-span-2">
                            <Label>Unit name</Label>
                            <Input v-model="unit.name" required />
                        </div>
                        <div class="grid gap-1.5">
                            <Label>Type</Label>
                            <select
                                v-model="unit.unit_type"
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
                        <div class="grid gap-1.5">
                            <Label>Size & unit</Label>
                            <div class="flex gap-1.5">
                                <Input
                                    v-model="unit.size"
                                    type="number"
                                    inputmode="decimal"
                                    min="0"
                                    step="0.01"
                                    placeholder="Size"
                                />
                                <Input
                                    v-model="unit.size_unit"
                                    placeholder="sqm/acres"
                                    class="w-24"
                                />
                            </div>
                        </div>
                        <div
                            v-if="
                                form.farm_type === 'aquaculture' ||
                                form.farm_type === 'crop'
                            "
                            class="grid gap-1.5 md:col-span-2"
                        >
                            <Label>Water source</Label>
                            <Input
                                v-model="unit.water_source"
                                placeholder="e.g. Borehole, Rain-fed, Drip"
                            />
                        </div>
                        <div
                            v-if="form.farm_type === 'poultry'"
                            class="grid gap-1.5 md:col-span-2"
                        >
                            <Label>Housing system</Label>
                            <Input
                                v-model="unit.housing_system"
                                placeholder="e.g. Deep litter, Battery cage"
                            />
                        </div>
                        <div
                            class="flex items-end justify-between md:col-span-2"
                        >
                            <div class="grid flex-1 gap-1.5">
                                <Label>Capacity</Label>
                                <Input
                                    v-model="unit.capacity"
                                    placeholder="e.g. 1000 birds / 2000 fish"
                                />
                            </div>
                            <Button
                                v-if="form.units.length > 1"
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="ml-2 text-destructive"
                                @click="removeUnit(idx)"
                            >
                                <Trash2 class="size-4" />
                            </Button>
                        </div>
                    </div>
                </div>

                <div class="border-t pt-5">
                    <h3 class="text-base font-semibold">
                        First production cycle / batch
                    </h3>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label for="onboard-cycle-name"
                                >Batch / cycle name</Label
                            >
                            <Input
                                id="onboard-cycle-name"
                                v-model="form.cycle_name"
                                placeholder="e.g. Batch 1 — Nov 2026"
                                required
                            />
                            <InputError :message="form.errors.cycle_name" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="onboard-commodity"
                                >Primary commodity</Label
                            >
                            <Input
                                id="onboard-commodity"
                                v-model="form.commodity_name"
                                required
                            />
                            <InputError :message="form.errors.commodity_name" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="onboard-breed"
                                >Breed / variety / strain</Label
                            >
                            <Input
                                id="onboard-breed"
                                v-model="form.breed_or_variety"
                                placeholder="e.g. Cobb 500, SAMMAZ 52, Clarias"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="onboard-output-unit">Output unit</Label>
                            <Input
                                id="onboard-output-unit"
                                v-model="form.commodity_unit"
                                placeholder="kg / birds / crates"
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="onboard-start"
                                >Start / stocking date</Label
                            >
                            <Input
                                id="onboard-start"
                                v-model="form.planned_start_on"
                                type="date"
                                required
                            />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="onboard-end"
                                >Target end / harvest date</Label
                            >
                            <Input
                                id="onboard-end"
                                v-model="form.planned_end_on"
                                type="date"
                            />
                        </div>
                        <template v-if="isStockFarm">
                            <div class="grid gap-1.5">
                                <Label for="onboard-stock">
                                    Initial stock count ({{
                                        form.farm_type === 'poultry'
                                            ? 'birds'
                                            : 'fish'
                                    }})
                                </Label>
                                <Input
                                    id="onboard-stock"
                                    v-model.number="form.initial_stock_count"
                                    type="number"
                                    inputmode="numeric"
                                    min="0"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label for="onboard-target-weight">
                                    Target harvest weight (g)
                                </Label>
                                <Input
                                    id="onboard-target-weight"
                                    v-model.number="form.target_weight_g"
                                    type="number"
                                    inputmode="decimal"
                                    min="0"
                                />
                            </div>
                        </template>
                        <div v-else class="grid gap-1.5">
                            <Label for="onboard-stage"
                                >Current crop stage</Label
                            >
                            <select
                                id="onboard-stage"
                                v-model="form.current_growth_stage"
                                class="h-11 rounded-md border bg-background px-3 text-base md:h-9 md:text-sm"
                            >
                                <option value="">Select stage</option>
                                <option
                                    v-for="stage in options.cropGrowthStages"
                                    :key="stage.value"
                                    :value="stage.value"
                                >
                                    {{ stage.label }}
                                </option>
                            </select>
                        </div>
                    </div>
                </div>

                <div
                    class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-between"
                >
                    <Button
                        type="button"
                        variant="outline"
                        class="min-h-11 w-full sm:min-h-9 sm:w-auto"
                        @click="step = 2"
                    >
                        <ArrowLeft class="mr-2 size-4" />
                        Back
                    </Button>
                    <Button
                        type="button"
                        class="min-h-11 w-full sm:min-h-9 sm:w-auto"
                        @click="step = 4"
                    >
                        Review Starter Kit
                        <ArrowRight class="ml-2 size-4" />
                    </Button>
                </div>
            </section>

            <!-- Step 4: Starter Kit & Launch -->
            <section
                v-show="step === 4"
                class="space-y-5 rounded-2xl border bg-card p-6"
            >
                <h2 class="text-lg font-semibold">
                    Starter automation & launch
                </h2>
                <div class="grid gap-3 md:grid-cols-3">
                    <label class="flex items-start gap-3 rounded-xl border p-4">
                        <input
                            v-model="form.create_starter_tasks"
                            type="checkbox"
                            class="mt-1 size-4"
                        />
                        <span class="text-sm">
                            <strong class="block font-medium"
                                >Starter tasks</strong
                            >
                            <span class="text-muted-foreground">
                                Pre-load
                                {{ selectedTemplate?.starterTasks.length ?? 0 }}
                                key milestone reminders.
                            </span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 rounded-xl border p-4">
                        <input
                            v-model="form.create_starter_inventory"
                            type="checkbox"
                            class="mt-1 size-4"
                        />
                        <span class="text-sm">
                            <strong class="block font-medium"
                                >Stock catalog</strong
                            >
                            <span class="text-muted-foreground">
                                Pre-create
                                {{
                                    selectedTemplate?.starterInventory.length ??
                                    0
                                }}
                                feed/input items.
                            </span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 rounded-xl border p-4">
                        <input
                            v-model="form.create_health_schedule"
                            type="checkbox"
                            class="mt-1 size-4"
                        />
                        <span class="text-sm">
                            <strong class="block font-medium"
                                >Health schedule</strong
                            >
                            <span class="text-muted-foreground">
                                Schedule vaccinations, biosecurity, or spray
                                reminders.
                            </span>
                        </span>
                    </label>
                </div>

                <div class="rounded-xl border bg-muted/40 p-4 text-sm">
                    <p class="font-medium">Summary</p>
                    <p class="mt-1 text-muted-foreground">
                        Farm:
                        <strong>{{ form.farm_name || 'Untitled Farm' }}</strong>
                        ({{ form.farm_type }}) · {{ form.units.length }} unit(s)
                        · Batch/Cycle:
                        <strong>{{ form.cycle_name || 'First Cycle' }}</strong>
                    </p>
                </div>

                <div
                    class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-between"
                >
                    <Button
                        type="button"
                        variant="outline"
                        class="min-h-11 w-full sm:min-h-9 sm:w-auto"
                        @click="step = 3"
                    >
                        <ArrowLeft class="mr-2 size-4" />
                        Back
                    </Button>
                    <Button
                        type="submit"
                        :disabled="form.processing"
                        class="min-h-11 w-full sm:min-h-9 sm:w-auto"
                    >
                        Launch Farm Workspace
                    </Button>
                </div>
            </section>
        </form>
    </div>
</template>
