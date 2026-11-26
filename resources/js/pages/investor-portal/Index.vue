<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    ClipboardCheck,
    Coins,
    FileText,
    Handshake,
    Landmark,
    MessageSquare,
    PiggyBank,
    Receipt,
    Sprout,
    TrendingUp,
    Wallet,
} from 'lucide-vue-next';
import { TabsContent, TabsList, TabsRoot, TabsTrigger } from 'reka-ui';
import { computed, nextTick, ref, watch } from 'vue';
import EmptyState from '@/components/farm/EmptyState.vue';
import FarmTypeBadge from '@/components/farm/FarmTypeBadge.vue';
import MetricCard from '@/components/farm/MetricCard.vue';
import Heading from '@/components/Heading.vue';
import InvestorAgreementTerms from '@/components/InvestorAgreementTerms.vue';
import InvestorCycleHealthCard from '@/components/InvestorCycleHealthCard.vue';
import InvestorPortalDecisions from '@/components/InvestorPortalDecisions.vue';
import InvestorPortalFinancialRecords from '@/components/InvestorPortalFinancialRecords.vue';
import InvestorPortalQuestions from '@/components/InvestorPortalQuestions.vue';
import InvestorPortalUpdates from '@/components/InvestorPortalUpdates.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { formatCurrency } from '@/lib/currency';
import { index as investorPortalIndex } from '@/routes/investor-portal';
import { index as statementsIndex } from '@/routes/investor-statements';
import { index as myInvestments } from '@/routes/my-investments';
import type { InvestorPortalAgreement, Team } from '@/types';

const props = defineProps<{ agreements: InvestorPortalAgreement[] }>();
const page = usePage();

defineOptions({
    layout: (props: { currentTeam?: Team | null }) => ({
        breadcrumbs: [
            {
                title: 'Investor Portal',
                href: props.currentTeam
                    ? investorPortalIndex(props.currentTeam.slug)
                    : '/',
            },
        ],
    }),
});

const tabs = [
    { id: 'overview', title: 'Overview', icon: Handshake },
    { id: 'decisions', title: 'Decisions', icon: ClipboardCheck },
    { id: 'financials', title: 'Financials', icon: Wallet },
    { id: 'updates', title: 'Farm updates', icon: Sprout },
    { id: 'documents', title: 'Documents', icon: FileText },
    { id: 'questions', title: 'Questions', icon: MessageSquare },
] as const;

type TabId = (typeof tabs)[number]['id'];
const validTabs = new Set<string>(tabs.map((tab) => tab.id));

const currentTeamSlug = computed(() => page.props.currentTeam?.slug ?? '');
const linkQuery = computed(
    () => new URLSearchParams(page.url.split('?')[1]?.split('#')[0] ?? ''),
);
const selectedId = ref<number>();
const activeTab = ref<TabId>('overview');

async function openSection(tab: 'decisions' | 'documents'): Promise<void> {
    activeTab.value = tab;
    await nextTick();
    document.getElementById(`investor-${tab}`)?.focus();
}

const agreement = computed(
    () =>
        props.agreements.find((item) => item.id === selectedId.value) ??
        props.agreements[0],
);

watch(
    [currentTeamSlug, () => props.agreements, () => page.url],
    ([slug, agreements], [previousSlug]) => {
        const linkedAgreementId = linkQuery.value.get('agreement');
        const matchedLinked = linkedAgreementId
            ? agreements.find((item) => String(item.id) === linkedAgreementId)
            : undefined;

        if (matchedLinked) {
            selectedId.value = matchedLinked.id;

            return;
        }

        if (
            slug !== previousSlug ||
            !agreements.some((item) => item.id === selectedId.value)
        ) {
            selectedId.value = agreements[0]?.id;
        }
    },
    { immediate: true },
);

watch(
    [currentTeamSlug, () => agreement.value?.id, () => page.url],
    () => {
        const linkedRequest =
            String(agreement.value?.id) === linkQuery.value.get('agreement')
                ? agreement.value?.approvalRequests.find(
                      (item) =>
                          String(item.id) === linkQuery.value.get('request'),
                  )
                : undefined;

        if (linkedRequest) {
            activeTab.value = 'decisions';

            if (typeof document !== 'undefined') {
                void nextTick(() =>
                    document
                        .getElementById(`investor-request-${linkedRequest.id}`)
                        ?.focus(),
                );
            }

            return;
        }

        const requestedTab = linkQuery.value.get('tab');
        activeTab.value =
            requestedTab && validTabs.has(requestedTab)
                ? (requestedTab as TabId)
                : 'overview';
    },
    { immediate: true },
);

const actionNeededCount = computed(
    () =>
        agreement.value?.approvalRequests.filter(
            (request) =>
                request.status === 'pending' ||
                request.status === 'clarification_requested',
        ).length ?? 0,
);

const fundingPercent = computed(() => {
    const item = agreement.value;

    if (!item || !(item.amountCommittedMinor > 0)) {
        return null;
    }

    return Math.round(
        (item.amountFundedMinor / item.amountCommittedMinor) * 100,
    );
});

const capitalRecoveryPercent = computed(() => {
    const item = agreement.value;

    if (!item || !(item.amountFundedMinor > 0)) {
        return null;
    }

    return Math.min(
        100,
        Math.round(
            ((item.summary.capitalRecoveredMinor ?? 0) /
                item.amountFundedMinor) *
                100,
        ),
    );
});

const financialMetrics = computed(() => {
    const item = agreement.value;

    if (!item) {
        return [];
    }

    return [
        {
            key: 'released' as const,
            label: 'Released',
            icon: Landmark,
            tone: 'emerald' as const,
            subtext: 'Externally released tranches',
            progress: null,
        },
        {
            key: 'spent' as const,
            label: 'Spent',
            icon: Receipt,
            tone: 'amber' as const,
            subtext: 'Approved farm expenditure',
            progress: null,
        },
        {
            key: 'balance' as const,
            label: 'Balance',
            icon: Wallet,
            tone: 'cyan' as const,
            subtext: 'Released less approved spend',
            progress: null,
        },
        {
            key: 'saleNet' as const,
            label: 'Net sales',
            icon: Coins,
            tone: 'emerald' as const,
            subtext: 'Approved net buyer proceeds',
            progress: null,
        },
        {
            key: 'capitalRecovered' as const,
            label: 'Capital recovered',
            icon: PiggyBank,
            tone: 'cyan' as const,
            subtext:
                capitalRecoveryPercent.value !== null
                    ? `${capitalRecoveryPercent.value}% of funded capital`
                    : 'Capital recovery progress',
            progress: capitalRecoveryPercent.value,
        },
        {
            key: 'investorShare' as const,
            label: 'Investor share',
            icon: TrendingUp,
            tone: 'emerald' as const,
            subtext: `${item.investorProfitSharePercentage}% agreed profit share`,
            progress: null,
        },
    ];
});
</script>

<template>
    <div
        class="mx-auto flex w-full max-w-7xl min-w-0 flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8"
    >
        <Head title="Investor Portal" />
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p
                    class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                >
                    Investor Portal
                </p>
                <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                    Your investments
                </h1>
            </div>
            <div class="flex flex-wrap items-end gap-3">
                <Button variant="outline" as-child>
                    <Link :href="myInvestments()">All investments</Link>
                </Button>
                <div
                    v-if="agreements.length > 1"
                    class="grid w-full max-w-sm min-w-0 gap-1.5 sm:w-auto"
                >
                    <Label for="portal-agreement">Agreement</Label>
                    <select
                        id="portal-agreement"
                        v-model="selectedId"
                        class="h-10 w-full min-w-0 rounded-md border bg-background ps-3 pe-8 text-sm"
                    >
                        <option
                            v-for="item in agreements"
                            :key="item.id"
                            :value="item.id"
                        >
                            {{ item.title }}
                        </option>
                    </select>
                </div>
            </div>
        </div>

        <TabsRoot
            v-if="agreement"
            :key="`${currentTeamSlug}:${agreement.id}`"
            v-model="activeTab"
            class="min-w-0"
        >
            <div
                class="relative isolate overflow-hidden rounded-2xl border border-[#245c4b]/30 bg-gradient-to-br from-[#1b4d3e] via-[#1f5948] to-[#163f33] px-5 py-6 text-[#f7fcf8] shadow-sm sm:px-8 sm:py-8 dark:border-emerald-900/50 dark:from-[#13352b] dark:via-[#163e32] dark:to-[#0e2a21]"
            >
                <div
                    class="grid gap-6 md:grid-cols-[minmax(0,1fr)_minmax(14rem,18rem)] md:items-center"
                >
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="inline-flex rounded-full border border-white/25 px-2.5 py-1 text-xs font-medium"
                                >{{ agreement.statusLabel }}</span
                            >
                            <FarmTypeBadge
                                v-if="agreement.farmType"
                                :farm-type="agreement.farmType"
                                :label="agreement.farmTypeLabel"
                            />
                        </div>
                        <h2
                            class="mt-3 text-2xl leading-tight font-semibold tracking-tight break-words sm:text-3xl"
                        >
                            {{ agreement.farmName }}
                        </h2>
                        <p
                            v-if="agreement.title !== agreement.farmName"
                            class="mt-2 max-w-xl text-sm leading-relaxed text-[#dbede3] sm:text-base"
                        >
                            {{ agreement.title }}
                        </p>
                        <p
                            v-if="agreement.productionCycleName"
                            class="mt-1 text-sm text-[#dbede3]"
                        >
                            {{ agreement.productionCycleName }}
                        </p>
                    </div>
                    <div
                        v-if="fundingPercent !== null"
                        class="min-w-0 border-t border-white/20 pt-4 md:border-s md:border-t-0 md:ps-6 md:pt-0"
                    >
                        <p class="text-sm text-[#dbede3]">Commitment funded</p>
                        <p class="mt-1 text-3xl font-semibold tabular-nums">
                            {{ fundingPercent }}%
                        </p>
                        <progress
                            aria-label="Share of capital commitment funded"
                            :value="Math.max(0, Math.min(fundingPercent, 100))"
                            max="100"
                            class="mt-3 block h-1.5 w-full appearance-none overflow-hidden rounded-full [&::-moz-progress-bar]:rounded-full [&::-moz-progress-bar]:bg-[#aee2bb] [&::-webkit-progress-bar]:bg-white/20 [&::-webkit-progress-value]:rounded-full [&::-webkit-progress-value]:bg-[#aee2bb]"
                        />
                    </div>
                </div>
            </div>

            <div
                v-if="actionNeededCount"
                class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-300/70 bg-amber-50 px-4 py-3 text-amber-950 dark:border-amber-800 dark:bg-amber-950/45 dark:text-amber-100"
            >
                <p class="flex items-center gap-2 text-sm font-medium">
                    <ClipboardCheck
                        class="size-4 shrink-0"
                        aria-hidden="true"
                    />
                    {{ actionNeededCount }}
                    {{
                        actionNeededCount === 1
                            ? 'request needs'
                            : 'requests need'
                    }}
                    your decision
                </p>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    @click="openSection('decisions')"
                >
                    Review requests
                    <ArrowRight class="size-4" aria-hidden="true" />
                </Button>
            </div>

            <TabsList
                aria-label="Agreement sections"
                class="mt-4 grid grid-cols-2 gap-1 rounded-xl border bg-muted/40 p-1.5 sm:grid-cols-3 xl:grid-cols-6"
            >
                <TabsTrigger
                    v-for="tab in tabs"
                    :key="tab.id"
                    :value="tab.id"
                    class="inline-flex min-h-10 min-w-0 items-center justify-center gap-1.5 rounded-md px-2 py-1.5 text-sm font-medium text-muted-foreground hover:bg-background hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none data-[state=active]:bg-card data-[state=active]:text-foreground data-[state=active]:shadow-sm"
                >
                    <component
                        :is="tab.icon"
                        aria-hidden="true"
                        class="size-4 shrink-0"
                    />
                    {{ tab.title }}
                    <span
                        v-if="tab.id === 'decisions' && actionNeededCount"
                        class="shrink-0 rounded-full bg-amber-100 px-1.5 text-xs text-amber-950 dark:bg-amber-900 dark:text-amber-100"
                        >{{ actionNeededCount }}</span
                    >
                    <span
                        v-else-if="
                            tab.id === 'questions' && agreement.comments.length
                        "
                        class="shrink-0 rounded-full bg-muted px-1.5 text-xs text-muted-foreground"
                        >{{ agreement.comments.length }}</span
                    >
                </TabsTrigger>
            </TabsList>

            <TabsContent
                value="overview"
                class="mt-6 space-y-6 focus-visible:ring-2 focus-visible:ring-ring"
            >
                <InvestorAgreementTerms :agreement="agreement" />

                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <MetricCard
                        v-for="metric in financialMetrics"
                        :key="metric.key"
                        :label="metric.label"
                        :value="
                            formatCurrency(
                                agreement.summary[metric.key],
                                agreement.currency,
                            )
                        "
                        :subtext="metric.subtext"
                        :icon="metric.icon"
                        :tone="metric.tone"
                        :progress="metric.progress"
                    />
                </div>

                <InvestorCycleHealthCard
                    v-if="agreement.cycleSnapshot"
                    :snapshot="agreement.cycleSnapshot"
                />

                <div v-if="currentTeamSlug" class="flex flex-wrap gap-2">
                    <Button variant="outline" as-child>
                        <Link
                            :href="
                                statementsIndex([currentTeamSlug, agreement.id])
                            "
                            >Investor statements</Link
                        >
                    </Button>
                    <Button
                        v-if="agreement.documents.length"
                        variant="ghost"
                        type="button"
                        @click="openSection('documents')"
                        >View agreement files</Button
                    >
                </div>
            </TabsContent>

            <TabsContent
                value="decisions"
                class="mt-6 focus-visible:ring-2 focus-visible:ring-ring"
            >
                <InvestorPortalDecisions
                    :requests="agreement.approvalRequests"
                    :current-team-slug="currentTeamSlug"
                    :agreement-id="agreement.id"
                />
            </TabsContent>

            <TabsContent
                value="financials"
                class="mt-6 focus-visible:ring-2 focus-visible:ring-ring"
            >
                <InvestorPortalFinancialRecords :agreement="agreement" />
            </TabsContent>

            <TabsContent
                value="updates"
                class="mt-6 focus-visible:ring-2 focus-visible:ring-ring"
            >
                <InvestorPortalUpdates :agreement="agreement" />
            </TabsContent>

            <TabsContent
                value="documents"
                class="mt-6 space-y-4 focus-visible:ring-2 focus-visible:ring-ring"
            >
                <div
                    id="investor-documents"
                    tabindex="-1"
                    class="flex flex-wrap items-start justify-between gap-3 focus:outline-none"
                >
                    <Heading
                        variant="small"
                        title="Agreement documents"
                        description="Download shared files or open investor statements."
                    />
                    <Button v-if="currentTeamSlug" variant="outline" as-child>
                        <Link
                            :href="
                                statementsIndex([currentTeamSlug, agreement.id])
                            "
                            >Investor statements</Link
                        >
                    </Button>
                </div>
                <ul
                    v-if="agreement.documents.length"
                    class="divide-y overflow-hidden rounded-xl border bg-card"
                >
                    <li
                        v-for="document in agreement.documents"
                        :key="document.id"
                    >
                        <a
                            :href="document.downloadUrl"
                            class="flex items-center gap-3 px-4 py-3 text-sm hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-inset"
                        >
                            <FileText
                                class="size-4 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <span class="min-w-0 break-all">{{
                                document.fileName
                            }}</span>
                            <span class="ms-auto underline underline-offset-4"
                                >Download</span
                            >
                        </a>
                    </li>
                </ul>
                <EmptyState
                    v-else
                    :icon="FileText"
                    title="No agreement files shared yet"
                    description="Signed agreements and supporting documents uploaded by the farm team will appear here."
                />
            </TabsContent>

            <TabsContent
                value="questions"
                class="mt-6 focus-visible:ring-2 focus-visible:ring-ring"
            >
                <InvestorPortalQuestions
                    :agreement="agreement"
                    :current-team-slug="currentTeamSlug"
                />
            </TabsContent>
        </TabsRoot>

        <EmptyState
            v-else
            :icon="Handshake"
            title="No agreements to show"
            description="Agreements assigned to you will appear here once they are active."
        />
    </div>
</template>
