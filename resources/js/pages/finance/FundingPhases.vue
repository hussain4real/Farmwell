<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { Banknote, CircleDollarSign, Plus, ReceiptText } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import EmptyState from '@/components/farm/EmptyState.vue';
import EvidenceGallery from '@/components/farm/EvidenceGallery.vue';
import MetricCard from '@/components/farm/MetricCard.vue';
import PageHero from '@/components/farm/PageHero.vue';
import SlideOverForm from '@/components/farm/SlideOverForm.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCurrency } from '@/lib/currency';
import { dashboard } from '@/routes';
import { index as financeIndex } from '@/routes/finance';
import {
    index as fundingPhasesIndex,
    release as releaseFundingPhase,
    store as storeFundingPhase,
    submit as submitFundingPhase,
    update as updateFundingPhase,
} from '@/routes/finance/funding-phases';
import type {
    Budget,
    CarryForward,
    FinanceFarm,
    FinanceInvestorAgreement,
    FinanceOptions,
    FinancePermissions,
    FundingPhase,
    Team,
} from '@/types';

type Props = {
    permissions: FinancePermissions;
    currency: string;
    farms: FinanceFarm[];
    budgets: Budget[];
    investorAgreements: FinanceInvestorAgreement[];
    fundingPhases: FundingPhase[];
    carryForward: CarryForward;
    options: Pick<
        FinanceOptions,
        'fundingPhaseStatuses' | 'investorVisibilityStatuses'
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
                title: 'Finance',
                href: props.currentTeam
                    ? financeIndex(props.currentTeam.slug)
                    : '/',
            },
            {
                title: 'Funding Phases',
                href: props.currentTeam
                    ? fundingPhasesIndex(props.currentTeam.slug)
                    : '/',
            },
        ],
    }),
});

const currentTeam = computed(() => page.props.currentTeam as Team | null);
const currentTeamSlug = computed(() => currentTeam.value?.slug ?? '');
const drawerOpen = ref(false);
const requestedEditId = new URLSearchParams(page.url.split('?')[1]).get('edit');
const editingRecord = ref<FundingPhase | null>(
    props.permissions.canManageFinance
        ? (props.fundingPhases.find(
              (phase) =>
                  phase.canEditRequest && String(phase.id) === requestedEditId,
          ) ?? null)
        : null,
);
const farmFilter = ref('all');
const statusFilter = ref('all');
const phaseFarmId = ref(props.farms[0]?.id ? String(props.farms[0].id) : '');
const phaseCycleId = ref('');
const phaseBudgetId = ref('');
const phaseInvestorAgreementId = ref('');
const phaseStatus = ref('draft');
const phaseInvestorVisibilityStatus = ref('private');
const formKey = ref(0);

watch(phaseInvestorAgreementId, (id) => {
    if (id) {
        phaseStatus.value = 'requested';
    }
});

const selectedFarm = computed(() =>
    props.farms.find((farm) => String(farm.id) === phaseFarmId.value),
);

const farmBudgets = computed(() =>
    props.budgets.filter(
        (budget) => String(budget.farmId) === phaseFarmId.value,
    ),
);

const farmInvestorAgreements = computed(() =>
    props.investorAgreements.filter(
        (agreement) =>
            String(agreement.farmId) === phaseFarmId.value &&
            (!phaseCycleId.value ||
                agreement.productionCycleId === null ||
                String(agreement.productionCycleId) === phaseCycleId.value),
    ),
);

const filteredPhases = computed(() =>
    props.fundingPhases.filter(
        (phase) =>
            (farmFilter.value === 'all' ||
                String(phase.farmId) === farmFilter.value) &&
            (statusFilter.value === 'all' ||
                phase.status === statusFilter.value),
    ),
);

const handleCreated = () => {
    formKey.value++;
    drawerOpen.value = false;
};

watch(phaseFarmId, () => {
    phaseCycleId.value = '';
    phaseBudgetId.value = '';
    phaseInvestorAgreementId.value = '';
});

watch(phaseCycleId, () => {
    phaseInvestorAgreementId.value = '';
});
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Head title="Finance / Funding Phases" />

        <PageHero
            eyebrow="Farm Accounting"
            title="Funding phases"
            description="Recorded releases, phase milestones, investor approval requests, and carry-forward balances."
        >
            <template #actions>
                <Button
                    v-if="
                        permissions.canManageFinance &&
                        currentTeamSlug &&
                        farms.length > 0
                    "
                    type="button"
                    class="bg-white text-[#1b4d3e] hover:bg-emerald-50"
                    @click="drawerOpen = true"
                >
                    <Plus class="mr-1.5 size-4" />
                    Create phase
                </Button>
            </template>
        </PageHero>

        <section class="grid gap-3 sm:grid-cols-3">
            <MetricCard
                label="Released"
                :value="formatCurrency(carryForward.released, currency)"
                :icon="Banknote"
                tone="emerald"
            />
            <MetricCard
                label="Spent"
                :value="formatCurrency(carryForward.spent, currency)"
                :icon="ReceiptText"
                tone="amber"
            />
            <MetricCard
                label="Carry-forward"
                :value="formatCurrency(carryForward.carryForward, currency)"
                :icon="CircleDollarSign"
                tone="cyan"
            />
        </section>

        <div class="flex flex-wrap items-center gap-2">
            <select
                v-model="farmFilter"
                aria-label="Filter phases by farm"
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
            <select
                v-model="statusFilter"
                aria-label="Filter phases by status"
                class="h-9 rounded-md border bg-card px-3 text-sm"
            >
                <option value="all">All statuses</option>
                <option
                    v-for="option in options.fundingPhaseStatuses"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </select>
        </div>

        <section class="space-y-4">
            <div class="flex items-center gap-2">
                <Banknote class="size-4 text-primary" />
                <Heading
                    variant="small"
                    title="Funding phase register"
                    description="Phase totals, milestone proof, and investor release status"
                />
            </div>

            <div
                v-if="filteredPhases.length > 0"
                class="grid gap-4 xl:grid-cols-2"
            >
                <article
                    v-for="phase in filteredPhases"
                    :key="phase.id"
                    class="space-y-4 rounded-2xl border bg-card p-5 text-sm shadow-xs"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div class="min-w-0">
                            <h2 class="text-base font-semibold">
                                {{ phase.name }}
                            </h2>
                            <p class="text-xs text-muted-foreground">
                                {{ phase.farmName }}
                                <span v-if="phase.productionCycleName">
                                    · {{ phase.productionCycleName }}
                                </span>
                                <span v-if="phase.milestone">
                                    · {{ phase.milestone }}
                                </span>
                            </p>
                        </div>
                        <Badge variant="secondary">
                            {{ phase.statusLabel }}
                        </Badge>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <Badge v-if="phase.investorAgreementTitle">
                            {{ phase.investorAgreementTitle }}
                        </Badge>
                        <Badge variant="outline">
                            {{ phase.investorVisibilityStatusLabel }}
                        </Badge>
                    </div>
                    <dl
                        class="grid grid-cols-2 gap-3 rounded-xl bg-muted/40 p-3.5"
                    >
                        <div class="min-w-0">
                            <dt class="text-xs text-muted-foreground">
                                Planned
                            </dt>
                            <dd
                                class="mt-0.5 font-semibold whitespace-nowrap tabular-nums"
                            >
                                {{
                                    formatCurrency(
                                        phase.plannedAmount,
                                        phase.currency,
                                    )
                                }}
                            </dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-xs text-muted-foreground">
                                Requested
                            </dt>
                            <dd
                                class="mt-0.5 font-semibold whitespace-nowrap tabular-nums"
                            >
                                {{
                                    formatCurrency(
                                        phase.requestedAmount,
                                        phase.currency,
                                    )
                                }}
                            </dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-xs text-muted-foreground">
                                Approved
                            </dt>
                            <dd
                                class="mt-0.5 font-semibold whitespace-nowrap tabular-nums"
                            >
                                {{
                                    formatCurrency(
                                        phase.approvedAmount,
                                        phase.currency,
                                    )
                                }}
                            </dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-xs text-muted-foreground">
                                Released
                            </dt>
                            <dd
                                class="mt-0.5 font-semibold whitespace-nowrap tabular-nums"
                            >
                                {{
                                    formatCurrency(
                                        phase.externallyReleasedAmount,
                                        phase.currency,
                                    )
                                }}
                            </dd>
                        </div>
                    </dl>
                    <div
                        v-if="phase.milestoneEvidence?.length"
                        class="space-y-2"
                    >
                        <p class="text-xs font-medium text-muted-foreground">
                            Milestone evidence
                        </p>
                        <EvidenceGallery
                            :items="phase.milestoneEvidence"
                            compact
                            dialog-title="Milestone evidence"
                        />
                    </div>
                    <Button
                        v-if="
                            permissions.canManageFinance &&
                            currentTeamSlug &&
                            phase.canEditRequest
                        "
                        type="button"
                        variant="outline"
                        class="min-h-12"
                        @click="editingRecord = phase"
                    >
                        Edit funding request
                    </Button>
                    <Form
                        v-if="
                            permissions.canManageFinance &&
                            phase.status === 'requested' &&
                            phase.investorAgreementId &&
                            !phase.fundingApprovalExists
                        "
                        v-bind="
                            submitFundingPhase.form([currentTeamSlug, phase.id])
                        "
                        class="grid gap-3 rounded-xl border bg-muted/25 p-3.5"
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid gap-1.5">
                            <Label :for="`phase-evidence-${phase.id}`">
                                Milestone evidence
                            </Label>
                            <Input
                                :id="`phase-evidence-${phase.id}`"
                                name="milestone_evidence[]"
                                type="file"
                                multiple
                            />
                            <InputError :message="errors.milestone_evidence" />
                        </div>
                        <Button :disabled="processing" class="w-full sm:w-auto">
                            Submit for investor approval
                        </Button>
                    </Form>
                    <Form
                        v-if="
                            permissions.canManageFinance &&
                            phase.status === 'approved' &&
                            phase.investorAgreementId
                        "
                        v-bind="
                            releaseFundingPhase.form([
                                currentTeamSlug,
                                phase.id,
                            ])
                        "
                        class="grid gap-3 rounded-xl border bg-muted/25 p-3.5 sm:grid-cols-2"
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid gap-1.5">
                            <Label :for="`release-amount-${phase.id}`">
                                Recorded release ({{ phase.currency }})
                            </Label>
                            <Input
                                :id="`release-amount-${phase.id}`"
                                name="amount"
                                inputmode="decimal"
                                required
                            />
                            <InputError :message="errors.amount" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label :for="`release-date-${phase.id}`">
                                Released on
                            </Label>
                            <Input
                                :id="`release-date-${phase.id}`"
                                name="released_on"
                                type="date"
                                required
                            />
                            <InputError :message="errors.released_on" />
                        </div>
                        <div class="space-y-2 sm:col-span-2">
                            <p class="text-xs text-muted-foreground">
                                Recording a release automatically updates the
                                linked agreement's recorded funding.
                            </p>
                            <Button
                                :disabled="processing"
                                class="w-full sm:w-auto"
                            >
                                Record release
                            </Button>
                        </div>
                    </Form>
                </article>
            </div>
            <EmptyState
                v-else
                title="No funding phases yet"
                description="Create a funding phase to schedule milestones, request investor approvals, and track releases."
                :icon="Banknote"
            />
        </section>

        <SlideOverForm
            v-if="editingRecord && currentTeamSlug"
            :open="true"
            title="Edit funding request"
            :description="editingRecord.name"
            @update:open="if (!$event) editingRecord = null;"
        >
            <Form
                :key="editingRecord.id"
                v-bind="
                    updateFundingPhase.form([currentTeamSlug, editingRecord.id])
                "
                class="grid gap-4"
                aria-label="Edit funding request"
                :options="{ preserveScroll: true }"
                v-slot="{ errors, processing }"
                @success="editingRecord = null"
            >
                <p class="text-sm text-muted-foreground">
                    The investor will see the updated request.
                </p>
                <div aria-live="polite" class="grid gap-4">
                    <div class="grid gap-2">
                        <Label for="fundingPhase-edit-name">Request name</Label>
                        <Input
                            id="fundingPhase-edit-name"
                            name="name"
                            :model-value="editingRecord.name"
                            required
                            maxlength="255"
                            :aria-invalid="Boolean(errors.name)"
                            aria-describedby="fundingPhase-edit-name-error"
                            class="min-h-12 text-base"
                        />
                        <InputError
                            id="fundingPhase-edit-name-error"
                            :message="errors.name"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="fundingPhase-edit-requested-amount"
                            >Requested amount ({{
                                editingRecord.currency
                            }})</Label
                        >
                        <Input
                            id="fundingPhase-edit-requested-amount"
                            name="requested_amount"
                            :model-value="editingRecord.requestedAmount"
                            required
                            inputmode="decimal"
                            :aria-invalid="Boolean(errors.requested_amount)"
                            aria-describedby="fundingPhase-edit-requested-amount-error"
                            class="min-h-12 text-base"
                        />
                        <InputError
                            id="fundingPhase-edit-requested-amount-error"
                            :message="errors.requested_amount"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="fundingPhase-edit-milestone"
                            >Milestone</Label
                        >
                        <Input
                            id="fundingPhase-edit-milestone"
                            name="milestone"
                            :model-value="editingRecord.milestone ?? ''"
                            maxlength="255"
                            :aria-invalid="Boolean(errors.milestone)"
                            aria-describedby="fundingPhase-edit-milestone-error"
                            class="min-h-12 text-base"
                        />
                        <InputError
                            id="fundingPhase-edit-milestone-error"
                            :message="errors.milestone"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="fundingPhase-edit-expected-on"
                            >Expected on</Label
                        >
                        <Input
                            id="fundingPhase-edit-expected-on"
                            name="expected_on"
                            :model-value="editingRecord.expectedOn ?? ''"
                            type="date"
                            :aria-invalid="Boolean(errors.expected_on)"
                            aria-describedby="fundingPhase-edit-expected-on-error"
                            class="min-h-12 text-base"
                        />
                        <InputError
                            id="fundingPhase-edit-expected-on-error"
                            :message="errors.expected_on"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="fundingPhase-edit-notes">Notes</Label>
                        <textarea
                            id="fundingPhase-edit-notes"
                            name="notes"
                            :value="editingRecord.notes ?? ''"
                            maxlength="4000"
                            :aria-invalid="Boolean(errors.notes)"
                            aria-describedby="fundingPhase-edit-notes-error"
                            class="rounded-md border bg-background px-3 py-2 text-base"
                            rows="3"
                        ></textarea>
                        <InputError
                            id="fundingPhase-edit-notes-error"
                            :message="errors.notes"
                        />
                    </div>
                </div>
                <Button type="submit" class="min-h-12" :disabled="processing">
                    Save changes
                </Button>
            </Form>
        </SlideOverForm>

        <SlideOverForm
            :open="drawerOpen"
            title="Create funding phase"
            description="External tracking only: recorded release and reconciliation support."
            wide
            @update:open="drawerOpen = $event"
        >
            <Form
                v-if="farms.length > 0 && currentTeamSlug"
                :key="formKey"
                v-bind="storeFundingPhase.form(currentTeamSlug)"
                class="grid gap-4 sm:grid-cols-2"
                v-slot="{ errors, processing }"
                @success="handleCreated"
            >
                <div class="grid gap-1.5">
                    <Label for="phase-farm">Farm</Label>
                    <select
                        id="phase-farm"
                        v-model="phaseFarmId"
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
                <div class="grid gap-1.5">
                    <Label for="phase-cycle">Cycle</Label>
                    <select
                        id="phase-cycle"
                        v-model="phaseCycleId"
                        name="production_cycle_id"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">Farm-level</option>
                        <option
                            v-for="cycle in selectedFarm?.productionCycles ??
                            []"
                            :key="cycle.id"
                            :value="String(cycle.id)"
                        >
                            {{ cycle.name }}
                        </option>
                    </select>
                    <InputError :message="errors.production_cycle_id" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="phase-budget">Budget</Label>
                    <select
                        id="phase-budget"
                        v-model="phaseBudgetId"
                        name="budget_id"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">No budget</option>
                        <option
                            v-for="budget in farmBudgets"
                            :key="budget.id"
                            :value="String(budget.id)"
                        >
                            {{ budget.name }}
                        </option>
                    </select>
                    <InputError :message="errors.budget_id" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="phase-status">Status</Label>
                    <select
                        id="phase-status"
                        v-model="phaseStatus"
                        name="status"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option
                            v-for="option in options.fundingPhaseStatuses.filter(
                                (item) =>
                                    !phaseInvestorAgreementId ||
                                    item.value === 'requested',
                            )"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <InputError :message="errors.status" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="phase-investor-agreement">
                        Investor agreement
                    </Label>
                    <select
                        id="phase-investor-agreement"
                        v-model="phaseInvestorAgreementId"
                        name="investor_agreement_id"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">Not linked</option>
                        <option
                            v-for="agreement in farmInvestorAgreements"
                            :key="agreement.id"
                            :value="String(agreement.id)"
                        >
                            {{ agreement.title }} / {{ agreement.investorName }}
                        </option>
                    </select>
                    <InputError :message="errors.investor_agreement_id" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="phase-investor-visibility">
                        Investor visibility
                    </Label>
                    <select
                        id="phase-investor-visibility"
                        v-model="phaseInvestorVisibilityStatus"
                        name="investor_visibility_status"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option
                            v-for="option in options.investorVisibilityStatuses"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <InputError :message="errors.investor_visibility_status" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="phase-name">Name</Label>
                    <Input id="phase-name" name="name" required />
                    <InputError :message="errors.name" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="phase-milestone">Milestone</Label>
                    <Input id="phase-milestone" name="milestone" />
                    <InputError :message="errors.milestone" />
                </div>
                <div class="grid gap-1.5 sm:col-span-2">
                    <Label for="milestone-evidence">Milestone evidence</Label>
                    <Input
                        id="milestone-evidence"
                        name="milestone_evidence[]"
                        type="file"
                        multiple
                    />
                    <InputError :message="errors.milestone_evidence" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="phase-planned">
                        Planned amount ({{ currency }})
                    </Label>
                    <Input
                        id="phase-planned"
                        name="planned_amount"
                        inputmode="decimal"
                    />
                    <InputError :message="errors.planned_amount" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="phase-requested">
                        Requested amount ({{ currency }})
                    </Label>
                    <Input
                        id="phase-requested"
                        name="requested_amount"
                        inputmode="decimal"
                    />
                    <InputError :message="errors.requested_amount" />
                </div>
                <div v-if="!phaseInvestorAgreementId" class="grid gap-1.5">
                    <Label for="phase-approved">
                        Approved amount ({{ currency }})
                    </Label>
                    <Input
                        id="phase-approved"
                        name="approved_amount"
                        inputmode="decimal"
                    />
                    <InputError :message="errors.approved_amount" />
                </div>
                <div v-if="!phaseInvestorAgreementId" class="grid gap-1.5">
                    <Label for="phase-released">
                        Recorded release ({{ currency }})
                    </Label>
                    <Input
                        id="phase-released"
                        name="externally_released_amount"
                        inputmode="decimal"
                    />
                    <InputError :message="errors.externally_released_amount" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="phase-expected">Expected on</Label>
                    <Input id="phase-expected" name="expected_on" type="date" />
                    <InputError :message="errors.expected_on" />
                </div>
                <div v-if="!phaseInvestorAgreementId" class="grid gap-1.5">
                    <Label for="phase-released-on">Released on</Label>
                    <Input
                        id="phase-released-on"
                        name="released_on"
                        type="date"
                    />
                    <InputError :message="errors.released_on" />
                </div>
                <div class="sm:col-span-2">
                    <Button :disabled="processing" class="w-full sm:w-auto">
                        Create phase
                    </Button>
                </div>
            </Form>
            <p v-else class="text-sm text-muted-foreground">
                Create a farm before adding funding phases.
            </p>
        </SlideOverForm>
    </div>
</template>
