<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { FileUp, Landmark } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    externalTransfers: ExternalTransfer[];
    options: Pick<
        FinanceOptions,
        | 'externalTransferDirections'
        | 'externalTransferStatuses'
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
const transferFarmId = ref(props.farms[0]?.id ? String(props.farms[0].id) : '');
const transferCycleId = ref('');
const transferBudgetId = ref('');
const transferFundingPhaseId = ref('');
const transferExpenseId = ref('');
const transferDirection = ref('incoming');
const transferStatus = ref('recorded');
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

watch(transferFarmId, () => {
    transferCycleId.value = '';
    transferBudgetId.value = '';
    transferFundingPhaseId.value = '';
    transferExpenseId.value = '';
});
</script>

<template>
    <Head title="Finance / External Transfers" />

    <div class="flex flex-1 flex-col gap-8 p-4 md:p-6">
        <Heading
            title="External transfers"
            description="Recorded releases, transfer proof, references, and reconciliation"
        />

        <section
            v-if="permissions.canManageFinance && currentTeamSlug"
            class="space-y-4 rounded-lg border p-4"
        >
            <div class="flex items-center gap-2">
                <FileUp class="size-4" />
                <Heading
                    variant="small"
                    title="Record external transfer"
                    description="External tracking only: no wallet, escrow, collection, or disbursement"
                />
            </div>

            <Form
                v-if="farms.length > 0"
                :key="formKey"
                v-bind="storeExternalTransfer.form(currentTeamSlug)"
                class="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
                v-slot="{ errors, processing, progress }"
                @success="formKey++"
            >
                <div class="grid gap-2">
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
                <div class="grid gap-2">
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
                <div class="grid gap-2">
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
                <div class="grid gap-2">
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
                <div class="grid gap-2">
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
                <div class="grid gap-2">
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
                <div class="grid gap-2">
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
                            {{ expense.amount }}
                        </option>
                    </select>
                    <InputError :message="errors.expense_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="transfer-type">Type</Label>
                    <Input
                        id="transfer-type"
                        name="transfer_type"
                        value="recorded release"
                        required
                    />
                    <InputError :message="errors.transfer_type" />
                </div>
                <div class="grid gap-2">
                    <Label for="transfer-amount">Amount ({{ currency }})</Label>
                    <Input
                        id="transfer-amount"
                        name="amount"
                        inputmode="decimal"
                        required
                    />
                    <InputError :message="errors.amount" />
                </div>
                <div class="grid gap-2">
                    <Label for="transfer-date">Transferred on</Label>
                    <Input
                        id="transfer-date"
                        name="transferred_on"
                        type="date"
                        required
                    />
                    <InputError :message="errors.transferred_on" />
                </div>
                <div class="grid gap-2">
                    <Label for="transfer-counterparty">Counterparty</Label>
                    <Input
                        id="transfer-counterparty"
                        name="counterparty_name"
                    />
                    <InputError :message="errors.counterparty_name" />
                </div>
                <div class="grid gap-2">
                    <Label for="transfer-reference">Reference</Label>
                    <Input id="transfer-reference" name="reference" />
                    <InputError :message="errors.reference" />
                </div>
                <div class="grid gap-2">
                    <Label for="proof-caption">Proof caption</Label>
                    <Input id="proof-caption" name="proof_caption" />
                    <InputError :message="errors.proof_caption" />
                </div>
                <div class="grid gap-2">
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
                <div class="flex items-end">
                    <Button :disabled="processing">Record transfer</Button>
                </div>
            </Form>
        </section>

        <section class="space-y-4">
            <div class="flex items-center gap-2">
                <Landmark class="size-4" />
                <Heading
                    variant="small"
                    title="External transfer register"
                    description="Proof and reconciliation history"
                />
            </div>

            <div v-if="externalTransfers.length > 0" class="space-y-4">
                <article
                    v-for="transfer in externalTransfers"
                    :key="transfer.id"
                    class="space-y-4 rounded-lg border p-4 text-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="font-medium">
                                {{ transfer.directionLabel }} /
                                {{ transfer.transferType }}
                            </h3>
                            <p class="text-muted-foreground">
                                {{ transfer.farmName }}
                                <span v-if="transfer.productionCycleName">
                                    / {{ transfer.productionCycleName }}
                                </span>
                            </p>
                        </div>
                        <Badge variant="secondary">
                            {{ transfer.statusLabel }}
                        </Badge>
                    </div>
                    <div class="grid gap-2 md:grid-cols-4">
                        <p>{{ transfer.amount }} {{ transfer.currency }}</p>
                        <p>{{ transfer.transferredOn }}</p>
                        <p>
                            {{ transfer.counterpartyName ?? 'No counterparty' }}
                        </p>
                        <p>{{ transfer.reference ?? 'No reference' }}</p>
                    </div>
                    <div
                        v-if="transfer.proof.length"
                        class="flex flex-wrap gap-2"
                    >
                        <a
                            v-for="proof in transfer.proof"
                            :key="proof.id"
                            :href="proof.downloadUrl"
                            class="underline underline-offset-4"
                        >
                            {{ proof.fileName }}
                        </a>
                    </div>

                    <div
                        v-if="transfer.reconciliations.length"
                        class="space-y-2"
                    >
                        <div
                            v-for="reconciliation in transfer.reconciliations"
                            :key="reconciliation.id"
                            class="rounded-lg border p-3"
                        >
                            <p class="font-medium">
                                {{ reconciliation.statusLabel }} /
                                {{ reconciliation.reconciledAmount }}
                            </p>
                            <p class="text-muted-foreground">
                                {{ reconciliation.reconciledAt }}
                            </p>
                        </div>
                    </div>

                    <Form
                        v-if="permissions.canManageFinance && currentTeamSlug"
                        v-bind="
                            storeReconciliation.form({
                                current_team: currentTeamSlug,
                                external_transfer: transfer.id,
                            })
                        "
                        class="grid gap-3 md:grid-cols-4"
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid gap-2">
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
                        <div class="grid gap-2">
                            <Label :for="`reconcile-amount-${transfer.id}`">
                                Amount
                            </Label>
                            <Input
                                :id="`reconcile-amount-${transfer.id}`"
                                name="reconciled_amount"
                                :value="transfer.amount"
                                required
                            />
                            <InputError :message="errors.reconciled_amount" />
                        </div>
                        <div class="grid gap-2">
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
                            <Button :disabled="processing">Reconcile</Button>
                        </div>
                    </Form>
                </article>
            </div>
            <p v-else class="text-sm text-muted-foreground">
                No external transfers recorded yet.
            </p>
        </section>
    </div>
</template>
