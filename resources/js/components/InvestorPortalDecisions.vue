<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import {
    CheckCircle2,
    ClipboardCheck,
    HelpCircle,
    Paperclip,
    XCircle,
} from 'lucide-vue-next';
import { computed, reactive, ref } from 'vue';
import ApprovalClarificationReplies from '@/components/ApprovalClarificationReplies.vue';
import EmptyState from '@/components/farm/EmptyState.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCurrency } from '@/lib/currency';
import { formatInvestorDate } from '@/lib/investor-portal';
import { store as storeDecision } from '@/routes/investors/approvals/decisions';
import type { InvestorApprovalRequest } from '@/types';

const props = defineProps<{
    requests: InvestorApprovalRequest[];
    currentTeamSlug: string;
    agreementId: number;
}>();

type DecisionValue = 'approved' | 'rejected' | 'clarification_requested';

const filter = ref<'all' | 'pending' | 'history'>('all');
const selectedDecisions = reactive<Record<number, DecisionValue | ''>>({});

const isActionable = (request: InvestorApprovalRequest): boolean =>
    request.status === 'pending' ||
    request.status === 'clarification_requested';

const pendingRequests = computed(() =>
    props.requests.filter((request) => isActionable(request)),
);

const historyRequests = computed(() =>
    props.requests.filter((request) => !isActionable(request)),
);

const visibleRequests = computed(() => {
    if (filter.value === 'pending') {
        return pendingRequests.value;
    }

    if (filter.value === 'history') {
        return historyRequests.value;
    }

    return [...pendingRequests.value, ...historyRequests.value];
});

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
    const options: Array<{
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
        options.push({
            value: 'clarification_requested',
            label: 'Request clarification',
            icon: HelpCircle,
            activeClass:
                'border-cyan-600 bg-cyan-600 text-white dark:bg-cyan-600',
        });
    }

    options.push({
        value: 'rejected',
        label: 'Reject',
        icon: XCircle,
        activeClass: 'border-rose-600 bg-rose-600 text-white dark:bg-rose-600',
    });

    return options;
}

function selectDecision(requestId: number, value: DecisionValue): void {
    selectedDecisions[requestId] = value;
}
</script>

<template>
    <section
        id="investor-decisions"
        tabindex="-1"
        class="space-y-4 focus:outline-none"
    >
        <Heading
            variant="small"
            title="Approval requests"
            description="Investor decisions and clarification history"
        />

        <div
            v-if="requests.length"
            class="flex flex-wrap items-center justify-between gap-3"
        >
            <p class="text-sm text-muted-foreground" role="status">
                {{ visibleRequests.length }} of {{ requests.length }} requests
            </p>
            <div
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
                    All ({{ requests.length }})
                </Button>
                <Button
                    type="button"
                    size="sm"
                    :variant="filter === 'pending' ? 'default' : 'outline'"
                    @click="filter = 'pending'"
                >
                    Needs a decision ({{ pendingRequests.length }})
                </Button>
                <Button
                    type="button"
                    size="sm"
                    :variant="filter === 'history' ? 'default' : 'outline'"
                    @click="filter = 'history'"
                >
                    Decision history ({{ historyRequests.length }})
                </Button>
            </div>
        </div>

        <div v-if="visibleRequests.length" class="space-y-4">
            <div
                v-for="request in visibleRequests"
                :key="request.id"
                :id="`investor-request-${request.id}`"
                tabindex="-1"
                class="scroll-mt-4 rounded-2xl border bg-card p-4 text-sm shadow-xs focus:outline-2 focus:outline-offset-2 focus:outline-ring sm:p-5"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="text-base font-semibold">
                                {{ request.requestTypeLabel }}
                            </p>
                            <Badge variant="outline">
                                {{ request.triggerTypeLabel }}
                            </Badge>
                        </div>
                        <p class="text-xs text-muted-foreground">
                            Requested
                            <time :datetime="request.requestedAt">{{
                                formatInvestorDate(request.requestedAt)
                            }}</time>
                        </p>
                    </div>
                    <Badge
                        variant="outline"
                        :class="statusBadgeClass(request.status)"
                    >
                        {{ request.statusLabel }}
                    </Badge>
                </div>

                <div
                    class="mt-3 flex flex-wrap items-baseline justify-between gap-2 rounded-xl bg-muted/40 px-3.5 py-2.5"
                >
                    <span class="font-medium">
                        {{ request.subjectLabel }}
                    </span>
                    <span class="text-base font-semibold tabular-nums">
                        {{
                            formatCurrency(
                                request.requestedAmount,
                                request.currency,
                            )
                        }}
                    </span>
                </div>

                <div v-if="request.expenseDescription" class="mt-3">
                    <p class="text-xs font-medium text-muted-foreground">
                        Expense description
                    </p>
                    <p class="mt-1 break-words whitespace-pre-wrap">
                        {{ request.expenseDescription }}
                    </p>
                </div>

                <div v-if="request.requesterComment" class="mt-3">
                    <p class="text-xs font-medium text-muted-foreground">
                        Requester’s comment
                    </p>
                    <p class="mt-1 break-words whitespace-pre-wrap">
                        {{ request.requesterComment }}
                    </p>
                </div>

                <p v-if="request.milestone" class="mt-3 text-muted-foreground">
                    <span class="font-medium text-foreground">Milestone:</span>
                    {{ request.milestone }}
                </p>

                <p v-if="request.expectedOn" class="mt-3 text-muted-foreground">
                    Expected on
                    <time :datetime="request.expectedOn">{{
                        formatInvestorDate(request.expectedOn)
                    }}</time>
                </p>

                <div v-if="request.milestoneEvidence.length" class="mt-3">
                    <p class="text-xs font-medium text-muted-foreground">
                        Milestone evidence
                    </p>
                    <div class="mt-1.5 flex flex-wrap gap-2">
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
                </div>

                <div
                    v-if="
                        request.approvedAmount !== null ||
                        request.decidedAt ||
                        request.decisionComment
                    "
                    class="mt-3 space-y-1 border-t pt-3 text-xs text-muted-foreground"
                >
                    <p
                        v-if="request.approvedAmount !== null"
                        class="font-medium text-foreground"
                    >
                        Approved amount:
                        {{
                            formatCurrency(
                                request.approvedAmount,
                                request.currency,
                            )
                        }}
                    </p>
                    <p v-if="request.decidedAt">
                        Decision recorded
                        <time :datetime="request.decidedAt">{{
                            formatInvestorDate(request.decidedAt)
                        }}</time>
                    </p>
                    <p
                        v-if="request.decisionComment"
                        class="text-sm break-words whitespace-pre-wrap text-foreground"
                    >
                        {{ request.decisionComment }}
                    </p>
                </div>

                <ApprovalClarificationReplies
                    :replies="request.clarificationReplies"
                    class="mt-3"
                />

                <Form
                    v-if="isActionable(request) && currentTeamSlug"
                    v-bind="
                        storeDecision.form({
                            current_team: currentTeamSlug,
                            approval_request: request.id,
                        })
                    "
                    class="mt-4 space-y-4 border-t pt-4"
                    v-slot="{ errors, processing }"
                >
                    <input
                        v-if="request.reviewToken"
                        type="hidden"
                        name="review_token"
                        :value="request.reviewToken"
                    />
                    <div class="space-y-2">
                        <Label :for="`portal-decision-${request.id}`">
                            Choose a decision
                        </Label>
                        <input
                            :id="`portal-decision-${request.id}`"
                            type="hidden"
                            name="status"
                            :value="selectedDecisions[request.id] ?? ''"
                        />
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="option in decisionOptionsFor(request)"
                                :key="option.value"
                                type="button"
                                :aria-pressed="
                                    selectedDecisions[request.id] ===
                                    option.value
                                "
                                class="inline-flex min-h-11 items-center gap-1.5 rounded-lg border px-3.5 py-2 text-sm font-medium transition-colors sm:min-h-9"
                                :class="
                                    selectedDecisions[request.id] ===
                                    option.value
                                        ? option.activeClass
                                        : 'border-border bg-background text-foreground hover:bg-muted'
                                "
                                @click="
                                    selectDecision(request.id, option.value)
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

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div
                            v-if="
                                request.requestType === 'funding_release' &&
                                selectedDecisions[request.id] === 'approved'
                            "
                            class="grid gap-2"
                        >
                            <Label
                                :for="`portal-approved-amount-${request.id}`"
                            >
                                Approved amount ({{ request.currency }})
                            </Label>
                            <Input
                                :id="`portal-approved-amount-${request.id}`"
                                name="approved_amount"
                                inputmode="decimal"
                                :placeholder="request.requestedAmount"
                            />
                            <p class="text-xs text-muted-foreground">
                                Leave blank to approve the full requested
                                amount.
                            </p>
                            <InputError :message="errors.approved_amount" />
                        </div>

                        <div
                            class="grid gap-2"
                            :class="
                                request.requestType === 'funding_release' &&
                                selectedDecisions[request.id] === 'approved'
                                    ? ''
                                    : 'sm:col-span-2'
                            "
                        >
                            <Label
                                :for="`portal-decision-comment-${request.id}`"
                            >
                                Comment
                            </Label>
                            <Input
                                :id="`portal-decision-comment-${request.id}`"
                                name="decision_comment"
                                maxlength="2000"
                                placeholder="Add context or conditions for the farm team"
                            />
                            <InputError :message="errors.decision_comment" />
                        </div>
                    </div>

                    <div>
                        <Button
                            type="submit"
                            :disabled="
                                processing || !selectedDecisions[request.id]
                            "
                            class="min-h-11 sm:min-h-9"
                        >
                            Save decision
                        </Button>
                    </div>
                </Form>
            </div>
        </div>
        <EmptyState
            v-else
            :icon="ClipboardCheck"
            :title="
                requests.length
                    ? 'No requests match this filter'
                    : 'No approval requests yet'
            "
            :description="
                requests.length
                    ? 'Switch filters to review other approval requests.'
                    : 'Funding release and expense approval requests will appear here.'
            "
        />
    </section>
</template>
