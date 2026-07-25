<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { ClipboardList, FileUp } from 'lucide-vue-next';
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

const bumpFormKey = () => {
    formKey.value++;
};

const syncFarmSelections = () => {
    const farmIds = props.farms.map((farm) => String(farm.id));
    const fallbackFarmId = farmIds[0] ?? '';

    if (!farmIds.includes(activityFarmId.value)) {
        activityFarmId.value = fallbackFarmId;
        activityUnitId.value = '';
        activityCycleId.value = '';
    }
};

const syncCommoditySelections = () => {
    const commodityIds = props.commodities.map((commodity) =>
        String(commodity.id),
    );
    const fallbackCommodityId = commodityIds[0] ?? '';

    if (!commodityIds.includes(activityCommodityId.value)) {
        activityCommodityId.value = fallbackCommodityId;
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
watch(activityFarmId, () => {
    activityUnitId.value = '';
    activityCycleId.value = '';
});
</script>

<template>
    <Head title="Field Diary" />

    <div class="flex flex-1 flex-col gap-8 p-4 md:p-6">
        <Heading
            title="Field diary"
            description="Activities, evidence, costs, next actions, and activity history"
        />

        <section
            v-if="permissions.canManageFarmOperations && currentTeamSlug"
            class="space-y-4 rounded-lg border p-4"
        >
            <div class="flex items-center gap-2">
                <FileUp class="size-4" />
                <Heading
                    variant="small"
                    title="Record activity"
                    description="Diary entry with cost, next action, and private evidence"
                />
            </div>

            <Form
                v-if="farms.length > 0"
                :key="formKey"
                v-bind="storeFarmActivity.form(currentTeamSlug)"
                class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
                v-slot="{ errors, processing, progress }"
                @success="bumpFormKey"
            >
                <div class="grid gap-2">
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
                    <InputError :message="errors.farm_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="activity-date">Date</Label>
                    <Input
                        id="activity-date"
                        name="activity_date"
                        type="date"
                        required
                    />
                    <InputError :message="errors.activity_date" />
                </div>
                <div class="grid gap-2">
                    <Label for="activity-type">Activity type</Label>
                    <Input id="activity-type" name="activity_type" required />
                    <InputError :message="errors.activity_type" />
                </div>
                <div class="grid gap-2">
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
                    <InputError :message="errors.status" />
                </div>
                <div class="grid gap-2">
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
                    <InputError :message="errors.production_unit_id" />
                </div>
                <div class="grid gap-2">
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
                    <InputError :message="errors.production_cycle_id" />
                </div>
                <div class="grid gap-2">
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
                    <InputError :message="errors.commodity_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="activity-cost">Cost</Label>
                    <Input
                        id="activity-cost"
                        name="cost"
                        type="number"
                        min="0"
                        step="0.01"
                    />
                    <InputError :message="errors.cost" />
                </div>
                <div class="grid gap-2">
                    <Label for="activity-next">Next activity</Label>
                    <Input id="activity-next" name="next_activity" />
                    <InputError :message="errors.next_activity" />
                </div>
                <div class="grid gap-2 md:col-span-2 xl:col-span-3">
                    <Label for="activity-description">Description</Label>
                    <textarea
                        id="activity-description"
                        name="description"
                        class="min-h-24 rounded-md border bg-background px-3 py-2 text-sm"
                        required
                    ></textarea>
                    <InputError :message="errors.description" />
                </div>
                <div class="grid gap-2 md:col-span-2 xl:col-span-3">
                    <Label for="activity-summary">
                        Investor-safe summary
                    </Label>
                    <Input id="activity-summary" name="investor_safe_summary" />
                    <InputError :message="errors.investor_safe_summary" />
                </div>
                <div class="grid gap-2">
                    <Label for="activity-evidence">Evidence</Label>
                    <Input
                        id="activity-evidence"
                        name="evidence[]"
                        type="file"
                        multiple
                    />
                    <InputError :message="errors.evidence" />
                    <InputError :message="errors['evidence.0']" />
                </div>
                <div class="grid gap-2">
                    <Label for="activity-evidence-caption">Caption</Label>
                    <Input
                        id="activity-evidence-caption"
                        name="evidence_caption"
                    />
                    <InputError :message="errors.evidence_caption" />
                </div>
                <div class="grid gap-2">
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
                    <InputError :message="errors.evidence_visibility" />
                </div>
                <progress
                    v-if="progress"
                    :value="progress.percentage"
                    max="100"
                    class="h-2 w-full md:col-span-2 xl:col-span-3"
                />
                <div class="md:col-span-2 xl:col-span-3">
                    <Button type="submit" :disabled="processing">
                        Record activity
                    </Button>
                </div>
            </Form>

            <p v-else class="text-sm text-muted-foreground">
                No farm records yet.
            </p>
        </section>

        <section class="space-y-4">
            <div class="flex items-center gap-2">
                <ClipboardList class="size-4" />
                <Heading
                    variant="small"
                    title="Activity history"
                    description="Costed field activity and private evidence"
                />
            </div>

            <div v-if="latestActivities.length > 0" class="grid gap-3">
                <article
                    v-for="activity in latestActivities"
                    :key="activity.id"
                    class="space-y-3 rounded-lg border p-4 text-sm"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-2"
                    >
                        <div>
                            <p class="font-medium">
                                {{ activity.farmName }} /
                                {{ activity.activityType }}
                            </p>
                            <p class="text-muted-foreground">
                                {{ activity.activityDate }}
                                <span v-if="activity.productionUnitName">
                                    / {{ activity.productionUnitName }}
                                </span>
                                <span v-if="activity.commodityName">
                                    / {{ activity.commodityName }}
                                </span>
                            </p>
                        </div>
                        <Badge variant="secondary">
                            {{ activity.statusLabel }}
                        </Badge>
                    </div>
                    <p>{{ activity.description }}</p>
                    <div class="grid gap-2 text-muted-foreground">
                        <p v-if="activity.cost">Cost: {{ activity.cost }}</p>
                        <p v-if="activity.nextActivity">
                            Next: {{ activity.nextActivity }}
                        </p>
                        <p v-if="activity.recordedBy">
                            Recorded by {{ activity.recordedBy }}
                        </p>
                    </div>
                    <div
                        v-if="activity.evidence.length > 0"
                        class="flex flex-wrap gap-2"
                    >
                        <a
                            v-for="evidence in activity.evidence"
                            :key="evidence.id"
                            :href="evidence.downloadUrl"
                            class="rounded-md border px-2 py-1 text-xs text-primary"
                        >
                            {{ evidence.fileName }}
                        </a>
                    </div>
                </article>
            </div>
            <p v-else class="text-sm text-muted-foreground">
                No activity entries yet.
            </p>
        </section>
    </div>
</template>
