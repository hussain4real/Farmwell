<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { Handshake, MessageSquare } from 'lucide-vue-next';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SummaryText from '@/components/SummaryText.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCurrency } from '@/lib/currency';
import { store as storeInvestorComment } from '@/routes/investor-comments';
import { index as investorPortalIndex } from '@/routes/investor-portal';
import { index as statementsIndex } from '@/routes/investor-statements';
import { store as storeDecision } from '@/routes/investors/approvals/decisions';
import type { InvestorPortalAgreement, Team } from '@/types';

type Props = {
    agreements: InvestorPortalAgreement[];
};

defineProps<Props>();
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

const currentTeam = computed(() => page.props.currentTeam as Team | null);
const currentTeamSlug = computed(() => currentTeam.value?.slug ?? '');
</script>

<template>
    <div class="flex flex-1 flex-col gap-8 p-4 md:p-6">
        <Head title="Investor Portal" />
        <Heading
            title="Your investments"
            description="Follow farm progress, financial updates, and decisions for your agreements."
        />

        <section v-if="agreements.length > 0" class="space-y-6">
            <article
                v-for="agreement in agreements"
                :key="agreement.id"
                class="space-y-6 rounded-xl border border-[#d6e5dc] bg-card p-5 sm:p-6 dark:border-border"
            >
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span
                                class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-[#e5f1e9] text-[#28664e] dark:bg-emerald-950 dark:text-emerald-300"
                                ><Handshake class="size-5"
                            /></span>
                            <h2 class="text-lg font-semibold">
                                {{ agreement.title }}
                            </h2>
                        </div>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ agreement.farmName }}
                            <span v-if="agreement.productionCycleName">
                                / {{ agreement.productionCycleName }}
                            </span>
                        </p>
                    </div>
                    <Badge variant="secondary">
                        {{ agreement.statusLabel }}
                    </Badge>
                </div>
                <Link
                    :href="statementsIndex([currentTeamSlug, agreement.id])"
                    class="text-sm font-medium underline underline-offset-4"
                    >Investor statements</Link
                >
                <div
                    v-if="agreement.documents.length"
                    class="flex flex-wrap gap-2 text-sm"
                >
                    <a
                        v-for="document in agreement.documents"
                        :key="document.id"
                        :href="document.downloadUrl"
                        class="underline underline-offset-4"
                    >
                        {{ document.fileName }}
                    </a>
                </div>

                <div
                    class="grid grid-cols-2 gap-px overflow-hidden rounded-xl border bg-border xl:grid-cols-3"
                >
                    <div class="min-w-0 bg-[#f8fbf9] p-4 dark:bg-muted/20">
                        <p class="text-sm text-muted-foreground">Released</p>
                        <p
                            class="mt-2 text-lg font-semibold tracking-tight break-words"
                        >
                            {{
                                formatCurrency(
                                    agreement.summary.released,
                                    agreement.currency,
                                )
                            }}
                        </p>
                    </div>
                    <div class="min-w-0 bg-[#f8fbf9] p-4 dark:bg-muted/20">
                        <p class="text-sm text-muted-foreground">Spent</p>
                        <p
                            class="mt-2 text-lg font-semibold tracking-tight break-words"
                        >
                            {{
                                formatCurrency(
                                    agreement.summary.spent,
                                    agreement.currency,
                                )
                            }}
                        </p>
                    </div>
                    <div class="min-w-0 bg-[#f8fbf9] p-4 dark:bg-muted/20">
                        <p class="text-sm text-muted-foreground">Balance</p>
                        <p
                            class="mt-2 text-lg font-semibold tracking-tight break-words"
                        >
                            {{
                                formatCurrency(
                                    agreement.summary.balance,
                                    agreement.currency,
                                )
                            }}
                        </p>
                    </div>
                    <div class="min-w-0 bg-[#f8fbf9] p-4 dark:bg-muted/20">
                        <p class="text-sm text-muted-foreground">Net sales</p>
                        <p
                            class="mt-2 text-lg font-semibold tracking-tight break-words"
                        >
                            {{
                                formatCurrency(
                                    agreement.summary.saleNet,
                                    agreement.currency,
                                )
                            }}
                        </p>
                    </div>
                    <div class="min-w-0 bg-[#f8fbf9] p-4 dark:bg-muted/20">
                        <p class="text-sm text-muted-foreground">
                            Capital recovered
                        </p>
                        <p
                            class="mt-2 text-lg font-semibold tracking-tight break-words"
                        >
                            {{
                                formatCurrency(
                                    agreement.summary.capitalRecovered,
                                    agreement.currency,
                                )
                            }}
                        </p>
                    </div>
                    <div class="min-w-0 bg-[#f8fbf9] p-4 dark:bg-muted/20">
                        <p class="text-sm text-muted-foreground">
                            Investor share
                        </p>
                        <p
                            class="mt-2 text-lg font-semibold tracking-tight break-words"
                        >
                            {{
                                formatCurrency(
                                    agreement.summary.investorShare,
                                    agreement.currency,
                                )
                            }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-6 xl:grid-cols-2">
                    <section class="space-y-3">
                        <Heading
                            variant="small"
                            title="Funding phases"
                            description="Approved release milestones"
                        />
                        <div
                            v-if="agreement.fundingPhases.length"
                            class="space-y-3"
                        >
                            <div
                                v-for="phase in agreement.fundingPhases"
                                :key="phase.id"
                                class="rounded-lg border p-3 text-sm"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <p class="font-medium">
                                        {{ phase.name }}
                                    </p>
                                    <Badge variant="secondary">
                                        {{ phase.statusLabel }}
                                    </Badge>
                                </div>
                                <p class="mt-1 text-muted-foreground">
                                    Released
                                    {{
                                        formatCurrency(
                                            phase.externallyReleasedAmount,
                                            agreement.currency,
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">
                            No approved funding phases yet.
                        </p>
                    </section>

                    <section class="space-y-3">
                        <Heading
                            variant="small"
                            title="Approved expenses"
                            description="Category, amount, status, and receipt evidence"
                        />
                        <div v-if="agreement.expenses.length" class="space-y-3">
                            <div
                                v-for="expense in agreement.expenses"
                                :key="expense.id"
                                class="rounded-lg border p-3 text-sm"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <p class="font-medium">
                                        {{ expense.categoryName }}
                                    </p>
                                    <Badge variant="secondary">
                                        {{
                                            formatCurrency(
                                                expense.amount,
                                                expense.currency,
                                            )
                                        }}
                                    </Badge>
                                </div>
                                <p class="mt-2">{{ expense.description }}</p>
                                <div
                                    v-if="expense.receipts.length"
                                    class="mt-2 flex flex-wrap gap-2"
                                >
                                    <a
                                        v-for="receipt in expense.receipts"
                                        :key="receipt.id"
                                        :href="receipt.downloadUrl"
                                        class="underline underline-offset-4"
                                    >
                                        {{ receipt.fileName }}
                                    </a>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">
                            No approved expenses yet.
                        </p>
                    </section>
                </div>

                <div class="grid gap-6 xl:grid-cols-3">
                    <section class="space-y-3">
                        <Heading
                            variant="small"
                            title="Harvest output"
                            description="Approved output entries and evidence"
                        />
                        <div
                            v-if="agreement.harvestRecords.length"
                            class="space-y-3"
                        >
                            <div
                                v-for="harvest in agreement.harvestRecords"
                                :key="harvest.id"
                                class="rounded-lg border p-3 text-sm"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <p class="font-medium">
                                        {{ harvest.commodityName }}
                                    </p>
                                    <Badge variant="secondary">
                                        {{ harvest.statusLabel }}
                                    </Badge>
                                </div>
                                <p class="mt-1 text-muted-foreground">
                                    {{ harvest.stageLabel }} /
                                    {{ harvest.harvestedOn }}
                                </p>
                                <p class="mt-2">
                                    {{ harvest.quantity }}
                                    {{ harvest.quantityUnit }}
                                </p>
                                <div
                                    v-if="harvest.evidence.length"
                                    class="mt-2 flex flex-wrap gap-2"
                                >
                                    <a
                                        v-for="evidence in harvest.evidence"
                                        :key="evidence.id"
                                        :href="evidence.downloadUrl"
                                        class="underline underline-offset-4"
                                    >
                                        {{ evidence.fileName }}
                                    </a>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">
                            No approved harvest output yet.
                        </p>
                    </section>

                    <section class="space-y-3">
                        <Heading
                            variant="small"
                            title="Sales"
                            description="Approved buyer records and net proceeds"
                        />
                        <div
                            v-if="agreement.saleRecords.length"
                            class="space-y-3"
                        >
                            <div
                                v-for="sale in agreement.saleRecords"
                                :key="sale.id"
                                class="rounded-lg border p-3 text-sm"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <p class="font-medium">
                                        {{ sale.buyerName }}
                                    </p>
                                    <Badge variant="secondary">
                                        {{
                                            formatCurrency(
                                                sale.netAmount,
                                                sale.currency,
                                            )
                                        }}
                                    </Badge>
                                </div>
                                <p class="mt-1 text-muted-foreground">
                                    {{ sale.commodityName }} /
                                    {{ sale.soldOn }}
                                </p>
                                <p class="mt-2">
                                    Gross
                                    {{
                                        formatCurrency(
                                            sale.grossAmount,
                                            sale.currency,
                                        )
                                    }}, deductions
                                    {{
                                        formatCurrency(
                                            sale.deductionAmount,
                                            sale.currency,
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">
                            No approved sales yet.
                        </p>
                    </section>

                    <section class="space-y-3">
                        <Heading
                            variant="small"
                            title="Distributions"
                            description="Capital recovery before profit share"
                        />
                        <div
                            v-if="agreement.distributionRecords.length"
                            class="space-y-3"
                        >
                            <div
                                v-for="distribution in agreement.distributionRecords"
                                :key="distribution.id"
                                class="rounded-lg border p-3 text-sm"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <p class="font-medium">
                                        {{ distribution.buyerName }}
                                    </p>
                                    <Badge variant="secondary">
                                        {{ distribution.statusLabel }}
                                    </Badge>
                                </div>
                                <p class="mt-2">
                                    Capital
                                    {{
                                        formatCurrency(
                                            distribution.capitalRecovered,
                                            distribution.currency,
                                        )
                                    }}, profit
                                    {{
                                        formatCurrency(
                                            distribution.netProfit,
                                            distribution.currency,
                                        )
                                    }}
                                </p>
                                <p class="mt-1 text-muted-foreground">
                                    Investor
                                    {{
                                        formatCurrency(
                                            distribution.investorShare,
                                            distribution.currency,
                                        )
                                    }}
                                    / farm
                                    {{
                                        formatCurrency(
                                            distribution.farmShare,
                                            distribution.currency,
                                        )
                                    }}
                                </p>
                            </div>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">
                            No approved distributions yet.
                        </p>
                    </section>
                </div>

                <div class="grid gap-6 xl:grid-cols-2">
                    <section class="space-y-3">
                        <Heading
                            variant="small"
                            title="External transfers"
                            description="Approved recorded releases and proof"
                        />
                        <div
                            v-if="agreement.externalTransfers.length"
                            class="space-y-3"
                        >
                            <div
                                v-for="transfer in agreement.externalTransfers"
                                :key="transfer.id"
                                class="rounded-lg border p-3 text-sm"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <p class="font-medium">
                                        {{ transfer.transferType }}
                                    </p>
                                    <Badge variant="secondary">
                                        {{
                                            formatCurrency(
                                                transfer.amount,
                                                transfer.currency,
                                            )
                                        }}
                                    </Badge>
                                </div>
                                <p class="mt-1 text-muted-foreground">
                                    {{ transfer.transferredOn }}
                                </p>
                                <div
                                    v-if="transfer.proof.length"
                                    class="mt-2 flex flex-wrap gap-2"
                                >
                                    <a
                                        v-for="proof in transfer.proof"
                                        :key="proof.id"
                                        :href="proof.downloadUrl"
                                        class="underline underline-offset-4"
                                    >
                                        {{ proof.fileName }}
                                    </a>
                                </div>
                            </div>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">
                            No approved external transfers yet.
                        </p>
                    </section>

                    <section class="space-y-3">
                        <Heading
                            variant="small"
                            title="Approval requests"
                            description="Investor decisions and clarification history"
                        />
                        <div
                            v-if="agreement.approvalRequests.length"
                            class="space-y-3"
                        >
                            <div
                                v-for="request in agreement.approvalRequests"
                                :key="request.id"
                                class="rounded-lg border p-3 text-sm"
                            >
                                <div
                                    class="flex items-start justify-between gap-3"
                                >
                                    <p class="font-medium">
                                        {{ request.requestTypeLabel }}
                                    </p>
                                    <Badge variant="secondary">
                                        {{ request.statusLabel }}
                                    </Badge>
                                </div>
                                <p class="mt-1 text-muted-foreground">
                                    {{ request.subjectLabel }} /
                                    {{
                                        formatCurrency(
                                            request.requestedAmount,
                                            request.currency,
                                        )
                                    }}
                                </p>
                                <div
                                    v-if="request.expenseDescription"
                                    class="mt-3"
                                >
                                    <p
                                        class="text-xs font-medium text-muted-foreground"
                                    >
                                        Expense description
                                    </p>
                                    <p
                                        class="mt-1 break-words whitespace-pre-wrap"
                                    >
                                        {{ request.expenseDescription }}
                                    </p>
                                </div>
                                <div
                                    v-if="request.requesterComment"
                                    class="mt-3"
                                >
                                    <p
                                        class="text-xs font-medium text-muted-foreground"
                                    >
                                        Requester’s comment
                                    </p>
                                    <p
                                        class="mt-1 break-words whitespace-pre-wrap"
                                    >
                                        {{ request.requesterComment }}
                                    </p>
                                </div>
                                <p v-if="request.milestone" class="mt-2">
                                    Milestone: {{ request.milestone }}
                                </p>
                                <div
                                    v-if="request.milestoneEvidence.length"
                                    class="mt-2"
                                >
                                    <p>Milestone evidence</p>
                                    <a
                                        v-for="file in request.milestoneEvidence"
                                        :key="file.id"
                                        :href="file.downloadUrl"
                                        class="underline"
                                        >{{ file.fileName }}</a
                                    >
                                </div>
                                <p v-if="request.decisionComment" class="mt-2">
                                    {{ request.decisionComment }}
                                </p>
                                <Form
                                    v-if="
                                        request.status === 'pending' &&
                                        currentTeamSlug
                                    "
                                    v-bind="
                                        storeDecision.form({
                                            current_team: currentTeamSlug,
                                            approval_request: request.id,
                                        })
                                    "
                                    class="mt-3 grid gap-3 md:grid-cols-3"
                                    v-slot="{ errors, processing }"
                                >
                                    <div class="grid gap-2">
                                        <Label
                                            :for="`portal-decision-${request.id}`"
                                        >
                                            Decision
                                        </Label>
                                        <select
                                            :id="`portal-decision-${request.id}`"
                                            name="status"
                                            class="h-9 rounded-md border bg-background px-3 text-sm"
                                        >
                                            <option value="approved">
                                                Approved
                                            </option>
                                            <option value="rejected">
                                                Rejected
                                            </option>
                                            <option
                                                value="clarification_requested"
                                            >
                                                Request clarification
                                            </option>
                                        </select>
                                        <InputError :message="errors.status" />
                                    </div>
                                    <div
                                        v-if="
                                            request.requestType ===
                                            'funding_release'
                                        "
                                        class="grid gap-2"
                                    >
                                        <Label
                                            :for="`portal-approved-amount-${request.id}`"
                                        >
                                            Approved amount ({{
                                                request.currency
                                            }})
                                        </Label>
                                        <Input
                                            :id="`portal-approved-amount-${request.id}`"
                                            name="approved_amount"
                                            inputmode="decimal"
                                            :placeholder="
                                                request.requestedAmount
                                            "
                                        />
                                        <p
                                            class="text-xs text-muted-foreground"
                                        >
                                            Leave blank to approve the full
                                            requested amount.
                                        </p>
                                        <InputError
                                            :message="errors.approved_amount"
                                        />
                                    </div>
                                    <div class="grid gap-2">
                                        <Label
                                            :for="`portal-decision-comment-${request.id}`"
                                        >
                                            Comment
                                        </Label>
                                        <Input
                                            :id="`portal-decision-comment-${request.id}`"
                                            name="decision_comment"
                                        />
                                        <InputError
                                            :message="errors.decision_comment"
                                        />
                                    </div>
                                    <div class="flex items-end">
                                        <Button :disabled="processing">
                                            Submit
                                        </Button>
                                    </div>
                                </Form>
                            </div>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">
                            No approval requests yet.
                        </p>
                    </section>
                </div>

                <div class="grid gap-6 xl:grid-cols-2">
                    <section class="space-y-3">
                        <Heading
                            variant="small"
                            title="Progress updates"
                            description="Investor-safe activity and task updates"
                        />
                        <div
                            v-if="
                                agreement.aiSummaries.length ||
                                agreement.activities.length ||
                                agreement.tasks.length ||
                                agreement.planChanges.length
                            "
                            class="space-y-3"
                        >
                            <div
                                v-for="summary in agreement.aiSummaries"
                                :key="`summary-${summary.id}`"
                                class="rounded-lg border p-3 text-sm"
                            >
                                <p class="font-medium">
                                    Reviewed progress summary
                                </p>
                                <SummaryText
                                    class="mt-1"
                                    :html="summary.html"
                                />
                            </div>
                            <div
                                v-for="activity in agreement.activities"
                                :key="`activity-${activity.id}`"
                                class="rounded-lg border p-3 text-sm"
                            >
                                <p class="font-medium">
                                    {{ activity.activityType }}
                                </p>
                                <p class="mt-1">{{ activity.summary }}</p>
                            </div>
                            <div
                                v-for="task in agreement.tasks"
                                :key="`task-${task.id}`"
                                class="rounded-lg border p-3 text-sm"
                            >
                                <p class="font-medium">{{ task.title }}</p>
                                <p class="mt-1 text-muted-foreground">
                                    {{ task.status }}
                                </p>
                            </div>
                            <div
                                v-for="change in agreement.planChanges"
                                :key="`change-${change.id}`"
                                class="rounded-lg border p-3 text-sm"
                            >
                                <p class="font-medium">
                                    {{ change.changeType }}
                                </p>
                                <p class="mt-1">
                                    {{ change.summary ?? change.impact }}
                                </p>
                            </div>
                        </div>
                        <p v-else class="text-sm text-muted-foreground">
                            No approved progress updates yet.
                        </p>
                    </section>

                    <section class="space-y-3">
                        <Heading
                            variant="small"
                            title="Questions"
                            description="Comments are stored for internal review"
                        />
                        <div v-if="agreement.comments.length" class="space-y-3">
                            <div
                                v-for="comment in agreement.comments"
                                :key="comment.id"
                                class="rounded-lg border p-3 text-sm"
                            >
                                <p class="font-medium">
                                    {{ comment.authorName }}
                                </p>
                                <p class="mt-1">{{ comment.body }}</p>
                                <p class="mt-1 text-muted-foreground">
                                    {{ comment.subjectLabel }}
                                </p>
                            </div>
                        </div>

                        <Form
                            v-if="currentTeamSlug"
                            v-bind="
                                storeInvestorComment.form({
                                    current_team: currentTeamSlug,
                                    investor_agreement: agreement.id,
                                })
                            "
                            class="grid gap-3"
                            v-slot="{ errors, processing }"
                        >
                            <div class="grid gap-2">
                                <Label :for="`comment-${agreement.id}`">
                                    Comment
                                </Label>
                                <Input
                                    :id="`comment-${agreement.id}`"
                                    name="body"
                                    required
                                />
                                <InputError :message="errors.body" />
                            </div>
                            <div>
                                <Button :disabled="processing">
                                    <MessageSquare class="mr-2 size-4" />
                                    Send
                                </Button>
                            </div>
                        </Form>
                    </section>
                </div>
            </article>
        </section>
        <p v-else class="text-sm text-muted-foreground">
            No active investor agreements are assigned to this account.
        </p>
    </div>
</template>
