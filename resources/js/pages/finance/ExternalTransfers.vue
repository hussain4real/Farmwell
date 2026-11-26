<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { Landmark, Plus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import EmptyState from '@/components/farm/EmptyState.vue';
import EvidenceGallery from '@/components/farm/EvidenceGallery.vue';
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
    index as externalTransfersIndex,
    store as storeExternalTransfer,
} from '@/routes/finance/external-transfers';
import { store as storeReconciliation } from '@/routes/finance/external-transfers/reconciliations';
import type {
    Budget,
    Expense,
    ExternalTransfer,
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
    fundingPhases: FundingPhase[];
    expenses: Expense[];
    investorAgreements: FinanceInvestorAgreement[];
    externalTransfers: ExternalTransfer[];
    options: Pick<
        FinanceOptions,
        | 'externalTransferDirections'
        | 'externalTransferStatuses'
        | 'investorVisibilityStatuses'
        | 'transferReconciliationStatuses'
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
                title: 'External Transfers',
                href: props.currentTeam
                    ? externalTransfersIndex(props.currentTeam.slug)
                    : '/',
            },
        ],
    }),
});

const currentTeam = computed(() => page.props.currentTeam as Team | null);
const currentTeamSlug = computed(() => currentTeam.value?.slug ?? '');
const drawerOpen = ref(false);
const farmFilter = ref('all');
const directionFilter = ref('all');
const statusFilter = ref('all');
const transferFarmId = ref(props.farms[0]?.id ? String(props.farms[0].id) : '');
const transferCycleId = ref('');
const transferBudgetId = ref('');
const transferFundingPhaseId = ref('');
const transferExpenseId = ref('');
const transferInvestorAgreementId = ref('');
const transferDirection = ref('incoming');
const transferStatus = ref('recorded');
const transferInvestorVisibilityStatus = ref('private');
const formKey = ref(0);

const selectedFarm = computed(() =>
    props.farms.find((farm) => String(farm.id) === transferFarmId.value),
);

const farmBudgets = computed(() =>
    props.budgets.filter(
        (budget) => String(budget.farmId) === transferFarmId.value,
    ),
);

const farmFundingPhases = computed(() =>
    props.fundingPhases.filter(
        (phase) => String(phase.farmId) === transferFarmId.value,
    ),
);

const farmExpenses = computed(() =>
    props.expenses.filter(
        (expense) => String(expense.farmId) === transferFarmId.value,
    ),
);

const farmInvestorAgreements = computed(() =>
    props.investorAgreements.filter(
        (agreement) =>
            String(agreement.farmId) === transferFarmId.value &&
            (!transferCycleId.value ||
                agreement.productionCycleId === null ||
                String(agreement.productionCycleId) === transferCycleId.value),
    ),
);

const filteredTransfers = computed(() =>
    props.externalTransfers.filter(
        (transfer) =>
            (farmFilter.value === 'all' ||
                String(transfer.farmId) === farmFilter.value) &&
            (directionFilter.value === 'all' ||
                transfer.direction === directionFilter.value) &&
            (statusFilter.value === 'all' ||
                transfer.status === statusFilter.value),
    ),
);

const incomingTotal = computed(() => {
    const minor = props.externalTransfers
        .filter((t) => t.direction === 'incoming')
        .reduce((sum, t) => sum + (t.amountMinor ?? 0), 0);

    return formatCurrency((minor / 100).toFixed(2), props.currency);
});

const outgoingTotal = computed(() => {
    const minor = props.externalTransfers
        .filter((t) => t.direction === 'outgoing')
        .reduce((sum, t) => sum + (t.amountMinor ?? 0), 0);

    return formatCurrency((minor / 100).toFixed(2), props.currency);
});

const handleCreated = () => {
    formKey.value++;
    drawerOpen.value = false;
};

watch(transferFarmId, () => {
    transferCycleId.value = '';
    transferBudgetId.value = '';
    transferFundingPhaseId.value = '';
    transferExpenseId.value = '';
    transferInvestorAgreementId.value = '';
});

watch(transferCycleId, () => {
    transferInvestorAgreementId.value = '';
});
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Head title="Finance / External Transfers" />

        <PageHero
            eyebrow="Farm Accounting"
            title="External transfers"
            description="Recorded bank/mobile releases, transfer proof, references, and reconciliation history."
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
                    Record transfer
                </Button>
            </template>
            <template #metrics>
                <div class="px-4 py-3 sm:px-6">
                    <p class="text-xs text-emerald-100/75">Transfers</p>
                    <p class="mt-0.5 text-lg font-semibold tabular-nums">
                        {{ externalTransfers.length }}
                    </p>
                </div>
                <div class="border-s border-white/15 px-4 py-3 sm:px-6">
                    <p class="text-xs text-emerald-100/75">Incoming</p>
                    <p
                        class="mt-0.5 text-base font-semibold whitespace-nowrap tabular-nums sm:text-lg"
                    >
                        {{ incomingTotal }}
                    </p>
                </div>
                <div
                    class="col-span-2 border-t border-white/15 px-4 py-3 sm:col-span-2 sm:border-s sm:border-t-0 sm:px-6"
                >
                    <p class="text-xs text-emerald-100/75">Outgoing</p>
                    <p
                        class="mt-0.5 text-base font-semibold whitespace-nowrap tabular-nums sm:text-lg"
                    >
                        {{ outgoingTotal }}
                    </p>
                </div>
            </template>
        </PageHero>

        <div class="flex flex-wrap items-center gap-2">
            <select
                v-model="farmFilter"
                aria-label="Filter transfers by farm"
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
                v-model="directionFilter"
                aria-label="Filter transfers by direction"
                class="h-9 rounded-md border bg-card px-3 text-sm"
            >
                <option value="all">All directions</option>
                <option
                    v-for="option in options.externalTransferDirections"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </select>
            <select
                v-model="statusFilter"
                aria-label="Filter transfers by status"
                class="h-9 rounded-md border bg-card px-3 text-sm"
            >
                <option value="all">All statuses</option>
                <option
                    v-for="option in options.externalTransferStatuses"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </select>
        </div>

        <section class="space-y-4">
            <div class="flex items-center gap-2">
                <Landmark class="size-4 text-primary" />
                <Heading
                    variant="small"
                    title="External transfer register"
                    description="Proof and reconciliation history"
                />
            </div>

            <div v-if="filteredTransfers.length > 0" class="space-y-4">
                <article
                    v-for="transfer in filteredTransfers"
                    :key="transfer.id"
                    class="space-y-4 rounded-2xl border bg-card p-5 text-sm shadow-xs"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div class="min-w-0">
                            <h2 class="text-base font-semibold">
                                {{ transfer.directionLabel }} /
                                {{ transfer.transferType }}
                            </h2>
                            <p class="text-xs text-muted-foreground">
                                {{ transfer.farmName }}
                                <span v-if="transfer.productionCycleName">
                                    · {{ transfer.productionCycleName }}
                                </span>
                                ·
                                <span class="whitespace-nowrap">{{
                                    transfer.transferredOn
                                }}</span>
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <Badge variant="outline" class="tabular-nums">
                                {{
                                    formatCurrency(
                                        transfer.amount,
                                        transfer.currency,
                                    )
                                }}
                            </Badge>
                            <Badge variant="secondary">
                                {{ transfer.statusLabel }}
                            </Badge>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <Badge v-if="transfer.investorAgreementTitle">
                            {{ transfer.investorAgreementTitle }}
                        </Badge>
                        <Badge variant="outline">
                            {{ transfer.investorVisibilityStatusLabel }}
                        </Badge>
                    </div>
                    <dl
                        class="grid gap-3 rounded-xl bg-muted/40 p-3.5 sm:grid-cols-2"
                    >
                        <div class="min-w-0">
                            <dt class="text-xs text-muted-foreground">
                                Counterparty
                            </dt>
                            <dd class="mt-0.5 font-medium break-words">
                                {{
                                    transfer.counterpartyName ??
                                    'No counterparty'
                                }}
                            </dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-xs text-muted-foreground">
                                Reference
                            </dt>
                            <dd class="mt-0.5 font-medium break-words">
                                {{ transfer.reference ?? 'No reference' }}
                            </dd>
                        </div>
                    </dl>
                    <EvidenceGallery
                        v-if="transfer.proof.length"
                        :items="transfer.proof"
                        compact
                        dialog-title="Transfer proof"
                    />

                    <div
                        v-if="transfer.reconciliations.length"
                        class="space-y-2"
                    >
                        <div
                            v-for="reconciliation in transfer.reconciliations"
                            :key="reconciliation.id"
                            class="flex flex-wrap items-center justify-between gap-2 rounded-xl border bg-muted/20 p-3"
                        >
                            <p class="font-medium tabular-nums">
                                {{ reconciliation.statusLabel }} ·
                                {{
                                    formatCurrency(
                                        reconciliation.reconciledAmount,
                                        transfer.currency,
                                    )
                                }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ reconciliation.reconciledAt }}
                            </p>
                        </div>
                    </div>

                    <details
                        v-if="permissions.canManageFinance && currentTeamSlug"
                        class="border-t pt-3"
                    >
                        <summary
                            class="cursor-pointer text-xs font-medium text-primary hover:underline"
                        >
                            Reconcile transfer
                        </summary>
                        <Form
                            v-bind="
                                storeReconciliation.form({
                                    current_team: currentTeamSlug,
                                    external_transfer: transfer.id,
                                })
                            "
                            class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4"
                            v-slot="{ errors, processing }"
                        >
                            <div class="grid gap-1.5">
                                <Label :for="`reconcile-status-${transfer.id}`">
                                    Reconciliation
                                </Label>
                                <select
                                    :id="`reconcile-status-${transfer.id}`"
                                    name="status"
                                    class="h-9 rounded-md border bg-background px-3 text-sm"
                                >
                                    <option
                                        v-for="option in options.transferReconciliationStatuses"
                                        :key="option.value"
                                        :value="option.value"
                                    >
                                        {{ option.label }}
                                    </option>
                                </select>
                                <InputError :message="errors.status" />
                            </div>
                            <div class="grid gap-1.5">
                                <Label :for="`reconcile-amount-${transfer.id}`">
                                    Amount
                                </Label>
                                <Input
                                    :id="`reconcile-amount-${transfer.id}`"
                                    name="reconciled_amount"
                                    inputmode="decimal"
                                    :value="transfer.amount"
                                    required
                                />
                                <InputError
                                    :message="errors.reconciled_amount"
                                />
                            </div>
                            <div class="grid gap-1.5">
                                <Label :for="`reconcile-at-${transfer.id}`">
                                    Reconciled at
                                </Label>
                                <Input
                                    :id="`reconcile-at-${transfer.id}`"
                                    name="reconciled_at"
                                    type="datetime-local"
                                />
                                <InputError :message="errors.reconciled_at" />
                            </div>
                            <div class="flex items-end">
                                <Button
                                    :disabled="processing"
                                    class="w-full sm:w-auto"
                                >
                                    Reconcile
                                </Button>
                            </div>
                        </Form>
                    </details>
                </article>
            </div>
            <EmptyState
                v-else
                title="No external transfers yet"
                description="Record incoming investor funding or outgoing disbursements alongside bank transfer proof."
                :icon="Landmark"
            />
        </section>

        <SlideOverForm
            :open="drawerOpen"
            title="Record external transfer"
            description="External tracking only: no wallet, escrow, collection, or disbursement."
            wide
            @update:open="drawerOpen = $event"
        >
            <Form
                v-if="farms.length > 0 && currentTeamSlug"
                :key="formKey"
                v-bind="storeExternalTransfer.form(currentTeamSlug)"
                class="grid gap-4 sm:grid-cols-2"
                v-slot="{ errors, processing, progress }"
                @success="handleCreated"
            >
                <div class="grid gap-1.5">
                    <Label for="transfer-farm">Farm</Label>
                    <select
                        id="transfer-farm"
                        v-model="transferFarmId"
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
                    <Label for="transfer-cycle">Cycle</Label>
                    <select
                        id="transfer-cycle"
                        v-model="transferCycleId"
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
                    <Label for="transfer-direction">Direction</Label>
                    <select
                        id="transfer-direction"
                        v-model="transferDirection"
                        name="direction"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                        required
                    >
                        <option
                            v-for="option in options.externalTransferDirections"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <InputError :message="errors.direction" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="transfer-status">Status</Label>
                    <select
                        id="transfer-status"
                        v-model="transferStatus"
                        name="status"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option
                            v-for="option in options.externalTransferStatuses"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <InputError :message="errors.status" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="transfer-budget">Budget</Label>
                    <select
                        id="transfer-budget"
                        v-model="transferBudgetId"
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
                    <Label for="transfer-phase">Funding phase</Label>
                    <select
                        id="transfer-phase"
                        v-model="transferFundingPhaseId"
                        name="funding_phase_id"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">No phase</option>
                        <option
                            v-for="phase in farmFundingPhases"
                            :key="phase.id"
                            :value="String(phase.id)"
                        >
                            {{ phase.name }}
                        </option>
                    </select>
                    <InputError :message="errors.funding_phase_id" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="transfer-expense">Expense</Label>
                    <select
                        id="transfer-expense"
                        v-model="transferExpenseId"
                        name="expense_id"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">No expense</option>
                        <option
                            v-for="expense in farmExpenses"
                            :key="expense.id"
                            :value="String(expense.id)"
                        >
                            {{ expense.expenseCategoryName }} /
                            {{
                                formatCurrency(expense.amount, expense.currency)
                            }}
                        </option>
                    </select>
                    <InputError :message="errors.expense_id" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="transfer-investor-agreement">
                        Investor agreement
                    </Label>
                    <select
                        id="transfer-investor-agreement"
                        v-model="transferInvestorAgreementId"
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
                    <Label for="transfer-investor-visibility">
                        Investor visibility
                    </Label>
                    <select
                        id="transfer-investor-visibility"
                        v-model="transferInvestorVisibilityStatus"
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
                    <Label for="transfer-type">Type</Label>
                    <Input
                        id="transfer-type"
                        name="transfer_type"
                        value="recorded release"
                        required
                    />
                    <InputError :message="errors.transfer_type" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="transfer-amount">Amount ({{ currency }})</Label>
                    <Input
                        id="transfer-amount"
                        name="amount"
                        inputmode="decimal"
                        required
                    />
                    <InputError :message="errors.amount" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="transfer-date">Transferred on</Label>
                    <Input
                        id="transfer-date"
                        name="transferred_on"
                        type="date"
                        required
                    />
                    <InputError :message="errors.transferred_on" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="transfer-counterparty">Counterparty</Label>
                    <Input
                        id="transfer-counterparty"
                        name="counterparty_name"
                    />
                    <InputError :message="errors.counterparty_name" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="transfer-reference">Reference</Label>
                    <Input id="transfer-reference" name="reference" />
                    <InputError :message="errors.reference" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="proof-caption">Proof caption</Label>
                    <Input id="proof-caption" name="proof_caption" />
                    <InputError :message="errors.proof_caption" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="transfer-proof">Proof</Label>
                    <Input id="transfer-proof" name="proof" type="file" />
                    <InputError :message="errors.proof" />
                    <progress
                        v-if="progress"
                        :value="progress.percentage"
                        max="100"
                        class="h-1 w-full"
                    />
                </div>
                <div class="sm:col-span-2">
                    <Button :disabled="processing" class="w-full sm:w-auto">
                        Record transfer
                    </Button>
                </div>
            </Form>
            <p v-else class="text-sm text-muted-foreground">
                Create a farm before adding external transfers.
            </p>
        </SlideOverForm>
    </div>
</template>
