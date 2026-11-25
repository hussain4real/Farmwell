<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowRight, ClipboardCheck, Sprout } from 'lucide-vue-next';
import { Badge } from '@/components/ui/badge';
import { formatCurrency } from '@/lib/currency';
import { formatInvestorDate } from '@/lib/investor-portal';
import { index as investorPortal } from '@/routes/investor-portal';
import { index as myInvestments } from '@/routes/my-investments';

type FarmInvestment = {
    id: number;
    name: string;
    teamSlug: string;
    agreements: {
        id: number;
        title: string;
        statusLabel: string;
        currency: string;
        committed: string;
        funded: string;
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

defineProps<{ farms: FarmInvestment[]; attention: PendingDecision[] }>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'My Investments', href: myInvestments.url() }],
    },
});
</script>

<template>
    <div
        class="@container mx-auto flex w-full max-w-7xl min-w-0 flex-1 flex-col gap-7 px-4 py-6 sm:px-6 lg:p-8"
    >
        <Head title="My Investments" />
        <header
            class="rounded-2xl bg-[#214d40] px-5 py-6 text-white @xl:px-7 @xl:py-8 dark:bg-[#173f33]"
        >
            <h1 class="text-3xl font-semibold tracking-tight">
                My Investments
            </h1>
        </header>

        <section
            aria-labelledby="investments-attention"
            class="overflow-hidden rounded-xl border bg-card text-card-foreground"
        >
            <div class="flex items-center gap-2.5 px-4 py-4 @xl:px-5">
                <ClipboardCheck
                    aria-hidden="true"
                    class="size-5 shrink-0 text-[#34775c] dark:text-emerald-300"
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
                        class="group flex flex-wrap items-center justify-between gap-x-4 gap-y-2 px-4 py-3 hover:bg-muted/60 focus-visible:outline-2 focus-visible:-outline-offset-2 @xl:px-5"
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
                            class="flex items-center gap-2 text-sm text-muted-foreground"
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
                class="grid items-start gap-4 @3xl:grid-cols-2"
            >
                <article
                    v-for="farm in farms"
                    :key="farm.id"
                    class="min-w-0 overflow-hidden rounded-xl border bg-card text-card-foreground"
                >
                    <header
                        class="flex items-center gap-3 border-b bg-muted/40 px-4 py-4 @xl:px-5"
                    >
                        <span
                            class="grid size-9 shrink-0 place-items-center rounded-lg bg-[#e4f1e8] text-[#214d40] dark:bg-emerald-900/45 dark:text-emerald-200"
                        >
                            <Sprout aria-hidden="true" class="size-5" />
                        </span>
                        <h3 class="min-w-0 text-lg font-semibold break-words">
                            {{ farm.name }}
                        </h3>
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
                                <h4 class="min-w-0 font-medium break-words">
                                    {{ agreement.title }}
                                </h4>
                                <Badge variant="outline">{{
                                    agreement.statusLabel
                                }}</Badge>
                            </div>
                            <dl
                                class="grid grid-cols-2 gap-3 rounded-lg bg-muted/50 p-3 text-sm"
                            >
                                <div class="min-w-0">
                                    <dt class="text-muted-foreground">
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
                                    <dt class="text-muted-foreground">
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
                            </dl>
                            <div class="space-y-1 text-sm">
                                <p class="text-muted-foreground">
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
                                <p v-else>
                                    Awaiting first shared field activity
                                </p>
                            </div>
                            <Link
                                :href="
                                    investorPortal(farm.teamSlug, {
                                        query: { agreement: agreement.id },
                                    })
                                "
                                :aria-label="`Open investor portal for ${agreement.title}`"
                                class="inline-flex min-h-11 items-center gap-2 rounded-md border px-3 text-sm font-medium hover:bg-muted focus-visible:outline-2 focus-visible:outline-offset-2"
                                >Open investor portal
                                <ArrowRight aria-hidden="true" class="size-4"
                            /></Link>
                        </li>
                    </ul>
                </article>
            </div>
            <p
                v-else
                class="rounded-xl border border-dashed p-5 text-sm text-muted-foreground"
            >
                Farms appear here when an investor agreement is shared with you.
            </p>
        </section>
    </div>
</template>
