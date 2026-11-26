<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    CheckCircle2,
    Clock,
    HelpCircle,
    Paperclip,
    ShieldCheck,
    XCircle,
} from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';
import EmptyState from '@/components/farm/EmptyState.vue';
import MetricCard from '@/components/farm/MetricCard.vue';
import PageHero from '@/components/farm/PageHero.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCurrency } from '@/lib/currency';
import { formatInvestorDate } from '@/lib/investor-portal';
import { dashboard } from '@/routes';
import { index as investorsIndex } from '@/routes/investors';
import { index as approvalsIndex } from '@/routes/investors/approvals';
import { store as storeDecision } from '@/routes/investors/approvals/decisions';
import type {
    InvestorApprovalRequest,
    InvestorOptions,
    InvestorPermissions,
    Team,
} from '@/types';

type Props = {
    permissions: InvestorPermissions;
    approvalRequests: InvestorApprovalRequest[];
    counts?: {
        total: number;
        pending: number;
        clarificationRequested: number;
        approved: number;
        rejected: number;
    };
    options: Pick<InvestorOptions, 'approvalRequestStatuses'>;
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
                title: 'Investors',
                href: props.currentTeam
                    ? investorsIndex(props.currentTeam.slug)
                    : '/',
            },
            {
                title: 'Approvals',
                href: props.currentTeam
                    ? approvalsIndex(props.currentTeam.slug)
                    : '/',
            },
        ],
    }),
});

type DecisionValue = 'approved' | 'rejected' | 'clarification_requested';

const currentTeam = computed(() => page.props.currentTeam as Team | null);
const currentTeamSlug = computed(() => currentTeam.value?.slug ?? '');
const filter = ref<'all' | 'pending' | 'clarification' | 'history'>('all');
const selectedDecisions = reactive<Record<number, DecisionValue | ''>>({});

const summaryCounts = computed(() => ({
    total: props.counts?.total ?? props.approvalRequests.length,
    pending:
        props.counts?.pending ??
        props.approvalRequests.filter((r) => r.status === 'pending').length,
    clarificationRequested:
        props.counts?.clarificationRequested ??
        props.approvalRequests.filter(
            (r) => r.status === 'clarification_requested',
        ).length,
    approved:
        props.counts?.approved ??
        props.approvalRequests.filter((r) => r.status === 'approved').length,
    rejected:
        props.counts?.rejected ??
        props.approvalRequests.filter((r) => r.status === 'rejected').length,
}));

const visibleRequests = computed(() => {
    if (filter.value === 'pending') {
        return props.approvalRequests.filter((r) => r.status === 'pending');
    }

    if (filter.value === 'clarification') {
        return props.approvalRequests.filter(
            (r) => r.status === 'clarification_requested',
        );
    }

    if (filter.value === 'history') {
        return props.approvalRequests.filter(
            (r) =>
                r.status !== 'pending' &&
                r.status !== 'clarification_requested',
        );
    }

    return props.approvalRequests;
});

function isActionableStatus(status: string): boolean {
    return status === 'pending' || status === 'clarification_requested';
}

function canTeamDecide(request: InvestorApprovalRequest): boolean {
    return (
        props.permissions.canManageApprovalRequests &&
        Boolean(currentTeamSlug.value) &&
        request.requestType !== 'funding_release' &&
        isActionableStatus(request.status)
    );
}

function statusBadgeClass(status: string): string {
    switch (status) {
        case 'approved':
            return 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800/60 dark:bg-emerald-950/60 dark:text-emerald-200';
        case 'rejected':
            return 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-800/60 dark:bg-rose-950/60 dark:text-rose-200';
        case 'clarification_requested':
            return 'border-cyan-200 bg-cyan-50 text-cyan-800 dark:border-cyan-800/60 dark:bg-cyan-950/60 dark:text-cyan-200';
        default:
            return 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800/60 dark:bg-amber-950/60 dark:text-amber-200';
    }
}

function decisionOptionsFor(request: InvestorApprovalRequest): Array<{
    value: DecisionValue;
    label: string;
    icon: typeof CheckCircle2;
    activeClass: string;
}> {
    const items: Array<{
        value: DecisionValue;
        label: string;
        icon: typeof CheckCircle2;
        activeClass: string;
    }> = [
        {
            value: 'approved',
            label: 'Approve',
            icon: CheckCircle2,
            activeClass:
                'border-emerald-600 bg-emerald-600 text-white dark:bg-emerald-600',
        },
    ];

    if (request.status === 'pending') {
        items.push({
            value: 'clarification_requested',
            label: 'Request clarification',
            icon: HelpCircle,
            activeClass:
                'border-cyan-600 bg-cyan-600 text-white dark:bg-cyan-600',
        });
    }

    items.push({
        value: 'rejected',
        label: 'Reject',
        icon: XCircle,
        activeClass: 'border-rose-600 bg-rose-600 text-white dark:bg-rose-600',
    });

    return items;
}
</script>

<template>
    <div
        class="mx-auto flex w-full max-w-7xl min-w-0 flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8"
    >
        <Head title="Investor Approvals" />

        <PageHero
            eyebrow="Investor Governance"
            title="Approvals Queue"
            description="Review pending investor decisions for funding releases, expenses, budget overruns, and plan changes."
        >
            <template #actions>
                <Button
                    v-if="currentTeamSlug"
                    variant="secondary"
                    as-child
                    class="min-h-11 sm:min-h-9"
                >
                    <Link :href="investorsIndex(currentTeamSlug)">
                        <ArrowLeft class="size-4" aria-hidden="true" />
                        Investors &amp; agreements
                    </Link>
                </Button>
            </template>
        </PageHero>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <MetricCard
                label="Pending"
                :value="summaryCounts.pending"
                subtext="Awaiting initial review"
                tone="amber"
                :icon="Clock"
            />
            <MetricCard
                label="Needs clarification"
                :value="summaryCounts.clarificationRequested"
                subtext="Follow-up requested"
                tone="cyan"
                :icon="HelpCircle"
            />
            <MetricCard
                label="Approved"
                :value="summaryCounts.approved"
                subtext="Published to investor portal"
                tone="emerald"
                :icon="CheckCircle2"
            />
            <MetricCard
                label="Rejected"
                :value="summaryCounts.rejected"
                subtext="Withheld from portal"
                tone="rose"
                :icon="XCircle"
            />
        </div>

        <section class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <ShieldCheck class="size-4 text-primary" />
                    <h2 class="text-base font-semibold">Approval queue</h2>
                </div>

                <div
                    v-if="approvalRequests.length"
                    role="group"
                    aria-label="Filter approval requests"
                    class="flex flex-wrap gap-1.5"
                >
                    <Button
                        type="button"
                        size="sm"
                        :variant="filter === 'all' ? 'default' : 'outline'"
                        @click="filter = 'all'"
                    >
                        All ({{ summaryCounts.total }})
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        :variant="filter === 'pending' ? 'default' : 'outline'"
                        @click="filter = 'pending'"
                    >
                        Pending ({{ summaryCounts.pending }})
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        :variant="
                            filter === 'clarification' ? 'default' : 'outline'
                        "
                        @click="filter = 'clarification'"
                    >
                        Needs clarification ({{
                            summaryCounts.clarificationRequested
                        }})
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        :variant="filter === 'history' ? 'default' : 'outline'"
                        @click="filter = 'history'"
                    >
                        History
                    </Button>
                </div>
            </div>

            <div v-if="visibleRequests.length > 0" class="space-y-4">
                <article
                    v-for="request in visibleRequests"
                    :key="request.id"
                    class="space-y-4 rounded-2xl border bg-card p-4 text-sm shadow-xs sm:p-5"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div class="min-w-0 space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="text-base font-semibold">
                                    {{ request.requestTypeLabel }} ·
                                    {{ request.subjectLabel }}
                                </h3>
                                <Badge variant="outline">
                                    {{ request.triggerTypeLabel }}
                                </Badge>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                {{ request.agreementTitle }}
                                <template v-if="request.farmName">
                                    · {{ request.farmName }}
                                </template>
                                · {{ request.investorName }}
                            </p>
                        </div>
                        <Badge
                            variant="outline"
                            :class="statusBadgeClass(request.status)"
                        >
                            {{ request.statusLabel }}
                        </Badge>
                    </div>

                    <dl
                        class="grid grid-cols-2 gap-3 rounded-xl bg-muted/40 p-3 sm:grid-cols-4"
                    >
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Requested
                            </dt>
                            <dd class="mt-0.5 font-semibold tabular-nums">
                                {{
                                    formatCurrency(
                                        request.requestedAmount,
                                        request.currency,
                                    )
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Approved amount
                            </dt>
                            <dd class="mt-0.5 font-semibold tabular-nums">
                                {{
                                    request.approvedAmount !== null
                                        ? formatCurrency(
                                              request.approvedAmount,
                                              request.currency,
                                          )
                                        : '—'
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Requested on
                            </dt>
                            <dd class="mt-0.5 font-medium">
                                <time :datetime="request.requestedAt">{{
                                    formatInvestorDate(request.requestedAt)
                                }}</time>
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-muted-foreground">
                                Decided on
                            </dt>
                            <dd class="mt-0.5 font-medium">
                                {{
                                    request.decidedAt
                                        ? formatInvestorDate(request.decidedAt)
                                        : 'Pending decision'
                                }}
                            </dd>
                        </div>
                    </dl>

                    <p v-if="request.expenseDescription" class="break-words">
                        {{ request.expenseDescription }}
                    </p>
                    <p v-if="request.requesterComment" class="break-words">
                        <span class="text-xs font-medium text-muted-foreground"
                            >Requester note:
                        </span>
                        {{ request.requesterComment }}
                    </p>
                    <p
                        v-if="request.decisionComment"
                        class="break-words text-muted-foreground"
                    >
                        <span class="text-xs font-medium text-foreground"
                            >Decision note:
                        </span>
                        {{ request.decisionComment }}
                    </p>

                    <div
                        v-if="request.milestoneEvidence.length"
                        class="flex flex-wrap gap-2"
                    >
                        <a
                            v-for="file in request.milestoneEvidence"
                            :key="file.id"
                            :href="file.downloadUrl"
                            class="inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-xs font-medium underline-offset-4 hover:bg-muted hover:underline"
                        >
                            <Paperclip class="size-3.5" aria-hidden="true" />
                            {{ file.fileName }}
                        </a>
                    </div>

                    <p
                        v-if="
                            request.requestType === 'funding_release' &&
                            isActionableStatus(request.status)
                        "
                        class="rounded-lg border border-amber-200 bg-amber-50/70 px-3 py-2 text-xs font-medium text-amber-900 dark:border-amber-800/60 dark:bg-amber-950/40 dark:text-amber-200"
                    >
                        Awaiting investor decision — funding release requests
                        can only be approved or rejected by the investor.
                    </p>

                    <Form
                        v-if="canTeamDecide(request)"
                        v-bind="
                            storeDecision.form({
                                current_team: currentTeamSlug,
                                approval_request: request.id,
                            })
                        "
                        class="space-y-4 border-t pt-4"
                        v-slot="{ errors, processing }"
                    >
                        <div class="space-y-2">
                            <Label :for="`decision-status-${request.id}`">
                                Decision
                            </Label>
                            <input
                                :id="`decision-status-${request.id}`"
                                type="hidden"
                                name="status"
                                :value="selectedDecisions[request.id] ?? ''"
                            />
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="option in decisionOptionsFor(
                                        request,
                                    )"
                                    :key="option.value"
                                    type="button"
                                    :aria-pressed="
                                        selectedDecisions[request.id] ===
                                        option.value
                                    "
                                    class="inline-flex min-h-10 items-center gap-1.5 rounded-lg border px-3 py-1.5 text-sm font-medium transition-colors"
                                    :class="
                                        selectedDecisions[request.id] ===
                                        option.value
                                            ? option.activeClass
                                            : 'border-border bg-background text-foreground hover:bg-muted'
                                    "
                                    @click="
                                        selectedDecisions[request.id] =
                                            option.value
                                    "
                                >
                                    <component
                                        :is="option.icon"
                                        class="size-4"
                                        aria-hidden="true"
                                    />
                                    {{ option.label }}
                                </button>
                            </div>
                            <InputError :message="errors.status" />
                        </div>

                        <div class="grid gap-3 md:grid-cols-2">
                            <div class="grid gap-2">
                                <Label :for="`decision-amount-${request.id}`">
                                    Approved amount ({{ request.currency }})
                                </Label>
                                <Input
                                    :id="`decision-amount-${request.id}`"
                                    name="approved_amount"
                                    :model-value="request.requestedAmount"
                                    inputmode="decimal"
                                />
                                <InputError :message="errors.approved_amount" />
                            </div>
                            <div class="grid gap-2">
                                <Label :for="`decision-comment-${request.id}`">
                                    Comment
                                </Label>
                                <Input
                                    :id="`decision-comment-${request.id}`"
                                    name="decision_comment"
                                    maxlength="2000"
                                />
                                <InputError
                                    :message="errors.decision_comment"
                                />
                            </div>
                        </div>

                        <div>
                            <Button
                                :disabled="
                                    processing || !selectedDecisions[request.id]
                                "
                                class="min-h-10"
                            >
                                Submit decision
                            </Button>
                        </div>
                    </Form>
                </article>
            </div>
            <EmptyState
                v-else
                :icon="ShieldCheck"
                :title="
                    approvalRequests.length
                        ? 'No requests match this filter'
                        : 'No approval requests yet'
                "
                :description="
                    approvalRequests.length
                        ? 'Switch filters to inspect other approval requests.'
                        : 'Triggered expenses, funding releases, and plan changes will appear here.'
                "
            />
        </section>
    </div>
</template>
