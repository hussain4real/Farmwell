<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { MessageSquare } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import {
    convert as convertWhatsappIntake,
    index as whatsappIntakesIndex,
    reject as rejectWhatsappIntake,
    store as storeWhatsappIntake,
} from '@/routes/whatsapp-intakes';
import type {
    Commodity,
    Farm,
    FarmOperationOptions,
    FarmOperationPermissions,
    Team,
    WhatsappIntake,
} from '@/types';

type Props = {
    permissions: FarmOperationPermissions;
    farms: Farm[];
    commodities: Commodity[];
    pendingIntakes: WhatsappIntake[];
    options: Pick<FarmOperationOptions, 'whatsappIntakeStatuses'>;
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
                title: 'WhatsApp Intake',
                href: props.currentTeam
                    ? whatsappIntakesIndex(props.currentTeam.slug)
                    : '/',
            },
        ],
    }),
});

const currentTeam = computed(() => page.props.currentTeam as Team | null);
const currentTeamSlug = computed(() => currentTeam.value?.slug ?? '');
const whatsappFarmId = ref(props.farms[0]?.id ? String(props.farms[0].id) : '');
const whatsappUnitId = ref('');
const whatsappCycleId = ref('');
const whatsappCommodityId = ref(
    props.commodities[0]?.id ? String(props.commodities[0].id) : '',
);
const formKey = ref(0);

const selectedWhatsappFarm = computed(() =>
    props.farms.find((farm) => String(farm.id) === whatsappFarmId.value),
);

const bumpFormKey = () => {
    formKey.value++;
};

const syncFarmSelections = () => {
    const farmIds = props.farms.map((farm) => String(farm.id));
    const fallbackFarmId = farmIds[0] ?? '';

    if (!farmIds.includes(whatsappFarmId.value)) {
        whatsappFarmId.value = fallbackFarmId;
        whatsappUnitId.value = '';
        whatsappCycleId.value = '';
    }
};

const syncCommoditySelections = () => {
    const commodityIds = props.commodities.map((commodity) =>
        String(commodity.id),
    );
    const fallbackCommodityId = commodityIds[0] ?? '';

    if (!commodityIds.includes(whatsappCommodityId.value)) {
        whatsappCommodityId.value = fallbackCommodityId;
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
watch(whatsappFarmId, () => {
    whatsappUnitId.value = '';
    whatsappCycleId.value = '';
});
</script>

<template>
    <Head title="WhatsApp Intake" />

    <div class="flex flex-1 flex-col gap-8 p-4 md:p-6">
        <Heading
            title="WhatsApp intake"
            description="Pending review, conversion, rejection, and staged evidence"
        />

        <section
            v-if="permissions.canManageFarmOperations && currentTeamSlug"
            class="space-y-4 rounded-lg border p-4"
        >
            <div class="flex items-center gap-2">
                <MessageSquare class="size-4" />
                <Heading
                    variant="small"
                    title="Queue intake"
                    description="Forwarded update staged for review"
                />
            </div>

            <Form
                :key="formKey"
                v-bind="storeWhatsappIntake.form(currentTeamSlug)"
                class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
                v-slot="{ errors, processing, progress }"
                @success="bumpFormKey"
            >
                <div class="grid gap-2 md:col-span-2 xl:col-span-3">
                    <Label for="whatsapp-message">Original message</Label>
                    <textarea
                        id="whatsapp-message"
                        name="source_message"
                        class="min-h-28 rounded-md border bg-background px-3 py-2 text-sm"
                        required
                    ></textarea>
                    <InputError :message="errors.source_message" />
                </div>
                <div class="grid gap-2">
                    <Label for="whatsapp-sender">Sender</Label>
                    <Input id="whatsapp-sender" name="source_sender" />
                    <InputError :message="errors.source_sender" />
                </div>
                <div class="grid gap-2">
                    <Label for="whatsapp-date">Source date</Label>
                    <Input id="whatsapp-date" name="source_date" type="date" />
                    <InputError :message="errors.source_date" />
                </div>
                <div class="grid gap-2">
                    <Label for="whatsapp-farm">Farm</Label>
                    <select
                        id="whatsapp-farm"
                        v-model="whatsappFarmId"
                        name="farm_id"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">None</option>
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
                    <Label for="whatsapp-unit">Production unit</Label>
                    <select
                        id="whatsapp-unit"
                        v-model="whatsappUnitId"
                        name="production_unit_id"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">None</option>
                        <option
                            v-for="unit in selectedWhatsappFarm?.productionUnits ??
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
                    <Label for="whatsapp-cycle">Production cycle</Label>
                    <select
                        id="whatsapp-cycle"
                        v-model="whatsappCycleId"
                        name="production_cycle_id"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">None</option>
                        <option
                            v-for="cycle in selectedWhatsappFarm?.productionCycles ??
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
                    <Label for="whatsapp-commodity">Commodity</Label>
                    <select
                        id="whatsapp-commodity"
                        v-model="whatsappCommodityId"
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
                    <Label for="whatsapp-evidence">Attachments</Label>
                    <Input
                        id="whatsapp-evidence"
                        name="evidence[]"
                        type="file"
                        multiple
                    />
                    <InputError :message="errors.evidence" />
                    <InputError :message="errors['evidence.0']" />
                </div>
                <div class="grid gap-2">
                    <Label for="whatsapp-caption">Caption</Label>
                    <Input id="whatsapp-caption" name="evidence_caption" />
                    <InputError :message="errors.evidence_caption" />
                </div>
                <progress
                    v-if="progress"
                    :value="progress.percentage"
                    max="100"
                    class="h-2 w-full md:col-span-2 xl:col-span-3"
                />
                <div class="md:col-span-2 xl:col-span-3">
                    <Button type="submit" :disabled="processing">
                        Queue intake
                    </Button>
                </div>
            </Form>
        </section>

        <section class="space-y-4">
            <div class="flex items-center gap-2">
                <MessageSquare class="size-4" />
                <Heading
                    variant="small"
                    title="Review queue"
                    description="Convert or reject forwarded WhatsApp updates"
                />
            </div>

            <div v-if="pendingIntakes.length > 0" class="grid gap-3">
                <article
                    v-for="intake in pendingIntakes"
                    :key="intake.id"
                    class="space-y-4 rounded-lg border p-4 text-sm"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-2"
                    >
                        <div>
                            <p class="font-medium">
                                {{ intake.sourceSender ?? 'WhatsApp update' }}
                            </p>
                            <p class="text-muted-foreground">
                                {{ intake.farmName ?? 'No farm selected' }}
                                <span v-if="intake.sourceDate">
                                    / {{ intake.sourceDate }}
                                </span>
                            </p>
                        </div>
                        <Badge variant="secondary">
                            {{ intake.reviewStatusLabel }}
                        </Badge>
                    </div>
                    <p>{{ intake.sourceMessage }}</p>
                    <div
                        v-if="intake.evidence.length > 0"
                        class="flex flex-wrap gap-2"
                    >
                        <a
                            v-for="evidence in intake.evidence"
                            :key="evidence.id"
                            :href="evidence.downloadUrl"
                            class="rounded-md border px-2 py-1 text-xs text-primary"
                        >
                            {{ evidence.fileName }}
                        </a>
                    </div>

                    <Form
                        v-if="
                            permissions.canManageFarmOperations &&
                            currentTeamSlug
                        "
                        v-bind="
                            convertWhatsappIntake.form([
                                currentTeamSlug,
                                intake.id,
                            ])
                        "
                        class="grid gap-3 md:grid-cols-2"
                        v-slot="{ errors, processing }"
                    >
                        <select
                            name="farm_id"
                            class="h-9 rounded-md border bg-background px-3 text-sm"
                            required
                        >
                            <option value="">Select farm</option>
                            <option
                                v-for="farm in farms"
                                :key="farm.id"
                                :value="farm.id"
                                :selected="farm.id === intake.farmId"
                            >
                                {{ farm.name }}
                            </option>
                        </select>
                        <InputError :message="errors.farm_id" />
                        <input
                            type="hidden"
                            name="production_unit_id"
                            :value="intake.productionUnitId ?? ''"
                        />
                        <input
                            type="hidden"
                            name="production_cycle_id"
                            :value="intake.productionCycleId ?? ''"
                        />
                        <input
                            type="hidden"
                            name="commodity_id"
                            :value="intake.commodityId ?? ''"
                        />
                        <Input
                            name="activity_date"
                            type="date"
                            :value="
                                intake.normalizedActivityDate ??
                                intake.sourceDate ??
                                ''
                            "
                            required
                        />
                        <InputError :message="errors.activity_date" />
                        <Input
                            name="activity_type"
                            :value="intake.normalizedActivityType ?? ''"
                            placeholder="Activity type"
                            required
                        />
                        <InputError :message="errors.activity_type" />
                        <textarea
                            name="description"
                            class="min-h-24 rounded-md border bg-background px-3 py-2 text-sm md:col-span-2"
                            required
                            :value="
                                intake.normalizedDescription ??
                                intake.sourceMessage
                            "
                        ></textarea>
                        <InputError :message="errors.description" />
                        <Input
                            name="cost"
                            type="number"
                            min="0"
                            step="0.01"
                            :value="intake.normalizedCost ?? ''"
                            placeholder="Cost"
                        />
                        <InputError :message="errors.cost" />
                        <Input
                            name="next_activity"
                            :value="intake.normalizedNextActivity ?? ''"
                            placeholder="Next activity"
                        />
                        <InputError :message="errors.next_activity" />
                        <Input
                            name="investor_safe_summary"
                            :value="intake.normalizedInvestorSafeSummary ?? ''"
                            placeholder="Investor-safe summary"
                        />
                        <InputError :message="errors.investor_safe_summary" />
                        <input type="hidden" name="status" value="completed" />
                        <div class="md:col-span-2">
                            <Button
                                type="submit"
                                :disabled="processing || farms.length === 0"
                            >
                                Convert
                            </Button>
                        </div>
                    </Form>

                    <Form
                        v-if="
                            permissions.canManageFarmOperations &&
                            currentTeamSlug
                        "
                        v-bind="
                            rejectWhatsappIntake.form([
                                currentTeamSlug,
                                intake.id,
                            ])
                        "
                        class="grid gap-2 md:grid-cols-[1fr_auto]"
                        v-slot="{ errors, processing }"
                    >
                        <Input
                            name="rejection_reason"
                            placeholder="Rejection reason"
                            required
                        />
                        <Button
                            type="submit"
                            variant="outline"
                            :disabled="processing"
                        >
                            Reject
                        </Button>
                        <InputError :message="errors.rejection_reason" />
                    </Form>
                </article>
            </div>
            <p v-else class="text-sm text-muted-foreground">
                No pending WhatsApp intake.
            </p>
        </section>
    </div>
</template>
