<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    ClipboardCheck,
    FileText,
    Handshake,
    MessageSquare,
    Sprout,
    Wallet,
} from 'lucide-vue-next';
import { TabsContent, TabsList, TabsRoot, TabsTrigger } from 'reka-ui';
import { computed, nextTick, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InvestorAgreementTerms from '@/components/InvestorAgreementTerms.vue';
import InvestorPortalDecisions from '@/components/InvestorPortalDecisions.vue';
import InvestorPortalFinancialRecords from '@/components/InvestorPortalFinancialRecords.vue';
import InvestorPortalQuestions from '@/components/InvestorPortalQuestions.vue';
import InvestorPortalUpdates from '@/components/InvestorPortalUpdates.vue';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { formatCurrency } from '@/lib/currency';
import { index as investorPortalIndex } from '@/routes/investor-portal';
import { index as statementsIndex } from '@/routes/investor-statements';
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

const currentTeamSlug = computed(() => page.props.currentTeam?.slug ?? '');
const selectedId = ref<number>();
const activeTab = ref('overview');

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
    [currentTeamSlug, () => props.agreements],
    ([slug, agreements], [previousSlug]) => {
        if (
            slug !== previousSlug ||
            !agreements.some((item) => item.id === selectedId.value)
        ) {
            selectedId.value = agreements[0]?.id;
        }
    },
    { immediate: true },
);
watch([currentTeamSlug, () => agreement.value?.id], () => {
    activeTab.value = 'overview';
});

const pendingCount = computed(
    () =>
        agreement.value?.approvalRequests.filter(
            (request) => request.status === 'pending',
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

const financialMetrics = [
    { key: 'released', label: 'Released' },
    { key: 'spent', label: 'Spent' },
    { key: 'balance', label: 'Balance' },
    { key: 'saleNet', label: 'Net sales' },
    { key: 'capitalRecovered', label: 'Capital recovered' },
    { key: 'investorShare', label: 'Investor share' },
] as const;
</script>

<template>
    <div
        class="mx-auto flex w-full max-w-7xl min-w-0 flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8"
    >
        <Head title="Investor Portal" />
        <div class="flex flex-wrap items-end justify-between gap-4">
            <h1 class="text-2xl font-semibold tracking-tight sm:text-3xl">
                Your investments
            </h1>
            <div
                v-if="agreements.length > 1"
                class="grid w-full max-w-sm min-w-0 gap-2 sm:w-auto"
            >
                <Label for="portal-agreement">Agreement</Label>
                <select
                    id="portal-agreement"
                    v-model="selectedId"
                    class="h-10 w-full min-w-0 rounded-md border bg-background px-3 text-sm"
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

        <TabsRoot
            v-if="agreement"
            :key="`${currentTeamSlug}:${agreement.id}`"
            v-model="activeTab"
            class="min-w-0"
        >
            <div
                class="relative isolate overflow-hidden rounded-2xl bg-[#1c493b] px-5 py-6 text-[#f7fcf8] sm:px-8 sm:py-8 dark:bg-[#173c31]"
            >
                <div
                    class="grid gap-6 md:grid-cols-[minmax(0,1fr)_minmax(14rem,18rem)] md:items-center"
                >
                    <div class="min-w-0">
                        <span
                            class="inline-flex rounded-full border border-white/25 px-2.5 py-1 text-xs font-medium"
                            >{{ agreement.statusLabel }}</span
                        >
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
                v-if="pendingCount"
                class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-amber-300/70 bg-amber-50 px-4 py-3 text-amber-950 dark:border-amber-800 dark:bg-amber-950/45 dark:text-amber-100"
            >
                <p class="flex items-center gap-2 text-sm font-medium">
                    <ClipboardCheck
                        class="size-4 shrink-0"
                        aria-hidden="true"
                    />
                    {{ pendingCount }}
                    {{ pendingCount === 1 ? 'request needs' : 'requests need' }}
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
                        v-if="tab.id === 'decisions' && pendingCount"
                        class="shrink-0 rounded-full bg-amber-100 px-1.5 text-xs text-amber-950 dark:bg-amber-900 dark:text-amber-100"
                        >{{ pendingCount }}</span
                    >
                </TabsTrigger>
            </TabsList>

            <TabsContent
                value="overview"
                class="mt-6 space-y-5 focus-visible:ring-2 focus-visible:ring-ring"
            >
                <InvestorAgreementTerms :agreement="agreement" />
                <dl
                    class="grid grid-cols-2 gap-px overflow-hidden rounded-xl border bg-border sm:grid-cols-3"
                >
                    <div
                        v-for="metric in financialMetrics"
                        :key="metric.key"
                        class="min-w-0 bg-card p-3.5 sm:p-4"
                    >
                        <dt class="text-sm text-muted-foreground">
                            {{ metric.label }}
                        </dt>
                        <dd
                            class="mt-1 text-base font-semibold break-words tabular-nums sm:text-lg"
                        >
                            {{
                                formatCurrency(
                                    agreement.summary[metric.key],
                                    agreement.currency,
                                )
                            }}
                        </dd>
                    </div>
                </dl>
                <div v-if="currentTeamSlug" class="flex flex-wrap gap-2">
                    <Button variant="outline" as-child
                        ><Link
                            :href="
                                statementsIndex([currentTeamSlug, agreement.id])
                            "
                            >Investor statements</Link
                        ></Button
                    >
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
                    <Button v-if="currentTeamSlug" variant="outline" as-child
                        ><Link
                            :href="
                                statementsIndex([currentTeamSlug, agreement.id])
                            "
                            >Investor statements</Link
                        ></Button
                    >
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
                <p
                    v-else
                    class="rounded-lg border border-dashed p-5 text-sm text-muted-foreground"
                >
                    No agreement files have been shared yet.
                </p>
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

        <section
            v-else
            class="rounded-xl border border-dashed bg-card px-6 py-10 text-center"
        >
            <Handshake
                class="mx-auto mb-3 size-7 text-muted-foreground"
                aria-hidden="true"
            />
            <h2 class="text-lg font-semibold">No agreements to show</h2>
            <p class="mt-1 text-sm text-muted-foreground">
                Agreements assigned to you will appear here once they are
                active.
            </p>
        </section>
    </div>
</template>
