<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { FileUp, ReceiptText } from 'lucide-vue-next';
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
    FinanceOptions,
    FinancePermissions,
    FinanceVariance,
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
    activities: FinanceActivity[];
    expenses: Expense[];
    variance: FinanceVariance;
    options: Pick<FinanceOptions, 'expenseStatuses'>;
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
const expenseFarmId = ref(props.farms[0]?.id ? String(props.farms[0].id) : '');
const expenseCycleId = ref('');
const expenseBudgetId = ref('');
const expenseBudgetLineId = ref('');
const expenseFundingPhaseId = ref('');
const expenseCategoryId = ref(
    props.categories[0]?.id ? String(props.categories[0].id) : '',
);
const expenseActivityId = ref('');
const expenseStatus = ref('approved');
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

const farmActivities = computed(() =>
    props.activities.filter(
        (activity) => String(activity.farmId) === expenseFarmId.value,
    ),
);

watch(expenseFarmId, () => {
    expenseCycleId.value = '';
    expenseBudgetId.value = '';
    expenseBudgetLineId.value = '';
    expenseFundingPhaseId.value = '';
    expenseActivityId.value = '';
});

watch(expenseBudgetId, () => {
    expenseBudgetLineId.value = '';
});
</script>

<template>
    <Head title="Finance / Expenses" />

    <div class="flex flex-1 flex-col gap-8 p-4 md:p-6">
        <Heading
            title="Expenses"
            description="Recorded spending, receipts, category variance, and activity links"
        />

        <section
            v-if="permissions.canManageFinance && currentTeamSlug"
            class="space-y-4 rounded-lg border p-4"
        >
            <div class="flex items-center gap-2">
                <FileUp class="size-4" />
                <Heading
                    variant="small"
                    title="Record expense"
                    description="Official finance totals come from expenses, not diary cost notes"
                />
            </div>

            <Form
                v-if="farms.length > 0 && categories.length > 0"
                :key="formKey"
                v-bind="storeExpense.form(currentTeamSlug)"
                class="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
                v-slot="{ errors, processing, progress }"
                @success="formKey++"
            >
                <div class="grid gap-2">
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
                <div class="grid gap-2">
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
                <div class="grid gap-2">
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
                <div class="grid gap-2">
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
                <div class="grid gap-2">
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
                <div class="grid gap-2">
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
                <div class="grid gap-2">
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
                <div class="grid gap-2">
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
                <div class="grid gap-2">
                    <Label for="expense-date">Date</Label>
                    <Input
                        id="expense-date"
                        name="incurred_on"
                        type="date"
                        required
                    />
                    <InputError :message="errors.incurred_on" />
                </div>
                <div class="grid gap-2">
                    <Label for="expense-amount">Amount ({{ currency }})</Label>
                    <Input
                        id="expense-amount"
                        name="amount"
                        inputmode="decimal"
                        required
                    />
                    <InputError :message="errors.amount" />
                </div>
                <div class="grid gap-2">
                    <Label for="expense-vendor">Vendor</Label>
                    <Input id="expense-vendor" name="vendor" />
                    <InputError :message="errors.vendor" />
                </div>
                <div class="grid gap-2">
                    <Label for="expense-payment-method">Payment method</Label>
                    <Input id="expense-payment-method" name="payment_method" />
                    <InputError :message="errors.payment_method" />
                </div>
                <div class="grid gap-2 md:col-span-2">
                    <Label for="expense-description">Description</Label>
                    <Input
                        id="expense-description"
                        name="description"
                        required
                    />
                    <InputError :message="errors.description" />
                </div>
                <div class="grid gap-2">
                    <Label for="receipt-caption">Receipt caption</Label>
                    <Input id="receipt-caption" name="receipt_caption" />
                    <InputError :message="errors.receipt_caption" />
                </div>
                <div class="grid gap-2">
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
                <div class="flex items-end">
                    <Button :disabled="processing">Record expense</Button>
                </div>
            </Form>
            <p v-else class="text-sm text-muted-foreground">
                Create a farm and default category before recording expenses.
            </p>
        </section>

        <section
            class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(320px,420px)]"
        >
            <div class="space-y-4">
                <div class="flex items-center gap-2">
                    <ReceiptText class="size-4" />
                    <Heading
                        variant="small"
                        title="Expense register"
                        description="Recorded expenses and private receipt links"
                    />
                </div>

                <div v-if="expenses.length > 0" class="space-y-3">
                    <article
                        v-for="expense in expenses"
                        :key="expense.id"
                        class="space-y-3 rounded-lg border p-4 text-sm"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-medium">
                                    {{ expense.expenseCategoryName }}
                                </p>
                                <p class="text-muted-foreground">
                                    {{ expense.farmName }}
                                    <span v-if="expense.productionCycleName">
                                        / {{ expense.productionCycleName }}
                                    </span>
                                </p>
                            </div>
                            <Badge variant="secondary">
                                {{ expense.amount }} {{ expense.currency }}
                            </Badge>
                        </div>
                        <p v-if="expense.description">
                            {{ expense.description }}
                        </p>
                        <div
                            v-if="expense.receipts?.length"
                            class="flex flex-wrap gap-2"
                        >
                            <a
                                v-for="receipt in expense.receipts"
                                :key="receipt.id"
                                :href="receipt.downloadUrl"
                                class="text-sm underline underline-offset-4"
                            >
                                {{ receipt.fileName }}
                            </a>
                        </div>
                    </article>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    No expenses recorded yet.
                </p>
            </div>

            <aside class="space-y-4 rounded-lg border p-4">
                <Heading
                    variant="small"
                    title="Variance"
                    description="Budgeted and spent by category"
                />
                <div v-if="variance.byCategory.length > 0" class="space-y-3">
                    <div
                        v-for="category in variance.byCategory"
                        :key="category.categoryId"
                        class="rounded-lg border p-3 text-sm"
                    >
                        <p class="font-medium">{{ category.categoryName }}</p>
                        <p class="mt-1 text-muted-foreground">
                            Budgeted {{ category.budgeted }} / spent
                            {{ category.spent }}
                        </p>
                        <p class="mt-1">Balance {{ category.balance }}</p>
                    </div>
                </div>
            </aside>
        </section>
    </div>
</template>
