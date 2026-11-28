<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import {
    AlertCircle,
    CalendarClock,
    CheckCircle2,
    HeartPulse,
    Plus,
    ShieldAlert,
    Sparkles,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import EmptyState from '@/components/farm/EmptyState.vue';
import MetricCard from '@/components/farm/MetricCard.vue';
import PageHero from '@/components/farm/PageHero.vue';
import SlideOverForm from '@/components/farm/SlideOverForm.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import { store as applyPreset } from '@/routes/farms/production-cycles/health-presets';
import {
    index as healthIndex,
    store as storeHealthEvent,
    update as updateHealthEvent,
} from '@/routes/health';
import type { FarmOption, FarmOperationPermissions, Team } from '@/types';

type HealthEventRow = {
    id: number;
    farmId: number;
    farmName: string;
    productionCycleId: number | null;
    productionCycleName: string | null;
    productionUnitName: string | null;
    inventoryItemId: number | null;
    inventoryItemName: string | null;
    eventType: string;
    eventTypeLabel: string;
    title: string;
    productName: string | null;
    dosage: string | null;
    administrationRoute: string | null;
    scheduledOn: string;
    completedOn: string | null;
    withdrawalDays: number | null;
    withdrawalEndsOn: string | null;
    isUnderWithdrawal: boolean;
    isOverdue: boolean;
    status: string;
    statusLabel: string;
    cost: string;
    currency: string;
    notes: string | null;
};

const props = defineProps<{
    permissions: FarmOperationPermissions;
    currency: string;
    summary: {
        scheduled: number;
        overdue: number;
        completed: number;
        activeWithdrawals: number;
    };
    events: HealthEventRow[];
    activeWithdrawals: HealthEventRow[];
    farms: {
        id: number;
        name: string;
        farmType: string;
        productionUnits: { id: number; name: string }[];
        productionCycles: { id: number; name: string }[];
    }[];
    inventoryItems: {
        id: number;
        name: string;
        unit: string;
        currentQuantity: string;
    }[];
    options: {
        eventTypes: FarmOption[];
        eventStatuses: FarmOption[];
        presets: {
            key: string;
            label: string;
            description: string;
            eventCount: number;
        }[];
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
                title: 'Health & Biosecurity',
                href: props.currentTeam
                    ? healthIndex(props.currentTeam.slug)
                    : '/',
            },
        ],
    }),
});

const page = usePage();
const teamSlug = computed(
    () => (page.props.currentTeam as Team | null)?.slug ?? '',
);

const statusFilter = ref('all');
const createDrawerOpen = ref(false);
const presetDrawerOpen = ref(false);
const selectedFarmId = ref(props.farms[0]?.id ? String(props.farms[0].id) : '');
const presetFarmId = ref(props.farms[0]?.id ? String(props.farms[0].id) : '');

const selectedFarm = computed(() =>
    props.farms.find((f) => String(f.id) === selectedFarmId.value),
);
const presetFarm = computed(() =>
    props.farms.find((f) => String(f.id) === presetFarmId.value),
);
const presetCycleId = ref(
    presetFarm.value?.productionCycles[0]?.id
        ? String(presetFarm.value.productionCycles[0].id)
        : '',
);

const filteredEvents = computed(() =>
    props.events.filter((ev) => {
        if (statusFilter.value === 'overdue') {
            return ev.isOverdue;
        }

        if (statusFilter.value !== 'all') {
            return ev.status === statusFilter.value;
        }

        return true;
    }),
);

const today = new Date().toISOString().slice(0, 10);
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Head title="Health, Vaccines & Biosecurity" />

        <PageHero
            eyebrow="Preventive Care & Guardrails"
            title="Health, vaccines & treatments"
            description="Schedule vaccinations, pond prophylaxis, and crop sprays, and monitor active harvest withdrawal windows."
        >
            <template #actions>
                <Button
                    v-if="
                        permissions.canManageFarmOperations && farms.length > 0
                    "
                    type="button"
                    variant="secondary"
                    @click="presetDrawerOpen = true"
                >
                    <Sparkles class="mr-1.5 size-4" />
                    Apply preset schedule
                </Button>
                <Button
                    v-if="
                        permissions.canManageFarmOperations && farms.length > 0
                    "
                    type="button"
                    class="bg-white text-[#1b4d3e] hover:bg-emerald-50"
                    @click="createDrawerOpen = true"
                >
                    <Plus class="mr-1.5 size-4" />
                    Schedule health event
                </Button>
            </template>
        </PageHero>

        <section
            v-if="activeWithdrawals.length > 0"
            class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
        >
            <div class="flex items-start gap-3">
                <ShieldAlert
                    class="mt-0.5 size-5 shrink-0 text-amber-700 dark:text-amber-300"
                />
                <div class="space-y-1">
                    <p class="font-semibold">
                        Active withdrawal period warning
                    </p>
                    <ul class="space-y-1">
                        <li v-for="w in activeWithdrawals" :key="w.id">
                            <strong>{{ w.farmName }}</strong>
                            <span v-if="w.productionCycleName">
                                ({{ w.productionCycleName }})
                            </span>
                            — {{ w.title }}: safe harvest on/after
                            <strong>{{ w.withdrawalEndsOn }}</strong>
                        </li>
                    </ul>
                </div>
            </div>
        </section>

        <section class="grid grid-cols-2 gap-4 xl:grid-cols-4">
            <MetricCard
                label="Scheduled"
                :value="summary.scheduled"
                :icon="CalendarClock"
                tone="cyan"
            />
            <MetricCard
                label="Overdue"
                :value="summary.overdue"
                :icon="AlertCircle"
                :tone="summary.overdue > 0 ? 'rose' : 'default'"
            />
            <MetricCard
                label="Completed"
                :value="summary.completed"
                :icon="CheckCircle2"
                tone="emerald"
            />
            <MetricCard
                label="Active withdrawals"
                :value="summary.activeWithdrawals"
                :icon="ShieldAlert"
                :tone="summary.activeWithdrawals > 0 ? 'amber' : 'default'"
            />
        </section>

        <div
            class="no-scrollbar -mx-4 flex min-w-0 flex-nowrap gap-1.5 overflow-x-auto px-4 pb-1 sm:mx-0 sm:flex-wrap sm:overflow-visible sm:px-0 sm:pb-0"
        >
            <button
                v-for="tab in [
                    { value: 'all', label: 'All' },
                    { value: 'scheduled', label: 'Scheduled' },
                    { value: 'overdue', label: 'Overdue' },
                    { value: 'completed', label: 'Completed' },
                ]"
                :key="tab.value"
                type="button"
                class="min-h-9 shrink-0 rounded-full border px-3.5 py-1.5 text-xs font-medium transition-colors"
                :class="
                    statusFilter === tab.value
                        ? 'border-primary bg-primary text-primary-foreground'
                        : 'bg-card hover:bg-accent'
                "
                @click="statusFilter = tab.value"
            >
                {{ tab.label }}
            </button>
        </div>

        <section v-if="filteredEvents.length > 0" class="grid gap-3">
            <article
                v-for="ev in filteredEvents"
                :key="ev.id"
                class="flex flex-col justify-between gap-4 rounded-2xl border bg-card p-4 text-sm sm:flex-row sm:items-center"
            >
                <div class="space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="font-semibold">{{ ev.title }}</h3>
                        <Badge variant="outline">{{ ev.eventTypeLabel }}</Badge>
                        <Badge
                            :variant="
                                ev.isOverdue
                                    ? 'destructive'
                                    : ev.status === 'completed'
                                      ? 'default'
                                      : 'secondary'
                            "
                        >
                            {{ ev.isOverdue ? 'Overdue' : ev.statusLabel }}
                        </Badge>
                    </div>
                    <p class="text-xs text-muted-foreground">
                        {{ ev.farmName }}
                        <span v-if="ev.productionCycleName">
                            · {{ ev.productionCycleName }}
                        </span>
                        · Scheduled {{ ev.scheduledOn }}
                        <span v-if="ev.productName">
                            · Product: {{ ev.productName }}
                        </span>
                        <span v-if="ev.isUnderWithdrawal">
                            · Withdrawal until {{ ev.withdrawalEndsOn }}
                        </span>
                    </p>
                    <p v-if="ev.notes" class="text-muted-foreground">
                        {{ ev.notes }}
                    </p>
                </div>

                <div
                    v-if="
                        ev.status === 'scheduled' &&
                        permissions.canManageFieldWork &&
                        teamSlug
                    "
                    class="flex shrink-0 items-center gap-2"
                >
                    <Form
                        v-bind="updateHealthEvent.form([teamSlug, ev.id])"
                        v-slot="{ processing }"
                    >
                        <input type="hidden" name="status" value="completed" />
                        <input
                            type="hidden"
                            name="completed_on"
                            :value="today"
                        />
                        <Button type="submit" size="sm" :disabled="processing">
                            Mark done
                        </Button>
                    </Form>
                    <Form
                        v-bind="updateHealthEvent.form([teamSlug, ev.id])"
                        v-slot="{ processing }"
                    >
                        <input type="hidden" name="status" value="skipped" />
                        <Button
                            type="submit"
                            size="sm"
                            variant="outline"
                            :disabled="processing"
                        >
                            Skip
                        </Button>
                    </Form>
                </div>
            </article>
        </section>

        <EmptyState
            v-else
            title="No health events found"
            description="Schedule vaccinations, deworming, pond treatments, or crop protection sprays—or apply a starter preset."
            :icon="HeartPulse"
        />

        <!-- Create Health Event Drawer -->
        <SlideOverForm
            :open="createDrawerOpen"
            title="Schedule health or treatment event"
            @update:open="createDrawerOpen = $event"
        >
            <Form
                v-if="teamSlug"
                v-bind="storeHealthEvent.form(teamSlug)"
                class="grid gap-4"
                reset-on-success
                v-slot="{ errors, processing }"
                @success="createDrawerOpen = false"
            >
                <div class="grid gap-1.5">
                    <Label for="he-farm">Farm</Label>
                    <select
                        id="he-farm"
                        v-model="selectedFarmId"
                        name="farm_id"
                        class="h-11 rounded-md border bg-background px-3 text-base md:h-9 md:text-sm"
                        required
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
                    <Label for="he-cycle">Production cycle</Label>
                    <select
                        id="he-cycle"
                        name="production_cycle_id"
                        class="h-11 rounded-md border bg-background px-3 text-base md:h-9 md:text-sm"
                    >
                        <option value="">Farm-wide</option>
                        <option
                            v-for="cycle in selectedFarm?.productionCycles ??
                            []"
                            :key="cycle.id"
                            :value="cycle.id"
                        >
                            {{ cycle.name }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="he-type">Event type</Label>
                    <select
                        id="he-type"
                        name="event_type"
                        class="h-11 rounded-md border bg-background px-3 text-base md:h-9 md:text-sm"
                        required
                    >
                        <option
                            v-for="opt in options.eventTypes"
                            :key="opt.value"
                            :value="opt.value"
                        >
                            {{ opt.label }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="he-title">Title</Label>
                    <Input
                        id="he-title"
                        name="title"
                        placeholder="e.g. Day 14 Gumboro Vaccine"
                        required
                    />
                    <InputError :message="errors.title" />
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-1.5">
                        <Label for="he-date">Scheduled date</Label>
                        <Input
                            id="he-date"
                            name="scheduled_on"
                            type="date"
                            :default-value="today"
                            required
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="he-withdrawal">Withdrawal days</Label>
                        <Input
                            id="he-withdrawal"
                            name="withdrawal_days"
                            type="number"
                            inputmode="numeric"
                            min="0"
                            default-value="0"
                        />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div class="grid gap-1.5">
                        <Label for="he-product">Product / vaccine</Label>
                        <Input id="he-product" name="product_name" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="he-cost"
                            >Estimated cost ({{ currency }})</Label
                        >
                        <Input
                            id="he-cost"
                            name="cost"
                            type="number"
                            inputmode="decimal"
                            min="0"
                            step="0.01"
                        />
                    </div>
                </div>
                <div class="grid gap-1.5">
                    <Label for="he-notes">Notes</Label>
                    <Input id="he-notes" name="notes" />
                </div>
                <Button
                    type="submit"
                    :disabled="processing"
                    class="min-h-11 w-full sm:min-h-9 sm:w-auto"
                    >Save schedule</Button
                >
            </Form>
        </SlideOverForm>

        <!-- Apply Preset Drawer -->
        <SlideOverForm
            :open="presetDrawerOpen"
            title="Apply health schedule preset"
            description="Generate standard vaccination, pond, or crop spray dates from a cycle's start date"
            @update:open="presetDrawerOpen = $event"
        >
            <Form
                v-if="teamSlug && presetFarm && presetCycleId"
                v-bind="
                    applyPreset.form([
                        teamSlug,
                        presetFarm.id,
                        Number(presetCycleId),
                    ])
                "
                class="grid gap-4"
                v-slot="{ processing }"
                @success="presetDrawerOpen = false"
            >
                <div class="grid gap-1.5">
                    <Label>Farm</Label>
                    <select
                        v-model="presetFarmId"
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
                    <Label>Cycle</Label>
                    <select
                        v-model="presetCycleId"
                        class="h-11 rounded-md border bg-background px-3 text-base md:h-9 md:text-sm"
                    >
                        <option
                            v-for="cycle in presetFarm.productionCycles"
                            :key="cycle.id"
                            :value="String(cycle.id)"
                        >
                            {{ cycle.name }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="preset-key">Preset schedule</Label>
                    <select
                        id="preset-key"
                        name="preset_key"
                        class="h-11 rounded-md border bg-background px-3 text-base md:h-9 md:text-sm"
                        required
                    >
                        <option
                            v-for="p in options.presets"
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
            <p v-else class="text-sm text-muted-foreground">
                Select a farm with an active or planned cycle first.
            </p>
        </SlideOverForm>
    </div>
</template>
