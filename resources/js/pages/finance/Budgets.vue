<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { ChartNoAxesCombined, Download, ListPlus, Plus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import EmptyState from '@/components/farm/EmptyState.vue';
import PageHero from '@/components/farm/PageHero.vue';
import SlideOverForm from '@/components/farm/SlideOverForm.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCurrency } from '@/lib/currency';
import { dashboard } from '@/routes';
import { index as financeIndex } from '@/routes/finance';
import { index as budgetExports } from '@/routes/finance/budget-exports';
import { store as storeBudgetLine } from '@/routes/finance/budget-lines';
import {
    index as budgetsIndex,
    store as storeBudget,
} from '@/routes/finance/budgets';
import type {
    Budget,
    ExpenseCategory,
    FinanceFarm,
    FinanceOptions,
    FinancePermissions,
    Team,
} from '@/types';

type Props = {
    permissions: FinancePermissions;
    currency: string;
    farms: FinanceFarm[];
    categories: ExpenseCategory[];
    budgets: Budget[];
    options: Pick<FinanceOptions, 'budgetStatuses'>;
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
                title: 'Budgets',
                href: props.currentTeam
                    ? budgetsIndex(props.currentTeam.slug)
                    : '/',
            },
        ],
    }),
});

const currentTeam = computed(() => page.props.currentTeam as Team | null);
const currentTeamSlug = computed(() => currentTeam.value?.slug ?? '');
const budgetDrawerOpen = ref(false);
const lineDrawerOpen = ref(false);
const farmFilter = ref('all');
const statusFilter = ref('all');
const budgetFarmId = ref(props.farms[0]?.id ? String(props.farms[0].id) : '');
const budgetCycleId = ref('');
const budgetStatus = ref('draft');
const lineCategoryId = ref(
    props.categories[0]?.id ? String(props.categories[0].id) : '',
);
const selectedBudgetId = ref(
    props.budgets[0]?.id ? String(props.budgets[0].id) : '',
);
const addLineCategoryId = ref(
    props.categories[0]?.id ? String(props.categories[0].id) : '',
);
const formKeys = ref({ budget: 0, line: 0 });

const selectedFarm = computed(() =>
    props.farms.find((farm) => String(farm.id) === budgetFarmId.value),
);

const selectedBudget = computed(() =>
    props.budgets.find(
        (budget) => String(budget.id) === selectedBudgetId.value,
    ),
);

const filteredBudgets = computed(() =>
    props.budgets.filter(
        (budget) =>
            (farmFilter.value === 'all' ||
                String(budget.farmId) === farmFilter.value) &&
            (statusFilter.value === 'all' ||
                budget.status === statusFilter.value),
    ),
);

const totalLinesCount = computed(() =>
    props.budgets.reduce((sum, budget) => sum + (budget.lines?.length ?? 0), 0),
);

const totalPlannedDisplay = computed(() => {
    const totalMinor = props.budgets.reduce(
        (sum, budget) =>
            sum +
            (budget.lines?.reduce(
                (lineSum, line) => lineSum + (line.plannedAmountMinor ?? 0),
                0,
            ) ?? 0),
        0,
    );

    return formatCurrency((totalMinor / 100).toFixed(2), props.currency);
});

function budgetPlannedTotal(budget: Budget): string {
    const minor =
        budget.lines?.reduce(
            (sum, line) => sum + (line.plannedAmountMinor ?? 0),
            0,
        ) ?? 0;

    return formatCurrency((minor / 100).toFixed(2), budget.currency);
}

function openAddLineForBudget(budgetId: number) {
    selectedBudgetId.value = String(budgetId);
    lineDrawerOpen.value = true;
}

const handleBudgetCreated = () => {
    formKeys.value.budget++;
    budgetDrawerOpen.value = false;
};

const handleLineAdded = () => {
    formKeys.value.line++;
    lineDrawerOpen.value = false;
};

watch(budgetFarmId, () => {
    budgetCycleId.value = '';
});

watch(
    () => props.budgets.map((budget) => budget.id),
    () => {
        const budgetIds = props.budgets.map((budget) => String(budget.id));

        if (!budgetIds.includes(selectedBudgetId.value)) {
            selectedBudgetId.value = budgetIds[0] ?? '';
        }
    },
    { immediate: true },
);
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Head title="Finance / Budgets" />

        <PageHero
            eyebrow="Farm Accounting"
            title="Budgets"
            description="Budget headers, category lines, planned totals, and variance exports."
        >
            <template #actions>
                <Button
                    v-if="
                        permissions.canManageFinance &&
                        currentTeamSlug &&
                        budgets.length > 0
                    "
                    type="button"
                    variant="outline"
                    class="border-white/25 bg-white/10 text-white hover:bg-white/20 hover:text-white"
                    @click="lineDrawerOpen = true"
                >
                    <ListPlus class="mr-1.5 size-4" />
                    Add line
                </Button>
                <Button
                    v-if="
                        permissions.canManageFinance &&
                        currentTeamSlug &&
                        farms.length > 0
                    "
                    type="button"
                    class="bg-white text-[#1b4d3e] hover:bg-emerald-50"
                    @click="budgetDrawerOpen = true"
                >
                    <Plus class="mr-1.5 size-4" />
                    Create budget
                </Button>
            </template>
            <template #metrics>
                <div class="px-4 py-3 sm:px-6">
                    <p class="text-xs text-emerald-100/75">Budgets</p>
                    <p class="mt-0.5 text-lg font-semibold tabular-nums">
                        {{ budgets.length }}
                    </p>
                </div>
                <div class="border-s border-white/15 px-4 py-3 sm:px-6">
                    <p class="text-xs text-emerald-100/75">Planned lines</p>
                    <p class="mt-0.5 text-lg font-semibold tabular-nums">
                        {{ totalLinesCount }}
                    </p>
                </div>
                <div
                    class="col-span-2 border-t border-white/15 px-4 py-3 sm:col-span-2 sm:border-s sm:border-t-0 sm:px-6"
                >
                    <p class="text-xs text-emerald-100/75">Total planned</p>
                    <p class="mt-0.5 text-lg font-semibold tabular-nums">
                        {{ totalPlannedDisplay }}
                    </p>
                </div>
            </template>
        </PageHero>

        <div class="flex flex-wrap items-center gap-2">
            <select
                v-model="farmFilter"
                aria-label="Filter budgets by farm"
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
                aria-label="Filter budgets by status"
                class="h-9 rounded-md border bg-card px-3 text-sm"
            >
                <option value="all">All statuses</option>
                <option
                    v-for="option in options.budgetStatuses"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </select>
        </div>

        <section
            v-if="filteredBudgets.length > 0"
            class="grid gap-4 xl:grid-cols-2"
        >
            <article
                v-for="budget in filteredBudgets"
                :key="budget.id"
                class="flex flex-col justify-between gap-4 rounded-2xl border bg-card p-5 text-sm shadow-xs"
            >
                <div class="space-y-4">
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div class="min-w-0">
                            <h2 class="text-base font-semibold">
                                {{ budget.name }}
                            </h2>
                            <p class="text-xs text-muted-foreground">
                                {{ budget.farmName }}
                                <span v-if="budget.productionCycleName">
                                    · {{ budget.productionCycleName }}
                                </span>
                                <span
                                    v-if="
                                        budget.periodStartOn ||
                                        budget.periodEndOn
                                    "
                                >
                                    · {{ budget.periodStartOn ?? '—' }} to
                                    {{ budget.periodEndOn ?? '—' }}
                                </span>
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <Badge variant="outline" class="tabular-nums">
                                {{ budgetPlannedTotal(budget) }}
                            </Badge>
                            <Badge variant="secondary">
                                {{ budget.statusLabel }}
                            </Badge>
                        </div>
                    </div>

                    <div v-if="budget.lines?.length" class="space-y-2">
                        <div
                            v-for="line in budget.lines"
                            :key="line.id"
                            class="grid gap-1.5 rounded-xl border bg-muted/25 p-3 sm:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_auto] sm:items-center sm:gap-3"
                        >
                            <p class="font-medium">{{ line.description }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{
                                    line.expenseCategoryName ?? 'Uncategorized'
                                }}
                            </p>
                            <p class="font-medium tabular-nums sm:text-end">
                                {{
                                    formatCurrency(
                                        line.plannedAmount,
                                        line.currency,
                                    )
                                }}
                            </p>
                        </div>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">
                        No budget lines yet.
                    </p>
                </div>

                <div
                    v-if="permissions.canManageFinance && currentTeamSlug"
                    class="flex flex-wrap items-center gap-2 border-t pt-3"
                >
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        @click="openAddLineForBudget(budget.id)"
                    >
                        <ListPlus class="mr-1.5 size-4" />
                        Add line
                    </Button>
                    <Button variant="outline" size="sm" as-child>
                        <Link
                            :href="budgetExports([currentTeamSlug, budget.id])"
                        >
                            <Download class="mr-1.5 size-4" />
                            Export XLSX
                        </Link>
                    </Button>
                </div>
            </article>
        </section>

        <EmptyState
            v-else
            title="No budgets yet"
            description="Create a cycle or farm budget to plan category limits and track spending variance."
            :icon="ChartNoAxesCombined"
        />

        <SlideOverForm
            :open="budgetDrawerOpen"
            title="Create budget"
            description="Cycle-first when a production cycle exists, farm-level when it does not."
            wide
            @update:open="budgetDrawerOpen = $event"
        >
            <Form
                v-if="farms.length > 0 && currentTeamSlug"
                :key="formKeys.budget"
                v-bind="storeBudget.form(currentTeamSlug)"
                class="grid gap-4 sm:grid-cols-2"
                v-slot="{ errors, processing }"
                @success="handleBudgetCreated"
            >
                <div class="grid gap-1.5">
                    <Label for="budget-farm">Farm</Label>
                    <select
                        id="budget-farm"
                        v-model="budgetFarmId"
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
                    <Label for="budget-cycle">Cycle</Label>
                    <select
                        id="budget-cycle"
                        v-model="budgetCycleId"
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
                    <Label for="budget-name">Name</Label>
                    <Input id="budget-name" name="name" required />
                    <InputError :message="errors.name" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="budget-status">Status</Label>
                    <select
                        id="budget-status"
                        v-model="budgetStatus"
                        name="status"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option
                            v-for="option in options.budgetStatuses"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <InputError :message="errors.status" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="budget-start">Start</Label>
                    <Input
                        id="budget-start"
                        name="period_start_on"
                        type="date"
                    />
                    <InputError :message="errors.period_start_on" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="budget-end">End</Label>
                    <Input id="budget-end" name="period_end_on" type="date" />
                    <InputError :message="errors.period_end_on" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="budget-line-category">First category</Label>
                    <select
                        id="budget-line-category"
                        v-model="lineCategoryId"
                        name="lines[0][expense_category_id]"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">Uncategorized</option>
                        <option
                            v-for="category in categories"
                            :key="category.id"
                            :value="String(category.id)"
                        >
                            {{ category.name }}
                        </option>
                    </select>
                    <InputError
                        :message="errors['lines.0.expense_category_id']"
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="budget-line-amount">
                        Planned amount ({{ currency }})
                    </Label>
                    <Input
                        id="budget-line-amount"
                        name="lines[0][planned_amount]"
                        inputmode="decimal"
                    />
                    <InputError :message="errors['lines.0.planned_amount']" />
                </div>
                <div class="grid gap-1.5 sm:col-span-2">
                    <Label for="budget-line-description">First line</Label>
                    <Input
                        id="budget-line-description"
                        name="lines[0][description]"
                    />
                    <InputError :message="errors['lines.0.description']" />
                </div>
                <div class="sm:col-span-2">
                    <Button :disabled="processing" class="w-full sm:w-auto">
                        Create budget
                    </Button>
                </div>
            </Form>
            <p v-else class="text-sm text-muted-foreground">
                Create a farm before adding budgets.
            </p>
        </SlideOverForm>

        <SlideOverForm
            :open="lineDrawerOpen"
            title="Add budget line"
            description="Additional category-level planned amounts."
            @update:open="lineDrawerOpen = $event"
        >
            <Form
                v-if="selectedBudget && currentTeamSlug"
                :key="formKeys.line"
                v-bind="
                    storeBudgetLine.form({
                        current_team: currentTeamSlug,
                        budget: selectedBudget.id,
                    })
                "
                class="grid gap-4"
                v-slot="{ errors, processing }"
                @success="handleLineAdded"
            >
                <div class="grid gap-1.5">
                    <Label for="add-line-budget">Budget</Label>
                    <select
                        id="add-line-budget"
                        v-model="selectedBudgetId"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option
                            v-for="budget in budgets"
                            :key="budget.id"
                            :value="String(budget.id)"
                        >
                            {{ budget.name }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="add-line-category">Category</Label>
                    <select
                        id="add-line-category"
                        v-model="addLineCategoryId"
                        name="expense_category_id"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">Uncategorized</option>
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
                    <Label for="add-line-amount">Amount ({{ currency }})</Label>
                    <Input
                        id="add-line-amount"
                        name="planned_amount"
                        inputmode="decimal"
                        required
                    />
                    <InputError :message="errors.planned_amount" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="add-line-description">Description</Label>
                    <Input
                        id="add-line-description"
                        name="description"
                        required
                    />
                    <InputError :message="errors.description" />
                </div>
                <Button :disabled="processing" class="w-full sm:w-auto">
                    Add line
                </Button>
            </Form>
        </SlideOverForm>
    </div>
</template>
