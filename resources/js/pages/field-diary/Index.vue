<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { ClipboardList, Download, Plus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import EmptyState from '@/components/farm/EmptyState.vue';
import PageHero from '@/components/farm/PageHero.vue';
import SlideOverForm from '@/components/farm/SlideOverForm.vue';
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
import { formatCurrency } from '@/lib/currency';
import { dashboard } from '@/routes';
import { index as activityExports } from '@/routes/activity-exports';
import { store as storeFarmActivity } from '@/routes/farm-activities';
import { index as fieldDiaryIndex } from '@/routes/field-diary';
import type {
    Commodity,
    Farm,
    FarmActivity,
    FarmOperationOptions,
    FarmOperationPermissions,
    Team,
} from '@/types';

type Props = {
    currency: string;
    permissions: FarmOperationPermissions;
    farms: Farm[];
    commodities: Commodity[];
    latestActivities: FarmActivity[];
    options: Pick<
        FarmOperationOptions,
        'activityStatuses' | 'evidenceVisibilities'
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
                title: 'Field Diary',
                href: props.currentTeam
                    ? fieldDiaryIndex(props.currentTeam.slug)
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

const drawerOpen = ref(false);
const farmFilter = ref('all');
const activityFarmId = ref(props.farms[0]?.id ? String(props.farms[0].id) : '');
const activityUnitId = ref('');
const activityCycleId = ref('');
const activityCommodityId = ref(
    props.commodities[0]?.id ? String(props.commodities[0].id) : '',
);
const activityStatus = ref('completed');
const activityEvidenceVisibility = ref('private');
const formKey = ref(0);

const selectedActivityFarm = computed(() =>
    props.farms.find((farm) => String(farm.id) === activityFarmId.value),
);

const filteredActivities = computed(() =>
    props.latestActivities.filter(
        (a) =>
            farmFilter.value === 'all' || String(a.farmId) === farmFilter.value,
    ),
);

const bumpFormKey = () => {
    formKey.value++;
    drawerOpen.value = false;
};

watch(
    () => props.farms.map((farm) => farm.id),
    () => {
        const farmIds = props.farms.map((farm) => String(farm.id));
        const fallback = farmIds[0] ?? '';

        if (!farmIds.includes(activityFarmId.value)) {
            activityFarmId.value = fallback;
            activityUnitId.value = '';
            activityCycleId.value = '';
        }
    },
    { immediate: true },
);

watch(
    () => props.commodities.map((commodity) => commodity.id),
    () => {
        const ids = props.commodities.map((c) => String(c.id));

        if (!ids.includes(activityCommodityId.value)) {
            activityCommodityId.value = ids[0] ?? '';
        }
    },
    { immediate: true },
);

watch(activityFarmId, () => {
    activityUnitId.value = '';
    activityCycleId.value = '';
});
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Head title="Field Diary" />

        <PageHero
            eyebrow="Operations Journal"
            title="Field diary"
            description="Daily activities, input costs, photo evidence, and next-action tracking across all farms."
        >
            <template #actions>
                <Link
                    v-if="
                        permissions.canManageFarmOperations && currentTeamSlug
                    "
                    :href="activityExports(currentTeamSlug)"
                    class="inline-flex h-10 items-center gap-2 rounded-lg border border-white/25 bg-white/10 px-4 text-sm font-medium text-white hover:bg-white/20"
                >
                    <Download class="size-4" />
                    Export XLSX
                </Link>
                <Button
                    v-if="
                        permissions.canManageFieldWork &&
                        currentTeamSlug &&
                        farms.length > 0
                    "
                    type="button"
                    class="bg-white text-[#1b4d3e] hover:bg-emerald-50"
                    @click="drawerOpen = true"
                >
                    <Plus class="mr-1.5 size-4" />
                    Record activity
                </Button>
            </template>
        </PageHero>

        <div class="flex flex-wrap items-center gap-2">
            <select
                v-model="farmFilter"
                class="h-9 rounded-md border bg-card px-3 text-sm"
            >
                <option value="all">All farms</option>
                <option
                    v-for="farm in farms"
                    :key="farm.id"
                    :value="String(farm.id)"
                >
                    {{ farm.name }}
                </option>
            </select>
        </div>

        <section v-if="filteredActivities.length > 0" class="grid gap-3">
            <article
                v-for="activity in filteredActivities"
                :key="activity.id"
                class="space-y-3 rounded-2xl border bg-card p-4 text-sm shadow-xs"
            >
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="font-semibold">
                            {{ activity.farmName }} /
                            {{ activity.activityType }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ activity.activityDate }}
                            <span v-if="activity.productionUnitName">
                                · {{ activity.productionUnitName }}
                            </span>
                            <span v-if="activity.commodityName">
                                · {{ activity.commodityName }}
                            </span>
                        </p>
                    </div>
                    <Badge variant="secondary">{{
                        activity.statusLabel
                    }}</Badge>
                </div>
                <p>{{ activity.description }}</p>
                <div class="flex flex-wrap gap-4 text-xs text-muted-foreground">
                    <span v-if="activity.cost">
                        Cost: {{ formatCurrency(activity.cost, currency) }}
                    </span>
                    <span v-if="activity.nextActivity">
                        Next: {{ activity.nextActivity }}
                    </span>
                    <span v-if="activity.recordedBy">
                        Recorded by {{ activity.recordedBy }}
                    </span>
                </div>
                <div
                    v-if="activity.evidence.length > 0"
                    class="flex flex-wrap gap-2"
                >
                    <a
                        v-for="evidence in activity.evidence"
                        :key="evidence.id"
                        :href="evidence.downloadUrl"
                        class="rounded-md border px-2.5 py-1 text-xs font-medium text-primary hover:bg-accent"
                    >
                        {{ evidence.fileName }}
                    </a>
                </div>
            </article>
        </section>

        <EmptyState
            v-else
            title="No field diary entries yet"
            description="Record daily farm operations, costs, next actions, and photo evidence."
            :icon="ClipboardList"
        />

        <SlideOverForm
            :open="drawerOpen"
            title="Record field activity"
            description="Log work performed with optional cost, next action, and private evidence"
            wide
            @update:open="drawerOpen = $event"
        >
            <Form
                v-if="farms.length > 0 && currentTeamSlug"
                :key="formKey"
                v-bind="storeFarmActivity.form(currentTeamSlug)"
                class="grid gap-4 sm:grid-cols-2"
                v-slot="{ errors, processing, progress }"
                @success="bumpFormKey"
            >
                <div class="grid gap-1.5">
                    <Label for="activity-farm">Farm</Label>
                    <select
                        id="activity-farm"
                        v-model="activityFarmId"
                        name="farm_id"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
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
                    <Label for="activity-date">Date</Label>
                    <Input
                        id="activity-date"
                        name="activity_date"
                        type="date"
                        required
                    />
                    <InputError :message="errors.activity_date" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="activity-type">Activity type</Label>
                    <Input id="activity-type" name="activity_type" required />
                    <InputError :message="errors.activity_type" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="activity-status">Status</Label>
                    <Select v-model="activityStatus" name="status">
                        <SelectTrigger id="activity-status" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in options.activityStatuses"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="activity-unit">Production unit</Label>
                    <select
                        id="activity-unit"
                        v-model="activityUnitId"
                        name="production_unit_id"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">None</option>
                        <option
                            v-for="unit in selectedActivityFarm?.productionUnits ??
                            []"
                            :key="unit.id"
                            :value="String(unit.id)"
                        >
                            {{ unit.name }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="activity-cycle">Production cycle</Label>
                    <select
                        id="activity-cycle"
                        v-model="activityCycleId"
                        name="production_cycle_id"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">None</option>
                        <option
                            v-for="cycle in selectedActivityFarm?.productionCycles ??
                            []"
                            :key="cycle.id"
                            :value="String(cycle.id)"
                        >
                            {{ cycle.name }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="activity-commodity">Commodity</Label>
                    <select
                        id="activity-commodity"
                        v-model="activityCommodityId"
                        name="commodity_id"
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
                </div>
                <div class="grid gap-1.5">
                    <Label for="activity-cost">Cost</Label>
                    <Input
                        id="activity-cost"
                        name="cost"
                        type="number"
                        min="0"
                        step="0.01"
                    />
                </div>
                <div class="grid gap-1.5 sm:col-span-2">
                    <Label for="activity-next">Next activity</Label>
                    <Input id="activity-next" name="next_activity" />
                </div>
                <div class="grid gap-1.5 sm:col-span-2">
                    <Label for="activity-description">Description</Label>
                    <textarea
                        id="activity-description"
                        name="description"
                        class="min-h-24 rounded-md border bg-background px-3 py-2 text-sm"
                        required
                    />
                    <InputError :message="errors.description" />
                </div>
                <div v-if="investorMode" class="grid gap-1.5 sm:col-span-2">
                    <Label for="activity-summary">Investor-safe summary</Label>
                    <Input id="activity-summary" name="investor_safe_summary" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="activity-evidence">Evidence</Label>
                    <Input
                        id="activity-evidence"
                        name="evidence[]"
                        type="file"
                        multiple
                        accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx"
                    />
                </div>
                <div v-if="investorMode" class="grid gap-1.5">
                    <Label for="activity-evidence-visibility">
                        Evidence visibility
                    </Label>
                    <Select
                        v-model="activityEvidenceVisibility"
                        name="evidence_visibility"
                    >
                        <SelectTrigger
                            id="activity-evidence-visibility"
                            class="w-full"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in options.evidenceVisibilities"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <input
                    v-else
                    type="hidden"
                    name="evidence_visibility"
                    value="private"
                />
                <progress
                    v-if="progress"
                    :value="progress.percentage"
                    max="100"
                    class="h-2 w-full sm:col-span-2"
                />
                <div class="sm:col-span-2">
                    <Button type="submit" :disabled="processing">
                        Record activity
                    </Button>
                </div>
            </Form>
        </SlideOverForm>
    </div>
</template>
