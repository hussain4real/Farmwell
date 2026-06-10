<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import {
    Banknote,
    ChartNoAxesCombined,
    CircleDollarSign,
    Landmark,
    ReceiptText,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import { index as financeIndex } from '@/routes/finance';
import { index as budgetsIndex } from '@/routes/finance/budgets';
import { index as expensesIndex } from '@/routes/finance/expenses';
import { index as externalTransfersIndex } from '@/routes/finance/external-transfers';
import { index as fundingPhasesIndex } from '@/routes/finance/funding-phases';
import { update as updateCurrency } from '@/routes/finance/settings/currency';
import type {
    CarryForward,
    Expense,
    ExternalTransfer,
    FinancePermissions,
    FinanceSummary,
    FinanceVariance,
    Team,
} from '@/types';

type Props = {
    permissions: FinancePermissions;
    currency: string;
    summary: FinanceSummary;
    variance: FinanceVariance;
    carryForward: CarryForward;
    recentExpenses: Expense[];
    recentTransfers: ExternalTransfer[];
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
        ],
    }),
});

const currentTeam = computed(() => page.props.currentTeam as Team | null);
const currentTeamSlug = computed(() => currentTeam.value?.slug ?? '');
const currency = ref(props.currency);

const workspaceLinks = computed(() => {
    if (!currentTeamSlug.value) {
        return [];
    }

    return [
        {
            title: 'Budgets',
            href: budgetsIndex(currentTeamSlug.value).url,
            description: 'Budget headers, category lines, and planned totals',
            icon: ChartNoAxesCombined,
        },
        {
            title: 'Funding phases',
            href: fundingPhasesIndex(currentTeamSlug.value).url,
            description: 'Recorded releases, milestones, and carry-forward',
            icon: Banknote,
        },
        {
            title: 'Expenses',
            href: expensesIndex(currentTeamSlug.value).url,
            description: 'Expense records, categories, receipts, and variance',
            icon: ReceiptText,
        },
        {
            title: 'External transfers',
            href: externalTransfersIndex(currentTeamSlug.value).url,
            description: 'Proof, references, and reconciliation records',
            icon: Landmark,
        },
    ];
});
</script>

<template>
    <Head title="Finance" />

    <div class="flex flex-1 flex-col gap-8 p-4 md:p-6">
        <Heading
            title="Finance"
            description="Budgets, expenses, funding phases, external transfers, variance, and carry-forward"
        />

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-lg border p-4">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm text-muted-foreground">
                        Budgeted
                    </span>
                    <ChartNoAxesCombined class="size-4 text-muted-foreground" />
                </div>
                <p class="mt-3 text-2xl font-semibold">
                    {{ summary.budgeted }} {{ currency }}
                </p>
            </div>
            <div class="rounded-lg border p-4">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm text-muted-foreground">Spent</span>
                    <ReceiptText class="size-4 text-muted-foreground" />
                </div>
                <p class="mt-3 text-2xl font-semibold">
                    {{ summary.spent }} {{ currency }}
                </p>
            </div>
            <div class="rounded-lg border p-4">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm text-muted-foreground">
                        Released
                    </span>
                    <Banknote class="size-4 text-muted-foreground" />
                </div>
                <p class="mt-3 text-2xl font-semibold">
                    {{ summary.released }} {{ currency }}
                </p>
            </div>
            <div class="rounded-lg border p-4">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm text-muted-foreground">
                        Carry-forward
                    </span>
                    <CircleDollarSign class="size-4 text-muted-foreground" />
                </div>
                <p class="mt-3 text-2xl font-semibold">
                    {{ carryForward.carryForward }} {{ currency }}
                </p>
            </div>
        </section>

        <section class="grid gap-4 xl:grid-cols-4">
            <Link
                v-for="workspace in workspaceLinks"
                :key="workspace.title"
                :href="workspace.href"
                class="rounded-lg border p-4 transition-colors hover:bg-accent"
            >
                <div class="flex items-start gap-3">
                    <component
                        :is="workspace.icon"
                        class="mt-1 size-4 text-muted-foreground"
                    />
                    <div>
                        <h3 class="font-medium">{{ workspace.title }}</h3>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ workspace.description }}
                        </p>
                    </div>
                </div>
            </Link>
        </section>

        <section
            v-if="permissions.canManageFinance && currentTeamSlug"
            class="space-y-4 rounded-lg border p-4"
        >
            <div class="flex items-center gap-2">
                <Landmark class="size-4" />
                <Heading
                    variant="small"
                    title="Default currency"
                    description="Copied onto new budget, funding, expense, and transfer records"
                />
            </div>

            <Form
                v-bind="updateCurrency.form(currentTeamSlug)"
                class="grid gap-4 md:grid-cols-[minmax(0,240px)_auto]"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="finance-currency">Currency</Label>
                    <Input
                        id="finance-currency"
                        v-model="currency"
                        name="currency"
                        maxlength="3"
                        required
                    />
                    <InputError :message="errors.currency" />
                </div>
                <div class="flex items-end">
                    <Button :disabled="processing">Save</Button>
                </div>
            </Form>
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            <div class="space-y-4 rounded-lg border p-4">
                <div class="flex items-center gap-2">
                    <ChartNoAxesCombined class="size-4" />
                    <Heading
                        variant="small"
                        title="Variance by category"
                        description="Budgeted, spent, and remaining balance"
                    />
                </div>

                <div v-if="variance.byCategory.length > 0" class="space-y-3">
                    <article
                        v-for="category in variance.byCategory"
                        :key="category.categoryId"
                        class="grid gap-3 rounded-lg border p-3 text-sm md:grid-cols-4"
                    >
                        <p class="font-medium">{{ category.categoryName }}</p>
                        <p>Budgeted {{ category.budgeted }}</p>
                        <p>Spent {{ category.spent }}</p>
                        <p>Balance {{ category.balance }}</p>
                    </article>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    No expense categories yet.
                </p>
            </div>

            <div class="space-y-4 rounded-lg border p-4">
                <div class="flex items-center gap-2">
                    <Banknote class="size-4" />
                    <Heading
                        variant="small"
                        title="Carry-forward"
                        description="Release less spend across funding phases"
                    />
                </div>

                <div v-if="carryForward.phases.length > 0" class="space-y-3">
                    <article
                        v-for="phase in carryForward.phases"
                        :key="phase.phaseId"
                        class="rounded-lg border p-3 text-sm"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <p class="font-medium">{{ phase.phaseName }}</p>
                            <Badge variant="secondary">
                                {{ phase.carryForward }}
                            </Badge>
                        </div>
                        <p class="mt-2 text-muted-foreground">
                            Released {{ phase.released }} / spent
                            {{ phase.spent }}
                        </p>
                    </article>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    No funding phases yet.
                </p>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            <div class="space-y-4 rounded-lg border p-4">
                <div class="flex items-center gap-2">
                    <ReceiptText class="size-4" />
                    <Heading
                        variant="small"
                        title="Recent expenses"
                        description="Latest recorded farm spending"
                    />
                </div>
                <div v-if="recentExpenses.length > 0" class="space-y-3">
                    <article
                        v-for="expense in recentExpenses"
                        :key="expense.id"
                        class="rounded-lg border p-3 text-sm"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-medium">
                                    {{ expense.expenseCategoryName }}
                                </p>
                                <p class="text-muted-foreground">
                                    {{ expense.farmName }}
                                </p>
                            </div>
                            <Badge variant="secondary">
                                {{ expense.amount }} {{ expense.currency }}
                            </Badge>
                        </div>
                    </article>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    No expenses recorded yet.
                </p>
            </div>

            <div class="space-y-4 rounded-lg border p-4">
                <div class="flex items-center gap-2">
                    <Landmark class="size-4" />
                    <Heading
                        variant="small"
                        title="Recent external transfers"
                        description="Recorded releases and reconciliation status"
                    />
                </div>
                <div v-if="recentTransfers.length > 0" class="space-y-3">
                    <article
                        v-for="transfer in recentTransfers"
                        :key="transfer.id"
                        class="rounded-lg border p-3 text-sm"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-medium">
                                    {{ transfer.directionLabel }} /
                                    {{ transfer.transferType }}
                                </p>
                                <p class="text-muted-foreground">
                                    {{ transfer.farmName }}
                                </p>
                            </div>
                            <Badge variant="secondary">
                                {{ transfer.statusLabel }}
                            </Badge>
                        </div>
                    </article>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    No external transfers recorded yet.
                </p>
            </div>
        </section>
    </div>
</template>
