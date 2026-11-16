<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
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
const filter = ref<'all' | 'pending' | 'history'>('all');
const visibleRequests = computed(() => {
    const pending = props.requests.filter(
        (request) => request.status === 'pending',
    );
    const history = props.requests.filter(
        (request) => request.status !== 'pending',
    );

    if (filter.value === 'pending') {
        return pending;
    }

    if (filter.value === 'history') {
        return history;
    }

    return [...pending, ...history];
});
</script>

<template>
    <section
        id="investor-decisions"
        tabindex="-1"
        class="space-y-3 focus:outline-none"
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
            <div class="flex items-center gap-2">
                <Label :for="`decision-filter-${agreementId}`">Show</Label>
                <select
                    :id="`decision-filter-${agreementId}`"
                    v-model="filter"
                    class="h-9 rounded-md border bg-background px-2 text-sm"
                >
                    <option value="all">All requests</option>
                    <option value="pending">Needs a decision</option>
                    <option value="history">Decision history</option>
                </select>
            </div>
        </div>
        <div v-if="visibleRequests.length" class="space-y-3">
            <div
                v-for="request in visibleRequests"
                :key="request.id"
                class="rounded-xl border bg-card p-4 text-sm sm:p-5"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <p class="font-medium">
                        {{ request.requestTypeLabel }}
                    </p>
                    <Badge variant="secondary">
                        {{ request.statusLabel }}
                    </Badge>
                </div>
                <p class="mt-1 text-xs text-muted-foreground">
                    Requested
                    <time :datetime="request.requestedAt">{{
                        formatInvestorDate(request.requestedAt)
                    }}</time>
                </p>
                <p class="mt-2 text-muted-foreground">
                    {{ request.subjectLabel }} /
                    {{
                        formatCurrency(
                            request.requestedAmount,
                            request.currency,
                        )
                    }}
                </p>
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
                <p v-if="request.milestone" class="mt-2">
                    Milestone: {{ request.milestone }}
                </p>
                <div v-if="request.milestoneEvidence.length" class="mt-2">
                    <p>Milestone evidence</p>
                    <a
                        v-for="file in request.milestoneEvidence"
                        :key="file.id"
                        :href="file.downloadUrl"
                        class="underline"
                        >{{ file.fileName }}</a
                    >
                </div>
                <p v-if="request.approvedAmount !== null" class="mt-2">
                    Approved
                    {{
                        formatCurrency(request.approvedAmount, request.currency)
                    }}
                </p>
                <p v-if="request.decidedAt" class="mt-2 text-muted-foreground">
                    Decision recorded
                    <time :datetime="request.decidedAt">{{
                        formatInvestorDate(request.decidedAt)
                    }}</time>
                </p>
                <p
                    v-if="request.decisionComment"
                    class="mt-2 break-words whitespace-pre-wrap"
                >
                    {{ request.decisionComment }}
                </p>
                <Form
                    v-if="request.status === 'pending' && currentTeamSlug"
                    v-bind="
                        storeDecision.form({
                            current_team: currentTeamSlug,
                            approval_request: request.id,
                        })
                    "
                    class="mt-4 grid gap-3 border-t pt-4 sm:grid-cols-2"
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-2">
                        <Label :for="`portal-decision-${request.id}`">
                            Decision
                        </Label>
                        <select
                            :id="`portal-decision-${request.id}`"
                            name="status"
                            required
                            class="h-9 rounded-md border bg-background px-3 text-sm"
                        >
                            <option value="" selected disabled>
                                Choose a decision
                            </option>
                            <option value="approved">Approve</option>
                            <option value="rejected">Reject</option>
                            <option value="clarification_requested">
                                Request clarification
                            </option>
                        </select>
                        <InputError :message="errors.status" />
                    </div>
                    <div
                        v-if="request.requestType === 'funding_release'"
                        class="grid gap-2"
                    >
                        <Label :for="`portal-approved-amount-${request.id}`">
                            Approved amount ({{ request.currency }})
                        </Label>
                        <Input
                            :id="`portal-approved-amount-${request.id}`"
                            name="approved_amount"
                            inputmode="decimal"
                            :placeholder="request.requestedAmount"
                        />
                        <p class="text-xs text-muted-foreground">
                            Leave blank to approve the full requested amount.
                        </p>
                        <InputError :message="errors.approved_amount" />
                    </div>
                    <div class="grid gap-2">
                        <Label :for="`portal-decision-comment-${request.id}`">
                            Comment
                        </Label>
                        <Input
                            :id="`portal-decision-comment-${request.id}`"
                            name="decision_comment"
                            maxlength="2000"
                        />
                        <InputError :message="errors.decision_comment" />
                    </div>
                    <div class="flex items-end">
                        <Button :disabled="processing"> Save decision </Button>
                    </div>
                </Form>
            </div>
        </div>
        <p v-else class="text-sm text-muted-foreground">
            {{
                requests.length
                    ? 'No requests match this filter.'
                    : 'No approval requests yet.'
            }}
        </p>
    </section>
</template>
