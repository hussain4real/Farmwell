<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowRight,
    ClipboardCheck,
    FileSpreadsheet,
    Handshake,
    Landmark,
    Sprout,
    TrendingUp,
    Wallet,
} from 'lucide-vue-next';
import { computed } from 'vue';
import EmptyState from '@/components/farm/EmptyState.vue';
import FarmTypeBadge from '@/components/farm/FarmTypeBadge.vue';
import MetricCard from '@/components/farm/MetricCard.vue';
import PageHero from '@/components/farm/PageHero.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatCurrency } from '@/lib/currency';
import { formatInvestorDate } from '@/lib/investor-portal';
import { index as investorPortal } from '@/routes/investor-portal';
import { index as investorStatements } from '@/routes/investor-statements';
import { index as myInvestments } from '@/routes/my-investments';
import type { InvestorPortfolioSummary } from '@/types';

type FarmInvestment = {
    id: number;
    name: string;
    farmType?: string;
    farmTypeLabel?: string;
    teamSlug: string;
    agreements: {
        id: number;
        title: string;
        status?: string;
        statusLabel: string;
        currency: string;
        committed: string;
        funded: string;
        fundingPercent?: number | null;
        released?: string;
        spent?: string;
        capitalRecovered?: string;
        investorShare?: string;
        productionCycleName?: string | null;
        cycleProgressPercent?: number | null;
        pendingDecisionCount?: number;
        latestActivity: { title: string; date: string } | null;
    }[];
};

type PendingDecision = {
    requestId: number;
    agreementId: number;
    agreementTitle: string;
    farmName: string;
    teamSlug: string;
    label: string;
    amount: string;
    currency: string;
    requestedAt: string;
};

const props = defineProps<{
    farms: FarmInvestment[];
    attention: PendingDecision[];
    summary?: InvestorPortfolioSummary;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'My Investments', href: myInvestments.url() }],
    },
});

const farmCount = computed(
    () => props.summary?.farmCount ?? props.farms.length,
);
const agreementCount = computed(
    () =>
        props.summary?.agreementCount ??
        props.farms.reduce((total, farm) => total + farm.agreements.length, 0),
);
const activeAgreementCount = computed(
    () => props.summary?.activeAgreementCount ?? agreementCount.value,
);
const pendingCount = computed(
    () => props.summary?.pendingDecisionCount ?? props.attention.length,
);
const currencyTotals = computed(() => props.summary?.totalsByCurrency ?? []);
</script>

<template>
    <div
        class="@container mx-auto flex w-full max-w-7xl min-w-0 flex-1 flex-col gap-6 px-4 py-6 sm:px-6 lg:p-8"
    >
        <Head title="My Investments" />

        <PageHero
            eyebrow="Investor Portfolio"
            title="My Investments"
            description="Track committed capital, approvals needing your decision, and live updates across every farm you back."
        >
            <template #metrics>
                <div class="px-4 py-3 sm:px-6">
                    <p class="text-xs text-emerald-100/75">Farms</p>
                    <p class="mt-0.5 text-lg font-semibold tabular-nums">
                        {{ farmCount }}
                    </p>
                </div>
                <div class="border-s border-white/15 px-4 py-3 sm:px-6">
                    <p class="text-xs text-emerald-100/75">Agreements</p>
                    <p class="mt-0.5 text-lg font-semibold tabular-nums">
                        {{ agreementCount }}
                    </p>
                </div>
                <div
                    class="border-t border-white/15 px-4 py-3 sm:border-s sm:border-t-0 sm:px-6"
                >
                    <p class="text-xs text-emerald-100/75">Active</p>
                    <p class="mt-0.5 text-lg font-semibold tabular-nums">
                        {{ activeAgreementCount }}
                    </p>
                </div>
                <div
                    class="border-s border-t border-white/15 px-4 py-3 sm:border-t-0 sm:px-6"
                >
                    <p class="text-xs text-emerald-100/75">Pending decisions</p>
                    <p class="mt-0.5 text-lg font-semibold tabular-nums">
                        {{ pendingCount }}
                    </p>
                </div>
            </template>
        </PageHero>

        <section
            v-if="currencyTotals.length"
            aria-label="Portfolio financial totals"
            class="space-y-4"
        >
            <div
                v-for="totals in currencyTotals"
                :key="totals.currency"
                class="space-y-2"
            >
                <p
                    v-if="currencyTotals.length > 1"
                    class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                >
                    {{ totals.currency }} portfolio totals
                </p>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <MetricCard
                        label="Committed capital"
                        :value="
                            formatCurrency(totals.committed, totals.currency)
                        "
                        subtext="Agreed investment allocation"
                        tone="emerald"
                        :icon="Handshake"
                    />
                    <MetricCard
                        label="Recorded funding"
                        :value="formatCurrency(totals.funded, totals.currency)"
                        :subtext="
                            totals.fundingPercent !== null
                                ? `${totals.fundingPercent}% of commitment · ${formatCurrency(totals.released, totals.currency)} released`
                                : `${formatCurrency(totals.released, totals.currency)} released`
                        "
                        :progress="totals.fundingPercent"
                        tone="cyan"
                        :icon="Wallet"
                    />
                    <MetricCard
                        label="Capital recovered"
                        :value="
                            formatCurrency(
                                totals.capitalRecovered,
                                totals.currency,
                            )
                        "
                        :subtext="`${formatCurrency(totals.spent, totals.currency)} approved spend`"
                        tone="amber"
                        :icon="Landmark"
                    />
                    <MetricCard
                        label="Investor profit share"
                        :value="
                            formatCurrency(
                                totals.investorShare,
                                totals.currency,
                            )
                        "
                        :subtext="`${formatCurrency(totals.totalReturned, totals.currency)} total returned`"
                        tone="emerald"
                        :icon="TrendingUp"
                    />
                </div>
            </div>
        </section>

        <section
            aria-labelledby="investments-attention"
            class="overflow-hidden rounded-2xl border bg-card text-card-foreground shadow-xs"
            :class="
                attention.length
                    ? 'border-amber-300/70 dark:border-amber-800/70'
                    : ''
            "
        >
            <div
                class="flex items-center gap-2.5 px-4 py-4 @xl:px-5"
                :class="
                    attention.length
                        ? 'bg-amber-50/70 dark:bg-amber-950/30'
                        : ''
                "
            >
                <ClipboardCheck
                    aria-hidden="true"
                    class="size-5 shrink-0"
                    :class="
                        attention.length
                            ? 'text-amber-700 dark:text-amber-300'
                            : 'text-[#34775c] dark:text-emerald-300'
                    "
                />
                <h2 id="investments-attention" class="text-base font-semibold">
                    Needs your attention
                </h2>
                <Badge v-if="attention.length" variant="secondary">{{
                    attention.length
                }}</Badge>
            </div>
            <ul v-if="attention.length" class="divide-y border-t">
                <li v-for="decision in attention" :key="decision.requestId">
                    <Link
                        :href="
                            investorPortal(decision.teamSlug, {
                                query: {
                                    agreement: decision.agreementId,
                                    request: decision.requestId,
                                },
                            })
                        "
                        class="group flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 py-3.5 hover:bg-muted/60 focus-visible:outline-2 focus-visible:-outline-offset-2 @xl:px-5"
                    >
                        <div class="min-w-0">
                            <p
                                class="font-medium break-words group-hover:underline"
                            >
                                {{ decision.label }} ·
                                {{
                                    formatCurrency(
                                        decision.amount,
                                        decision.currency,
                                    )
                                }}
                            </p>
                            <p
                                class="mt-0.5 text-sm break-words text-muted-foreground"
                            >
                                {{ decision.farmName }} ·
                                {{ decision.agreementTitle }}
                            </p>
                        </div>
                        <span
                            class="inline-flex items-center gap-2 text-sm font-medium text-amber-800 dark:text-amber-200"
                        >
                            <time :datetime="decision.requestedAt">{{
                                formatInvestorDate(decision.requestedAt)
                            }}</time>
                            <ArrowRight aria-hidden="true" class="size-4" />
                        </span>
                    </Link>
                </li>
            </ul>
            <p
                v-else
                class="border-t px-4 py-4 text-sm text-muted-foreground @xl:px-5"
            >
                No decisions are waiting for you.
            </p>
        </section>

        <section aria-labelledby="investment-farms" class="space-y-3">
            <h2
                id="investment-farms"
                class="text-lg font-semibold tracking-tight"
            >
                Farms you invest in
            </h2>
            <div
                v-if="farms.length"
                class="grid items-start gap-5 @3xl:grid-cols-2"
            >
                <article
                    v-for="farm in farms"
                    :key="farm.id"
                    class="min-w-0 overflow-hidden rounded-2xl border bg-card text-card-foreground shadow-xs"
                >
                    <header
                        class="flex flex-wrap items-center justify-between gap-3 border-b bg-muted/40 px-4 py-4 @xl:px-5"
                    >
                        <div class="flex min-w-0 items-center gap-3">
                            <span
                                class="grid size-9 shrink-0 place-items-center rounded-lg bg-[#e4f1e8] text-[#214d40] dark:bg-emerald-900/45 dark:text-emerald-200"
                            >
                                <Sprout aria-hidden="true" class="size-5" />
                            </span>
                            <h3
                                class="min-w-0 text-lg font-semibold break-words"
                            >
                                {{ farm.name }}
                            </h3>
                        </div>
                        <FarmTypeBadge
                            v-if="farm.farmType"
                            :farm-type="farm.farmType"
                            :label="farm.farmTypeLabel"
                        />
                    </header>
                    <ul class="divide-y">
                        <li
                            v-for="agreement in farm.agreements"
                            :key="agreement.id"
                            class="space-y-4 p-4 @xl:p-5"
                        >
                            <div
                                class="flex flex-wrap items-start justify-between gap-2"
                            >
                                <div class="min-w-0 space-y-1">
                                    <h4
                                        class="min-w-0 font-semibold break-words"
                                    >
                                        {{ agreement.title }}
                                    </h4>
                                    <p
                                        v-if="agreement.productionCycleName"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{ agreement.productionCycleName }}
                                        <template
                                            v-if="
                                                agreement.cycleProgressPercent !==
                                                    null &&
                                                agreement.cycleProgressPercent !==
                                                    undefined
                                            "
                                        >
                                            ·
                                            {{
                                                agreement.cycleProgressPercent
                                            }}% cycle progress
                                        </template>
                                    </p>
                                </div>
                                <div
                                    class="flex flex-wrap items-center gap-1.5"
                                >
                                    <Badge
                                        v-if="agreement.pendingDecisionCount"
                                        variant="secondary"
                                        class="bg-amber-100 text-amber-900 dark:bg-amber-950/70 dark:text-amber-200"
                                    >
                                        {{ agreement.pendingDecisionCount }}
                                        pending
                                    </Badge>
                                    <Badge variant="outline">{{
                                        agreement.statusLabel
                                    }}</Badge>
                                </div>
                            </div>

                            <div
                                v-if="
                                    agreement.fundingPercent !== null &&
                                    agreement.fundingPercent !== undefined
                                "
                                class="space-y-1.5"
                            >
                                <div
                                    class="flex items-center justify-between text-xs text-muted-foreground"
                                >
                                    <span>Commitment funded</span>
                                    <span class="font-medium tabular-nums">
                                        {{ agreement.fundingPercent }}%
                                    </span>
                                </div>
                                <div
                                    class="h-1.5 w-full overflow-hidden rounded-full bg-muted"
                                >
                                    <div
                                        class="h-full rounded-full bg-primary transition-all"
                                        :style="{
                                            width: `${Math.min(100, Math.max(0, agreement.fundingPercent))}%`,
                                        }"
                                    />
                                </div>
                            </div>

                            <dl
                                class="grid grid-cols-2 gap-3 rounded-xl bg-muted/50 p-3.5 text-sm"
                            >
                                <div class="min-w-0">
                                    <dt class="text-xs text-muted-foreground">
                                        Committed capital
                                    </dt>
                                    <dd
                                        class="mt-1 font-semibold break-words tabular-nums"
                                    >
                                        {{
                                            formatCurrency(
                                                agreement.committed,
                                                agreement.currency,
                                            )
                                        }}
                                    </dd>
                                </div>
                                <div class="min-w-0">
                                    <dt class="text-xs text-muted-foreground">
                                        Recorded funding
                                    </dt>
                                    <dd
                                        class="mt-1 font-semibold break-words tabular-nums"
                                    >
                                        {{
                                            formatCurrency(
                                                agreement.funded,
                                                agreement.currency,
                                            )
                                        }}
                                    </dd>
                                </div>
                                <div
                                    v-if="
                                        agreement.capitalRecovered !== undefined
                                    "
                                    class="min-w-0"
                                >
                                    <dt class="text-xs text-muted-foreground">
                                        Capital recovered
                                    </dt>
                                    <dd
                                        class="mt-1 font-semibold break-words tabular-nums"
                                    >
                                        {{
                                            formatCurrency(
                                                agreement.capitalRecovered,
                                                agreement.currency,
                                            )
                                        }}
                                    </dd>
                                </div>
                                <div
                                    v-if="agreement.investorShare !== undefined"
                                    class="min-w-0"
                                >
                                    <dt class="text-xs text-muted-foreground">
                                        Investor share
                                    </dt>
                                    <dd
                                        class="mt-1 font-semibold break-words tabular-nums"
                                    >
                                        {{
                                            formatCurrency(
                                                agreement.investorShare,
                                                agreement.currency,
                                            )
                                        }}
                                    </dd>
                                </div>
                            </dl>

                            <div class="space-y-1 text-sm">
                                <p class="text-xs text-muted-foreground">
                                    Latest shared field activity
                                </p>
                                <p
                                    v-if="agreement.latestActivity"
                                    class="flex flex-wrap gap-x-2 break-words"
                                >
                                    <span class="font-medium">{{
                                        agreement.latestActivity.title
                                    }}</span>
                                    <time
                                        :datetime="
                                            agreement.latestActivity.date
                                        "
                                        class="text-muted-foreground"
                                        >{{
                                            formatInvestorDate(
                                                agreement.latestActivity.date,
                                            )
                                        }}</time
                                    >
                                </p>
                                <p v-else class="text-muted-foreground">
                                    Awaiting first shared field activity
                                </p>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <Button variant="default" size="sm" as-child>
                                    <Link
                                        :href="
                                            investorPortal(farm.teamSlug, {
                                                query: {
                                                    agreement: agreement.id,
                                                },
                                            })
                                        "
                                        :aria-label="`Open investor portal for ${agreement.title}`"
                                        class="min-h-11 sm:min-h-9"
                                    >
                                        Open investor portal
                                        <ArrowRight
                                            aria-hidden="true"
                                            class="size-4"
                                        />
                                    </Link>
                                </Button>
                                <Button variant="outline" size="sm" as-child>
                                    <Link
                                        :href="
                                            investorStatements([
                                                farm.teamSlug,
                                                agreement.id,
                                            ])
                                        "
                                        class="min-h-11 sm:min-h-9"
                                    >
                                        <FileSpreadsheet
                                            aria-hidden="true"
                                            class="size-4"
                                        />
                                        Statements
                                    </Link>
                                </Button>
                            </div>
                        </li>
                    </ul>
                </article>
            </div>
            <EmptyState
                v-else
                :icon="Handshake"
                title="No shared investments yet"
                description="Farms appear here when an investor agreement is shared with you."
            />
        </section>
    </div>
</template>
