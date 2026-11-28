<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { Plus, ReceiptText } from 'lucide-vue-next';
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
    index as expensesIndex,
    store as storeExpense,
} from '@/routes/finance/expenses';
import type {
    Budget,
    BudgetLine,
    Expense,
    ExpenseCategory,
    FinanceActivity,
    FinanceFarm,
    FinanceInvestorAgreement,
    FinanceOptions,
    FinancePermissions,
    FinanceVariance,
    FinanceVarianceCategory,
    FundingPhase,
    Team,
} from '@/types';

type Props = {
    permissions: FinancePermissions;
    currency: string;
    farms: FinanceFarm[];
    categories: ExpenseCategory[];
    budgets: Budget[];
    budgetLines: BudgetLine[];
    fundingPhases: FundingPhase[];
    investorAgreements: FinanceInvestorAgreement[];
    activities: FinanceActivity[];
    expenses: Expense[];
    variance: FinanceVariance;
    options: Pick<
        FinanceOptions,
        'expenseStatuses' | 'investorVisibilityStatuses'
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
                title: 'Expenses',
                href: props.currentTeam
                    ? expensesIndex(props.currentTeam.slug)
                    : '/',
            },
        ],
    }),
});

const currentTeam = computed(() => page.props.currentTeam as Team | null);
const currentTeamSlug = computed(() => currentTeam.value?.slug ?? '');
const drawerOpen = ref(false);
const farmFilter = ref('all');
const categoryFilter = ref('all');
const statusFilter = ref('all');
const expenseFarmId = ref(props.farms[0]?.id ? String(props.farms[0].id) : '');
const expenseCycleId = ref('');
const expenseBudgetId = ref('');
const expenseBudgetLineId = ref('');
const expenseFundingPhaseId = ref('');
const expenseInvestorAgreementId = ref('');
const expenseCategoryId = ref(
    props.categories[0]?.id ? String(props.categories[0].id) : '',
);
const expenseActivityId = ref('');
const expenseStatus = ref('approved');
const expenseInvestorVisibilityStatus = ref('private');
const formKey = ref(0);

const selectedFarm = computed(() =>
    props.farms.find((farm) => String(farm.id) === expenseFarmId.value),
);

const farmBudgets = computed(() =>
    props.budgets.filter(
        (budget) => String(budget.farmId) === expenseFarmId.value,
    ),
);

const selectedBudgetLines = computed(() =>
    props.budgetLines.filter(
        (line) =>
            !expenseBudgetId.value ||
            String(line.budgetId) === expenseBudgetId.value,
    ),
);

const farmFundingPhases = computed(() =>
    props.fundingPhases.filter(
        (phase) => String(phase.farmId) === expenseFarmId.value,
    ),
);

const farmInvestorAgreements = computed(() =>
    props.investorAgreements.filter(
        (agreement) =>
            String(agreement.farmId) === expenseFarmId.value &&
            (!expenseCycleId.value ||
                agreement.productionCycleId === null ||
                String(agreement.productionCycleId) === expenseCycleId.value),
    ),
);

const farmActivities = computed(() =>
    props.activities.filter(
        (activity) => String(activity.farmId) === expenseFarmId.value,
    ),
);

const filteredExpenses = computed(() =>
    props.expenses.filter(
        (expense) =>
            (farmFilter.value === 'all' ||
                String(expense.farmId) === farmFilter.value) &&
            (categoryFilter.value === 'all' ||
                String(expense.expenseCategoryId) === categoryFilter.value) &&
            (statusFilter.value === 'all' ||
                expense.status === statusFilter.value),
    ),
);

function categoryUtilization(category: FinanceVarianceCategory): number {
    if (category.budgetedMinor <= 0) {
        return category.spentMinor > 0 ? 100 : 0;
    }

    return Math.min(
        100,
        Math.round((category.spentMinor / category.budgetedMinor) * 100),
    );
}

const handleCreated = () => {
    formKey.value++;
    drawerOpen.value = false;
};

watch(expenseFarmId, () => {
    expenseCycleId.value = '';
    expenseBudgetId.value = '';
    expenseBudgetLineId.value = '';
    expenseFundingPhaseId.value = '';
    expenseInvestorAgreementId.value = '';
    expenseActivityId.value = '';
});

watch(expenseBudgetId, () => {
    expenseBudgetLineId.value = '';
});

watch(expenseCycleId, () => {
    expenseInvestorAgreementId.value = '';
});
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Head title="Finance / Expenses" />

        <PageHero
            eyebrow="Farm Accounting"
            title="Expenses"
            description="Official finance spending, receipts, category variance, and activity links."
        >
            <template #actions>
                <Button
                    v-if="
                        permissions.canManageFinance &&
                        currentTeamSlug &&
                        farms.length > 0 &&
                        categories.length > 0
                    "
                    type="button"
                    class="bg-white text-[#1b4d3e] hover:bg-emerald-50"
                    @click="drawerOpen = true"
                >
                    <Plus class="mr-1.5 size-4" />
                    Record expense
                </Button>
            </template>
            <template #metrics>
                <div class="px-4 py-3 sm:px-6">
                    <p class="text-xs text-emerald-100/75">Expenses</p>
                    <p class="mt-0.5 text-lg font-semibold tabular-nums">
                        {{ expenses.length }}
                    </p>
                </div>
                <div class="border-s border-white/15 px-4 py-3 sm:px-6">
                    <p class="text-xs text-emerald-100/75">Total spent</p>
                    <p
                        class="mt-0.5 text-base font-semibold whitespace-nowrap tabular-nums sm:text-lg"
                    >
                        {{ formatCurrency(variance.spent, currency) }}
                    </p>
                </div>
                <div
                    class="col-span-2 border-t border-white/15 px-4 py-3 sm:col-span-2 sm:border-s sm:border-t-0 sm:px-6"
                >
                    <p class="text-xs text-emerald-100/75">Remaining budget</p>
                    <p
                        class="mt-0.5 text-base font-semibold whitespace-nowrap tabular-nums sm:text-lg"
                    >
                        {{ formatCurrency(variance.balance, currency) }}
                    </p>
                </div>
            </template>
        </PageHero>

        <div class="flex flex-wrap items-center gap-2">
            <select
                v-model="farmFilter"
                aria-label="Filter expenses by farm"
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
                v-model="categoryFilter"
                aria-label="Filter expenses by category"
                class="h-9 rounded-md border bg-card px-3 text-sm"
            >
                <option value="all">All categories</option>
                <option
                    v-for="category in categories"
                    :key="category.id"
                    :value="String(category.id)"
                >
                    {{ category.name }}
                </option>
            </select>
            <select
                v-model="statusFilter"
                aria-label="Filter expenses by status"
                class="h-9 rounded-md border bg-card px-3 text-sm"
            >
                <option value="all">All statuses</option>
                <option
                    v-for="option in options.expenseStatuses"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </select>
        </div>

        <section
            class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(300px,380px)]"
        >
            <div class="space-y-4">
                <div class="flex items-center gap-2">
                    <ReceiptText class="size-4 text-primary" />
                    <Heading
                        variant="small"
                        title="Expense register"
                        description="Recorded expenses and receipt evidence"
                    />
                </div>

                <div v-if="filteredExpenses.length > 0" class="space-y-3">
                    <article
                        v-for="expense in filteredExpenses"
                        :key="expense.id"
                        class="space-y-3 rounded-2xl border bg-card p-4 text-sm shadow-xs"
                    >
                        <div
                            class="flex flex-wrap items-start justify-between gap-3"
                        >
                            <div class="min-w-0">
                                <p class="font-semibold">
                                    {{ expense.expenseCategoryName }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ expense.farmName }}
                                    <span v-if="expense.productionCycleName">
                                        · {{ expense.productionCycleName }}
                                    </span>
                                    ·
                                    <span class="whitespace-nowrap">{{
                                        expense.incurredOn
                                    }}</span>
                                    <span v-if="expense.vendor">
                                        · {{ expense.vendor }}
                                    </span>
                                </p>
                            </div>
                            <Badge variant="secondary" class="tabular-nums">
                                {{
                                    formatCurrency(
                                        expense.amount,
                                        expense.currency,
                                    )
                                }}
                            </Badge>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <Badge v-if="expense.investorAgreementTitle">
                                {{ expense.investorAgreementTitle }}
                            </Badge>
                            <Badge variant="outline">
                                {{ expense.investorVisibilityStatusLabel }}
                            </Badge>
                            <Badge variant="secondary">
                                {{ expense.statusLabel }}
                            </Badge>
                        </div>
                        <p v-if="expense.description">
                            {{ expense.description }}
                        </p>
                        <EvidenceGallery
                            v-if="expense.receipts?.length"
                            :items="expense.receipts"
                            compact
                            dialog-title="Expense receipts"
                        />
                    </article>
                </div>
                <EmptyState
                    v-else
                    title="No matching expenses"
                    description="Record an expense or adjust your farm, category, and status filters."
                    :icon="ReceiptText"
                />
            </div>

            <aside
                class="h-fit space-y-4 rounded-2xl border bg-card p-5 shadow-xs"
            >
                <Heading
                    variant="small"
                    title="Category variance"
                    description="Budgeted vs. spent per category"
                />
                <div v-if="variance.byCategory.length > 0" class="space-y-3">
                    <div
                        v-for="category in variance.byCategory"
                        :key="category.categoryId"
                        class="space-y-2 rounded-xl border p-3 text-sm"
                        :class="
                            category.balanceMinor < 0
                                ? 'border-rose-300/70 bg-rose-50/40 dark:border-rose-900/60 dark:bg-rose-950/20'
                                : ''
                        "
                    >
                        <div class="flex items-start justify-between gap-2">
                            <p class="font-medium">
                                {{ category.categoryName }}
                            </p>
                            <span
                                class="text-xs font-medium tabular-nums"
                                :class="
                                    category.balanceMinor < 0
                                        ? 'text-rose-700 dark:text-rose-300'
                                        : 'text-muted-foreground'
                                "
                            >
                                {{ formatCurrency(category.balance, currency) }}
                                left
                            </span>
                        </div>
                        <div
                            class="h-1.5 w-full overflow-hidden rounded-full bg-muted"
                        >
                            <div
                                class="h-full rounded-full transition-all"
                                :class="
                                    category.balanceMinor < 0
                                        ? 'bg-rose-500'
                                        : 'bg-primary'
                                "
                                :style="{
                                    width: `${categoryUtilization(category)}%`,
                                }"
                            />
                        </div>
                        <p class="text-xs text-muted-foreground tabular-nums">
                            {{ formatCurrency(category.spent, currency) }} spent
                            of
                            {{ formatCurrency(category.budgeted, currency) }}
                        </p>
                    </div>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    No categories configured yet.
                </p>
            </aside>
        </section>

        <SlideOverForm
            :open="drawerOpen"
            title="Record expense"
            description="Official finance totals come from expenses, not diary cost notes."
            wide
            @update:open="drawerOpen = $event"
        >
            <Form
                v-if="
                    farms.length > 0 && categories.length > 0 && currentTeamSlug
                "
                :key="formKey"
                v-bind="storeExpense.form(currentTeamSlug)"
                class="grid gap-4 sm:grid-cols-2"
                v-slot="{ errors, processing, progress }"
                @success="handleCreated"
            >
                <div class="grid gap-1.5">
                    <Label for="expense-farm">Farm</Label>
                    <select
                        id="expense-farm"
                        v-model="expenseFarmId"
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
                    <Label for="expense-cycle">Cycle</Label>
                    <select
                        id="expense-cycle"
                        v-model="expenseCycleId"
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
                    <Label for="expense-category">Category</Label>
                    <select
                        id="expense-category"
                        v-model="expenseCategoryId"
                        name="expense_category_id"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                        required
                    >
                        <option
                            v-for="category in categories"
                            :key="category.id"
                            :value="String(category.id)"
                        >
                            {{ category.name }}
                        </option>
                    </select>
                    <InputError :message="errors.expense_category_id" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="expense-status">Status</Label>
                    <select
                        id="expense-status"
                        v-model="expenseStatus"
                        name="status"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option
                            v-for="option in options.expenseStatuses"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <InputError :message="errors.status" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="expense-budget">Budget</Label>
                    <select
                        id="expense-budget"
                        v-model="expenseBudgetId"
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
                    <Label for="expense-budget-line">Budget line</Label>
                    <select
                        id="expense-budget-line"
                        v-model="expenseBudgetLineId"
                        name="budget_line_id"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">No line</option>
                        <option
                            v-for="line in selectedBudgetLines"
                            :key="line.id"
                            :value="String(line.id)"
                        >
                            {{ line.description }}
                        </option>
                    </select>
                    <InputError :message="errors.budget_line_id" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="expense-phase">Funding phase</Label>
                    <select
                        id="expense-phase"
                        v-model="expenseFundingPhaseId"
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
                    <Label for="expense-investor-agreement">
                        Investor agreement
                    </Label>
                    <select
                        id="expense-investor-agreement"
                        v-model="expenseInvestorAgreementId"
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
                    <Label for="expense-investor-visibility">
                        Investor visibility
                    </Label>
                    <select
                        id="expense-investor-visibility"
                        v-model="expenseInvestorVisibilityStatus"
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
                    <Label for="expense-activity">Activity</Label>
                    <select
                        id="expense-activity"
                        v-model="expenseActivityId"
                        name="farm_activity_id"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">No activity</option>
                        <option
                            v-for="activity in farmActivities"
                            :key="activity.id"
                            :value="String(activity.id)"
                        >
                            {{ activity.activityDate }} /
                            {{ activity.activityType }}
                        </option>
                    </select>
                    <InputError :message="errors.farm_activity_id" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="expense-date">Date</Label>
                    <Input
                        id="expense-date"
                        name="incurred_on"
                        type="date"
                        required
                    />
                    <InputError :message="errors.incurred_on" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="expense-amount">Amount ({{ currency }})</Label>
                    <Input
                        id="expense-amount"
                        name="amount"
                        inputmode="decimal"
                        required
                    />
                    <InputError :message="errors.amount" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="expense-vendor">Vendor</Label>
                    <Input id="expense-vendor" name="vendor" />
                    <InputError :message="errors.vendor" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="expense-payment-method">Payment method</Label>
                    <Input id="expense-payment-method" name="payment_method" />
                    <InputError :message="errors.payment_method" />
                </div>
                <div class="grid gap-1.5 sm:col-span-2">
                    <Label for="expense-description">Description</Label>
                    <Input
                        id="expense-description"
                        name="description"
                        required
                    />
                    <InputError :message="errors.description" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="receipt-caption">Receipt caption</Label>
                    <Input id="receipt-caption" name="receipt_caption" />
                    <InputError :message="errors.receipt_caption" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="expense-receipts">Receipts</Label>
                    <Input
                        id="expense-receipts"
                        name="receipts[]"
                        type="file"
                        multiple
                    />
                    <InputError :message="errors.receipts" />
                    <progress
                        v-if="progress"
                        :value="progress.percentage"
                        max="100"
                        class="h-1 w-full"
                    />
                </div>
                <div class="sm:col-span-2">
                    <Button :disabled="processing" class="w-full sm:w-auto">
                        Record expense
                    </Button>
                </div>
            </Form>
            <p v-else class="text-sm text-muted-foreground">
                Create a farm and default category before recording expenses.
            </p>
        </SlideOverForm>
    </div>
</template>
