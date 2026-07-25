<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { ChartNoAxesCombined, Plus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import { index as financeIndex } from '@/routes/finance';
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

const bumpFormKey = (key: keyof typeof formKeys.value) => {
    formKeys.value[key]++;
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
    <Head title="Finance / Budgets" />

    <div class="flex flex-1 flex-col gap-8 p-4 md:p-6">
        <Heading
            title="Budgets"
            description="Budget headers, category lines, planned totals, and variance inputs"
        />

        <section
            v-if="permissions.canManageFinance && currentTeamSlug"
            class="space-y-4 rounded-lg border p-4"
        >
            <div class="flex items-center gap-2">
                <Plus class="size-4" />
                <Heading
                    variant="small"
                    title="Create budget"
                    description="Cycle-first when a production cycle exists, farm-level when it does not"
                />
            </div>

            <Form
                v-if="farms.length > 0"
                :key="formKeys.budget"
                v-bind="storeBudget.form(currentTeamSlug)"
                class="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
                v-slot="{ errors, processing }"
                @success="bumpFormKey('budget')"
            >
                <div class="grid gap-2">
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
                <div class="grid gap-2">
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
                <div class="grid gap-2">
                    <Label for="budget-name">Name</Label>
                    <Input id="budget-name" name="name" required />
                    <InputError :message="errors.name" />
                </div>
                <div class="grid gap-2">
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
                <div class="grid gap-2">
                    <Label for="budget-start">Start</Label>
                    <Input
                        id="budget-start"
                        name="period_start_on"
                        type="date"
                    />
                    <InputError :message="errors.period_start_on" />
                </div>
                <div class="grid gap-2">
                    <Label for="budget-end">End</Label>
                    <Input id="budget-end" name="period_end_on" type="date" />
                    <InputError :message="errors.period_end_on" />
                </div>
                <div class="grid gap-2">
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
                <div class="grid gap-2">
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
                <div class="grid gap-2 md:col-span-2 xl:col-span-3">
                    <Label for="budget-line-description">First line</Label>
                    <Input
                        id="budget-line-description"
                        name="lines[0][description]"
                    />
                    <InputError :message="errors['lines.0.description']" />
                </div>
                <div class="flex items-end">
                    <Button :disabled="processing">Create budget</Button>
                </div>
            </Form>
            <p v-else class="text-sm text-muted-foreground">
                Create a farm before adding budgets.
            </p>
        </section>

        <section
            v-if="
                permissions.canManageFinance &&
                currentTeamSlug &&
                budgets.length > 0
            "
            class="space-y-4 rounded-lg border p-4"
        >
            <div class="flex items-center gap-2">
                <Plus class="size-4" />
                <Heading
                    variant="small"
                    title="Add budget line"
                    description="Additional category-level planned amounts"
                />
            </div>

            <Form
                v-if="selectedBudget"
                :key="formKeys.line"
                v-bind="
                    storeBudgetLine.form({
                        current_team: currentTeamSlug,
                        budget: selectedBudget.id,
                    })
                "
                class="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
                v-slot="{ errors, processing }"
                @success="bumpFormKey('line')"
            >
                <div class="grid gap-2">
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
                <div class="grid gap-2">
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
                <div class="grid gap-2">
                    <Label for="add-line-amount">Amount ({{ currency }})</Label>
                    <Input
                        id="add-line-amount"
                        name="planned_amount"
                        inputmode="decimal"
                        required
                    />
                    <InputError :message="errors.planned_amount" />
                </div>
                <div class="grid gap-2">
                    <Label for="add-line-description">Description</Label>
                    <Input
                        id="add-line-description"
                        name="description"
                        required
                    />
                    <InputError :message="errors.description" />
                </div>
                <div class="flex items-end">
                    <Button :disabled="processing">Add line</Button>
                </div>
            </Form>
        </section>

        <section class="space-y-4">
            <div class="flex items-center gap-2">
                <ChartNoAxesCombined class="size-4" />
                <Heading
                    variant="small"
                    title="Budget register"
                    description="Current budget records and planned category lines"
                />
            </div>

            <div v-if="budgets.length > 0" class="grid gap-4 xl:grid-cols-2">
                <article
                    v-for="budget in budgets"
                    :key="budget.id"
                    class="space-y-4 rounded-lg border p-4"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="font-medium">{{ budget.name }}</h3>
                            <p class="text-sm text-muted-foreground">
                                {{ budget.farmName }}
                                <span v-if="budget.productionCycleName">
                                    / {{ budget.productionCycleName }}
                                </span>
                            </p>
                        </div>
                        <Badge variant="secondary">
                            {{ budget.statusLabel }}
                        </Badge>
                    </div>

                    <div v-if="budget.lines?.length" class="space-y-2">
                        <div
                            v-for="line in budget.lines"
                            :key="line.id"
                            class="grid gap-2 rounded-lg border p-3 text-sm md:grid-cols-3"
                        >
                            <p class="font-medium">{{ line.description }}</p>
                            <p class="text-muted-foreground">
                                {{
                                    line.expenseCategoryName ?? 'Uncategorized'
                                }}
                            </p>
                            <p>{{ line.plannedAmount }} {{ line.currency }}</p>
                        </div>
                    </div>
                    <p v-else class="text-sm text-muted-foreground">
                        No budget lines yet.
                    </p>
                </article>
            </div>
            <p v-else class="text-sm text-muted-foreground">
                No budgets created yet.
            </p>
        </section>
    </div>
</template>
