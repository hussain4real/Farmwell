<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { ShieldCheck } from 'lucide-vue-next';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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

const currentTeam = computed(() => page.props.currentTeam as Team | null);
const currentTeamSlug = computed(() => currentTeam.value?.slug ?? '');
const decisionStatuses = computed(() =>
    ['approved', 'rejected', 'clarification_requested'].map((status) => {
        return (
            props.options.approvalRequestStatuses.find(
                (candidate) => candidate.value === status,
            ) ?? { value: status, label: status }
        );
    }),
);
</script>

<template>
    <Head title="Investor Approvals" />

    <div class="flex flex-1 flex-col gap-8 p-4 md:p-6">
        <Heading
            title="Approvals"
            description="Investor decision queue for releases, expenses, overruns, and plan changes"
        />

        <section class="space-y-4">
            <div class="flex items-center gap-2">
                <ShieldCheck class="size-4" />
                <Heading
                    variant="small"
                    title="Approval queue"
                    description="Decisions update investor visibility and are audited"
                />
            </div>

            <div v-if="approvalRequests.length > 0" class="space-y-4">
                <article
                    v-for="request in approvalRequests"
                    :key="request.id"
                    class="space-y-4 rounded-lg border p-4 text-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="font-medium">
                                {{ request.requestTypeLabel }} /
                                {{ request.subjectLabel }}
                            </h3>
                            <p class="text-muted-foreground">
                                {{ request.agreementTitle }} /
                                {{ request.investorName }}
                            </p>
                        </div>
                        <Badge variant="secondary">
                            {{ request.statusLabel }}
                        </Badge>
                    </div>
                    <div class="grid gap-2 md:grid-cols-4">
                        <p>
                            Requested {{ request.requestedAmount }}
                            {{ request.currency }}
                        </p>
                        <p>{{ request.triggerTypeLabel }}</p>
                        <p>{{ request.requestedAt }}</p>
                        <p>{{ request.decidedAt ?? 'Pending decision' }}</p>
                    </div>
                    <p v-if="request.requesterComment">
                        {{ request.requesterComment }}
                    </p>
                    <p
                        v-if="request.decisionComment"
                        class="text-muted-foreground"
                    >
                        {{ request.decisionComment }}
                    </p>

                    <Form
                        v-if="
                            permissions.canManageApprovalRequests &&
                            currentTeamSlug &&
                            request.status === 'pending'
                        "
                        v-bind="
                            storeDecision.form({
                                current_team: currentTeamSlug,
                                approval_request: request.id,
                            })
                        "
                        class="grid gap-3 md:grid-cols-4"
                        v-slot="{ errors, processing }"
                    >
                        <div class="grid gap-2">
                            <Label :for="`decision-status-${request.id}`">
                                Decision
                            </Label>
                            <select
                                :id="`decision-status-${request.id}`"
                                name="status"
                                class="h-9 rounded-md border bg-background px-3 text-sm"
                            >
                                <option
                                    v-for="option in decisionStatuses"
                                    :key="option.value"
                                    :value="option.value"
                                >
                                    {{ option.label }}
                                </option>
                            </select>
                            <InputError :message="errors.status" />
                        </div>
                        <div class="grid gap-2">
                            <Label :for="`decision-amount-${request.id}`">
                                Approved amount
                            </Label>
                            <Input
                                :id="`decision-amount-${request.id}`"
                                name="approved_amount"
                                :value="request.requestedAmount"
                            />
                            <InputError :message="errors.approved_amount" />
                        </div>
                        <div class="grid gap-2 md:col-span-2">
                            <Label :for="`decision-comment-${request.id}`">
                                Comment
                            </Label>
                            <Input
                                :id="`decision-comment-${request.id}`"
                                name="decision_comment"
                            />
                            <InputError :message="errors.decision_comment" />
                        </div>
                        <div class="flex items-end">
                            <Button :disabled="processing">
                                Submit decision
                            </Button>
                        </div>
                    </Form>
                </article>
            </div>
            <p v-else class="text-sm text-muted-foreground">
                No approval requests yet.
            </p>
        </section>
    </div>
</template>
