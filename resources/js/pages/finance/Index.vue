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
import MetricCard from '@/components/farm/MetricCard.vue';
import PageHero from '@/components/farm/PageHero.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCurrency } from '@/lib/currency';
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
const investorMode = computed(
    () => page.props.currentTeamFeatures?.investorMode === true,
);
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
            visible: true,
        },
        {
            title: 'Expenses',
            href: expensesIndex(currentTeamSlug.value).url,
            description: 'Expense records, categories, receipts, and variance',
            icon: ReceiptText,
            visible: true,
        },
        {
            title: 'Funding phases',
            href: fundingPhasesIndex(currentTeamSlug.value).url,
            description: 'Recorded releases, milestones, and carry-forward',
            icon: Banknote,
            visible: investorMode.value,
        },
        {
            title: 'External transfers',
            href: externalTransfersIndex(currentTeamSlug.value).url,
            description: 'Proof, references, and reconciliation records',
            icon: Landmark,
            visible: investorMode.value,
        },
    ].filter((item) => item.visible);
});
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Head title="Finance" />

        <PageHero
            eyebrow="Farm Accounting"
            title="Finance & budgets"
            description="Budgets, expenses, and variance tracking in your team’s default currency."
        />

        <section class="grid grid-cols-2 gap-4 xl:grid-cols-4">
            <MetricCard
                label="Budgeted"
                :value="formatCurrency(summary.budgeted, props.currency)"
                :icon="ChartNoAxesCombined"
            />
            <MetricCard
                label="Spent"
                :value="formatCurrency(summary.spent, props.currency)"
                :icon="ReceiptText"
                tone="amber"
            />
            <MetricCard
                v-if="investorMode"
                label="Released"
                :value="formatCurrency(summary.released, props.currency)"
                :icon="Banknote"
                tone="emerald"
            />
            <MetricCard
                v-if="investorMode"
                label="Carry-forward"
                :value="
                    formatCurrency(carryForward.carryForward, props.currency)
                "
                :icon="CircleDollarSign"
            />
            <MetricCard
                v-if="!investorMode"
                label="Remaining budget"
                :value="formatCurrency(variance.variance, props.currency)"
                :icon="CircleDollarSign"
                tone="emerald"
            />
            <MetricCard
                v-if="!investorMode"
                label="Recorded expenses"
                :value="summary.expenses"
                :icon="ReceiptText"
            />
        </section>

        <section
            class="grid gap-4"
            :class="investorMode ? 'xl:grid-cols-4' : 'md:grid-cols-2'"
        >
            <Link
                v-for="workspace in workspaceLinks"
                :key="workspace.title"
                :href="workspace.href"
                class="rounded-2xl border bg-card p-5 transition-colors hover:bg-accent/50"
            >
                <div class="flex items-start gap-3">
                    <span
                        class="flex size-9 shrink-0 items-center justify-center rounded-xl bg-accent text-accent-foreground"
                    >
                        <component :is="workspace.icon" class="size-4" />
                    </span>
                    <div>
                        <h3 class="font-semibold">{{ workspace.title }}</h3>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ workspace.description }}
                        </p>
                    </div>
                </div>
            </Link>
        </section>

        <section
            v-if="permissions.canManageFinance && currentTeamSlug"
            class="space-y-4 rounded-2xl border bg-card p-5"
        >
            <div class="flex items-center gap-2">
                <Landmark class="size-4 text-primary" />
                <Heading
                    variant="small"
                    title="Default currency"
                    description="Copied onto new budget, expense, inventory, and sales records"
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
            <div class="space-y-4 rounded-2xl border bg-card p-5">
                <div class="flex items-center gap-2">
                    <ChartNoAxesCombined class="size-4 text-primary" />
                    <Heading
                        variant="small"
                        title="Variance by category"
                        description="Budgeted, spent, and remaining balance"
                    />
                </div>

                <div v-if="variance.byCategory.length > 0" class="space-y-2.5">
                    <article
                        v-for="category in variance.byCategory"
                        :key="category.categoryId"
                        class="space-y-2 rounded-xl border p-3 text-sm"
                        :class="
                            category.balanceMinor < 0
                                ? 'border-rose-300/70 bg-rose-50/40 dark:border-rose-900/60 dark:bg-rose-950/20'
                                : ''
                        "
                    >
                        <div
                            class="grid gap-1.5 sm:grid-cols-2 lg:grid-cols-[minmax(0,1fr)_auto_auto_auto] lg:items-center lg:gap-4"
                        >
                            <p class="font-medium">
                                {{ category.categoryName }}
                            </p>
                            <p class="whitespace-nowrap tabular-nums">
                                <span class="text-xs text-muted-foreground"
                                    >Budgeted</span
                                >
                                {{
                                    formatCurrency(
                                        category.budgeted,
                                        props.currency,
                                    )
                                }}
                            </p>
                            <p class="whitespace-nowrap tabular-nums">
                                <span class="text-xs text-muted-foreground"
                                    >Spent</span
                                >
                                {{
                                    formatCurrency(
                                        category.spent,
                                        props.currency,
                                    )
                                }}
                            </p>
                            <p
                                class="whitespace-nowrap tabular-nums"
                                :class="
                                    category.balanceMinor < 0
                                        ? 'font-medium text-rose-700 dark:text-rose-300'
                                        : ''
                                "
                            >
                                <span class="text-xs text-muted-foreground"
                                    >Balance</span
                                >
                                {{
                                    formatCurrency(
                                        category.balance,
                                        props.currency,
                                    )
                                }}
                            </p>
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
                                    width: `${category.budgetedMinor > 0 ? Math.min(100, Math.round((category.spentMinor / category.budgetedMinor) * 100)) : category.spentMinor > 0 ? 100 : 0}%`,
                                }"
                            />
                        </div>
                    </article>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    No expense categories yet.
                </p>
            </div>

            <div class="space-y-4 rounded-2xl border bg-card p-5">
                <div class="flex items-center gap-2">
                    <ReceiptText class="size-4 text-primary" />
                    <Heading
                        variant="small"
                        title="Recent expenses"
                        description="Latest recorded farm spending"
                    />
                </div>
                <div v-if="recentExpenses.length > 0" class="space-y-2.5">
                    <article
                        v-for="expense in recentExpenses"
                        :key="expense.id"
                        class="rounded-xl border p-3 text-sm"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-medium">
                                    {{ expense.expenseCategoryName }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ expense.farmName }}
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
                    </article>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    No expenses recorded yet.
                </p>
            </div>
        </section>

        <section v-if="investorMode" class="grid gap-6 xl:grid-cols-2">
            <div class="space-y-4 rounded-2xl border bg-card p-5">
                <div class="flex items-center gap-2">
                    <Banknote class="size-4 text-primary" />
                    <Heading
                        variant="small"
                        title="Carry-forward"
                        description="Release less spend across funding phases"
                    />
                </div>
                <div v-if="carryForward.phases.length > 0" class="space-y-2.5">
                    <article
                        v-for="phase in carryForward.phases"
                        :key="phase.phaseId"
                        class="rounded-xl border p-3 text-sm"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <p class="font-medium">{{ phase.phaseName }}</p>
                            <Badge variant="secondary" class="tabular-nums">
                                {{
                                    formatCurrency(
                                        phase.carryForward,
                                        props.currency,
                                    )
                                }}
                            </Badge>
                        </div>
                        <p class="mt-1.5 text-xs text-muted-foreground">
                            Released
                            {{ formatCurrency(phase.released, props.currency) }}
                            / spent
                            {{ formatCurrency(phase.spent, props.currency) }}
                        </p>
                    </article>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    No funding phases yet.
                </p>
            </div>

            <div class="space-y-4 rounded-2xl border bg-card p-5">
                <div class="flex items-center gap-2">
                    <Landmark class="size-4 text-primary" />
                    <Heading
                        variant="small"
                        title="Recent external transfers"
                        description="Recorded releases and reconciliation status"
                    />
                </div>
                <div v-if="recentTransfers.length > 0" class="space-y-2.5">
                    <article
                        v-for="transfer in recentTransfers"
                        :key="transfer.id"
                        class="rounded-xl border p-3 text-sm"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-medium">
                                    {{ transfer.directionLabel }} /
                                    {{ transfer.transferType }}
                                </p>
                                <p class="text-xs text-muted-foreground">
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
