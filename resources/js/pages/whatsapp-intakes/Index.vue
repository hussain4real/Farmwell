<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { MessageSquare, Plus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import EmptyState from '@/components/farm/EmptyState.vue';
import EvidenceGallery from '@/components/farm/EvidenceGallery.vue';
import PageHero from '@/components/farm/PageHero.vue';
import SlideOverForm from '@/components/farm/SlideOverForm.vue';
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
const investorMode = computed(
    () => page.props.currentTeamFeatures?.investorMode === true,
);
const drawerOpen = ref(false);
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

const intakesWithPhotosCount = computed(
    () =>
        props.pendingIntakes.filter((intake) =>
            intake.evidence.some((file) => Boolean(file.previewUrl)),
        ).length,
);

const bumpFormKey = () => {
    formKey.value++;
    drawerOpen.value = false;
};

watch(
    () => props.farms.map((farm) => farm.id),
    () => {
        const ids = props.farms.map((farm) => String(farm.id));

        if (!ids.includes(whatsappFarmId.value)) {
            whatsappFarmId.value = ids[0] ?? '';
            whatsappUnitId.value = '';
            whatsappCycleId.value = '';
        }
    },
    { immediate: true },
);

watch(
    () => props.commodities.map((commodity) => commodity.id),
    () => {
        const ids = props.commodities.map((c) => String(c.id));

        if (!ids.includes(whatsappCommodityId.value)) {
            whatsappCommodityId.value = ids[0] ?? '';
        }
    },
    { immediate: true },
);

watch(whatsappFarmId, () => {
    whatsappUnitId.value = '';
    whatsappCycleId.value = '';
});
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Head title="WhatsApp Intake" />

        <PageHero
            eyebrow="Field Message Triage"
            title="WhatsApp intake"
            description="Stage forwarded field updates from WhatsApp, review photos, and convert them into official diary entries."
        >
            <template #actions>
                <Button
                    v-if="
                        permissions.canManageFarmOperations && currentTeamSlug
                    "
                    type="button"
                    class="bg-white text-[#1b4d3e] hover:bg-emerald-50"
                    @click="drawerOpen = true"
                >
                    <Plus class="mr-1.5 size-4" />
                    Queue intake
                </Button>
            </template>
            <template #metrics>
                <div class="px-4 py-3 sm:px-6">
                    <p class="text-xs text-emerald-100/75">Pending review</p>
                    <p class="mt-0.5 text-lg font-semibold tabular-nums">
                        {{ pendingIntakes.length }}
                    </p>
                </div>
                <div class="border-s border-white/15 px-4 py-3 sm:px-6">
                    <p class="text-xs text-emerald-100/75">With photos</p>
                    <p class="mt-0.5 text-lg font-semibold tabular-nums">
                        {{ intakesWithPhotosCount }}
                    </p>
                </div>
            </template>
        </PageHero>

        <section v-if="pendingIntakes.length > 0" class="grid gap-4">
            <article
                v-for="intake in pendingIntakes"
                :key="intake.id"
                class="space-y-4 rounded-2xl border bg-card p-5 text-sm shadow-xs"
            >
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="font-semibold">
                            {{ intake.sourceSender ?? 'WhatsApp update' }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ intake.farmName ?? 'No farm selected' }}
                            <span v-if="intake.sourceDate">
                                · {{ intake.sourceDate }}
                            </span>
                        </p>
                    </div>
                    <Badge variant="secondary">
                        {{ intake.reviewStatusLabel }}
                    </Badge>
                </div>
                <p class="rounded-xl bg-muted/40 p-3">
                    {{ intake.sourceMessage }}
                </p>
                <EvidenceGallery
                    v-if="intake.evidence.length > 0"
                    :items="intake.evidence"
                    dialog-title="WhatsApp intake photos"
                />

                <Form
                    v-if="
                        permissions.canManageFarmOperations && currentTeamSlug
                    "
                    v-bind="
                        convertWhatsappIntake.form([currentTeamSlug, intake.id])
                    "
                    class="grid gap-3 md:grid-cols-2"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-1.5">
                        <Label :for="`convert-farm-${intake.id}`">Farm</Label>
                        <select
                            :id="`convert-farm-${intake.id}`"
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
                    </div>
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
                    <div class="grid gap-1.5">
                        <Label :for="`convert-date-${intake.id}`"
                            >Activity date</Label
                        >
                        <Input
                            :id="`convert-date-${intake.id}`"
                            name="activity_date"
                            type="date"
                            :value="
                                intake.normalizedActivityDate ??
                                intake.sourceDate ??
                                ''
                            "
                            required
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label :for="`convert-type-${intake.id}`"
                            >Activity type</Label
                        >
                        <Input
                            :id="`convert-type-${intake.id}`"
                            name="activity_type"
                            :value="intake.normalizedActivityType ?? ''"
                            placeholder="Activity type"
                            required
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label :for="`convert-cost-${intake.id}`">Cost</Label>
                        <Input
                            :id="`convert-cost-${intake.id}`"
                            name="cost"
                            type="number"
                            min="0"
                            step="0.01"
                            :value="intake.normalizedCost ?? ''"
                            placeholder="Cost"
                        />
                    </div>
                    <div class="grid gap-1.5 md:col-span-2">
                        <Label :for="`convert-description-${intake.id}`"
                            >Description</Label
                        >
                        <textarea
                            :id="`convert-description-${intake.id}`"
                            name="description"
                            class="min-h-20 rounded-md border bg-background px-3 py-2 text-sm"
                            required
                            :value="
                                intake.normalizedDescription ??
                                intake.sourceMessage
                            "
                        />
                    </div>
                    <div class="grid gap-1.5">
                        <Label :for="`convert-next-${intake.id}`"
                            >Next activity</Label
                        >
                        <Input
                            :id="`convert-next-${intake.id}`"
                            name="next_activity"
                            :value="intake.normalizedNextActivity ?? ''"
                            placeholder="Next activity"
                        />
                    </div>
                    <div v-if="investorMode" class="grid gap-1.5">
                        <Label :for="`convert-investor-summary-${intake.id}`"
                            >Investor-safe summary</Label
                        >
                        <Input
                            :id="`convert-investor-summary-${intake.id}`"
                            name="investor_safe_summary"
                            :value="intake.normalizedInvestorSafeSummary ?? ''"
                            placeholder="Investor-safe summary"
                        />
                    </div>
                    <input type="hidden" name="status" value="completed" />
                    <div class="md:col-span-2">
                        <Button
                            type="submit"
                            :disabled="processing || farms.length === 0"
                        >
                            Convert to diary entry
                        </Button>
                    </div>
                </Form>

                <details
                    v-if="
                        permissions.canManageFarmOperations && currentTeamSlug
                    "
                    class="border-t pt-3"
                >
                    <summary
                        class="cursor-pointer text-xs font-medium text-muted-foreground hover:text-foreground"
                    >
                        Reject intake
                    </summary>
                    <Form
                        v-bind="
                            rejectWhatsappIntake.form([
                                currentTeamSlug,
                                intake.id,
                            ])
                        "
                        class="mt-3 grid gap-2 md:grid-cols-[1fr_auto]"
                        v-slot="{ errors, processing }"
                    >
                        <Input
                            name="rejection_reason"
                            aria-label="Rejection reason"
                            placeholder="Rejection reason"
                            required
                        />
                        <Button
                            type="submit"
                            variant="outline"
                            :disabled="processing"
                        >
                            Confirm reject
                        </Button>
                        <InputError :message="errors.rejection_reason" />
                    </Form>
                </details>
            </article>
        </section>

        <EmptyState
            v-else
            title="No pending WhatsApp intake"
            description="Forwarded messages from farm workers will appear here for review."
            :icon="MessageSquare"
        />

        <SlideOverForm
            :open="drawerOpen"
            title="Queue WhatsApp intake"
            description="Stage a forwarded update and optional photo attachments"
            wide
            @update:open="drawerOpen = $event"
        >
            <Form
                v-if="currentTeamSlug"
                :key="formKey"
                v-bind="storeWhatsappIntake.form(currentTeamSlug)"
                class="grid gap-4 sm:grid-cols-2"
                v-slot="{ errors, processing, progress }"
                @success="bumpFormKey"
            >
                <div class="grid gap-1.5 sm:col-span-2">
                    <Label for="whatsapp-message">Original message</Label>
                    <textarea
                        id="whatsapp-message"
                        name="source_message"
                        class="min-h-24 rounded-md border bg-background px-3 py-2 text-sm"
                        required
                    />
                    <InputError :message="errors.source_message" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="whatsapp-sender">Sender</Label>
                    <Input id="whatsapp-sender" name="source_sender" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="whatsapp-date">Source date</Label>
                    <Input id="whatsapp-date" name="source_date" type="date" />
                </div>
                <div class="grid gap-1.5">
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
                </div>
                <div class="grid gap-1.5">
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
                </div>
                <div class="grid gap-1.5">
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
                </div>
                <div class="grid gap-1.5">
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
                </div>
                <div class="grid gap-1.5">
                    <Label for="whatsapp-evidence">Attachments</Label>
                    <Input
                        id="whatsapp-evidence"
                        name="evidence[]"
                        type="file"
                        multiple
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="whatsapp-caption">Caption</Label>
                    <Input id="whatsapp-caption" name="evidence_caption" />
                </div>
                <progress
                    v-if="progress"
                    :value="progress.percentage"
                    max="100"
                    class="h-2 w-full sm:col-span-2"
                />
                <div class="sm:col-span-2">
                    <Button type="submit" :disabled="processing">
                        Queue intake
                    </Button>
                </div>
            </Form>
        </SlideOverForm>
    </div>
</template>
