<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import {
    ArrowRight,
    FileUp,
    Handshake,
    MessageSquare,
    Paperclip,
    Pencil,
    Plus,
    Settings2,
    ShieldCheck,
    UserPlus,
    Users,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import EmptyState from '@/components/farm/EmptyState.vue';
import FarmTypeBadge from '@/components/farm/FarmTypeBadge.vue';
import MetricCard from '@/components/farm/MetricCard.vue';
import PageHero from '@/components/farm/PageHero.vue';
import SlideOverForm from '@/components/farm/SlideOverForm.vue';
import InputError from '@/components/InputError.vue';
import InviteMemberModal from '@/components/InviteMemberModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCurrency } from '@/lib/currency';
import { formatInvestorDate } from '@/lib/investor-portal';
import { dashboard } from '@/routes';
import { store as storeInvestorComment } from '@/routes/investor-comments';
import { index as investorsIndex } from '@/routes/investors';
import {
    store as storeAgreement,
    update as updateAgreement,
} from '@/routes/investors/agreements';
import { store as storeAgreementDocument } from '@/routes/investors/agreements/documents';
import { update as updateApprovalSettings } from '@/routes/investors/approval-settings';
import { index as approvalsIndex } from '@/routes/investors/approvals';
import type {
    InvestorApprovalSettings,
    InvestorExpenseCategory,
    InvestorFarm,
    InvestorInviteContext,
    InvestorManagementAgreement,
    InvestorManagementSummary,
    InvestorOptions,
    InvestorPermissions,
    InvestorUser,
    Team,
} from '@/types';

type Props = {
    permissions: InvestorPermissions;
    approvalSettings: InvestorApprovalSettings;
    investors: InvestorUser[];
    farms: InvestorFarm[];
    categories: InvestorExpenseCategory[];
    agreements: InvestorManagementAgreement[];
    summary?: InvestorManagementSummary;
    inviteContext?: InvestorInviteContext;
    options: Pick<
        InvestorOptions,
        'agreementStatuses' | 'capitalRecoveryRules'
    >;
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
        ],
    }),
});

const currentTeam = computed(() => page.props.currentTeam as Team | null);
const currentTeamSlug = computed(() => currentTeam.value?.slug ?? '');

const createOpen = ref(false);
const rulesOpen = ref(false);
const inviteOpen = ref(false);
const editingAgreement = ref<InvestorManagementAgreement | null>(null);
const qaAgreementId = ref<number | null>(null);

const qaAgreement = computed(
    () =>
        props.agreements.find((item) => item.id === qaAgreementId.value) ??
        null,
);

const statusFilter = ref<'all' | 'active' | 'draft' | 'other'>('all');

const filteredAgreements = computed(() => {
    if (statusFilter.value === 'active') {
        return props.agreements.filter((item) => item.status === 'active');
    }

    if (statusFilter.value === 'draft') {
        return props.agreements.filter((item) => item.status === 'draft');
    }

    if (statusFilter.value === 'other') {
        return props.agreements.filter(
            (item) => item.status !== 'active' && item.status !== 'draft',
        );
    }

    return props.agreements;
});

// Create agreement state
const agreementFarmId = ref(
    props.farms[0]?.id ? String(props.farms[0].id) : '',
);
const agreementCycleId = ref('');
const agreementStatus = ref('active');
const capitalRecoveryRule = ref('capital_first');
const createInvestorShare = ref(40);
const createFarmShare = ref(60);
const formKey = ref(0);
const settingsKey = ref(0);

function updateCreateInvestorShare(value: string | number): void {
    const parsed = Math.max(0, Math.min(100, Number(value) || 0));
    createInvestorShare.value = parsed;
    createFarmShare.value = 100 - parsed;
}

const selectedFarm = computed(() =>
    props.farms.find((farm) => String(farm.id) === agreementFarmId.value),
);

watch(agreementFarmId, () => {
    agreementCycleId.value = '';
});

// Edit agreement state
const editCycleId = ref('');
const editStatus = ref('active');
const editCapitalRule = ref('capital_first');
const editInvestorShare = ref(40);
const editFarmShare = ref(60);
const editFormKey = ref(0);

const editFarmCycles = computed(() => {
    if (!editingAgreement.value) {
        return [];
    }

    return (
        props.farms.find((farm) => farm.id === editingAgreement.value?.farmId)
            ?.productionCycles ?? []
    );
});

function openEdit(agreement: InvestorManagementAgreement): void {
    editingAgreement.value = agreement;
    editCycleId.value = agreement.productionCycleId
        ? String(agreement.productionCycleId)
        : '';
    editStatus.value = agreement.status;
    editCapitalRule.value = agreement.capitalRecoveryRule;
    editInvestorShare.value = agreement.investorProfitSharePercentage;
    editFarmShare.value = agreement.farmProfitSharePercentage;
    editFormKey.value++;
}

function updateEditInvestorShare(value: string | number): void {
    const parsed = Math.max(0, Math.min(100, Number(value) || 0));
    editInvestorShare.value = parsed;
    editFarmShare.value = 100 - parsed;
}

const requiredCategoryIds = computed(() =>
    props.categories
        .filter((category) => category.requiresInvestorApproval)
        .map((category) => String(category.id)),
);

const totalQuestions = computed(
    () =>
        props.summary?.questionCount ??
        props.agreements.reduce(
            (sum, item) => sum + (item.comments?.length ?? 0),
            0,
        ),
);

function agreementStatusBadgeClass(status: string): string {
    switch (status) {
        case 'active':
            return 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800/60 dark:bg-emerald-950/60 dark:text-emerald-200';
        case 'completed':
            return 'border-cyan-200 bg-cyan-50 text-cyan-800 dark:border-cyan-800/60 dark:bg-cyan-950/60 dark:text-cyan-200';
        case 'suspended':
            return 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800/60 dark:bg-amber-950/60 dark:text-amber-200';
        case 'cancelled':
            return 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-800/60 dark:bg-rose-950/60 dark:text-rose-200';
        default:
            return 'border-border bg-muted text-muted-foreground';
    }
}

function fundingPercentFor(
    agreement: InvestorManagementAgreement,
): number | null {
    if (!(agreement.amountCommittedMinor > 0)) {
        return null;
    }

    return Math.min(
        100,
        Math.round(
            (agreement.amountFundedMinor / agreement.amountCommittedMinor) *
                100,
        ),
    );
}
</script>

<template>
    <div
        class="mx-auto flex w-full max-w-7xl min-w-0 flex-1 flex-col gap-6 p-4 md:p-6 lg:p-8"
    >
        <Head title="Investors" />

        <PageHero
            eyebrow="Investor Relations"
            title="Investors & Agreements"
            description="Manage investor commitments, capital recovery terms, approval rules, and direct Q&A across your farms."
        >
            <template #actions>
                <Button
                    v-if="
                        permissions.canManageInvestorAgreements &&
                        currentTeamSlug
                    "
                    type="button"
                    variant="secondary"
                    class="min-h-11 sm:min-h-9"
                    @click="createOpen = true"
                >
                    <Plus class="size-4" aria-hidden="true" />
                    New agreement
                </Button>
                <Button
                    v-if="
                        permissions.canManageApprovalRequests && currentTeamSlug
                    "
                    type="button"
                    variant="outline"
                    class="min-h-11 border-white/25 bg-white/10 text-white hover:bg-white/20 hover:text-white sm:min-h-9"
                    @click="rulesOpen = true"
                >
                    <Settings2 class="size-4" aria-hidden="true" />
                    Approval rules
                </Button>
                <Button
                    v-if="
                        permissions.canCreateInvitation &&
                        inviteContext &&
                        currentTeam
                    "
                    type="button"
                    variant="outline"
                    class="min-h-11 border-white/25 bg-white/10 text-white hover:bg-white/20 hover:text-white sm:min-h-9"
                    @click="inviteOpen = true"
                >
                    <UserPlus class="size-4" aria-hidden="true" />
                    Invite investor
                </Button>
                <Button
                    v-if="
                        permissions.canViewApprovalRequests && currentTeamSlug
                    "
                    variant="outline"
                    as-child
                    class="min-h-11 border-white/25 bg-white/10 text-white hover:bg-white/20 hover:text-white sm:min-h-9"
                >
                    <Link :href="approvalsIndex(currentTeamSlug)">
                        <ShieldCheck class="size-4" aria-hidden="true" />
                        Approvals
                        <ArrowRight class="size-4" aria-hidden="true" />
                    </Link>
                </Button>
            </template>
        </PageHero>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <MetricCard
                label="Investors"
                :value="summary?.investorCount ?? investors.length"
                subtext="Workspace investor members"
                tone="emerald"
                :icon="Users"
            />
            <MetricCard
                label="Active agreements"
                :value="
                    summary?.activeAgreementCount ??
                    agreements.filter((a) => a.status === 'active').length
                "
                :subtext="
                    summary?.draftAgreementCount
                        ? `${summary.draftAgreementCount} draft pending activation`
                        : 'Live investor scopes'
                "
                tone="cyan"
                :icon="Handshake"
            />
            <MetricCard
                label="Pending approvals"
                :value="summary?.pendingApprovalCount ?? 0"
                subtext="Open & clarification requests"
                tone="amber"
                :icon="ShieldCheck"
            />
            <MetricCard
                label="Investor questions"
                :value="totalQuestions"
                subtext="Recorded Q&A notes across agreements"
                tone="default"
                :icon="MessageSquare"
            />
        </div>

        <section
            v-if="inviteContext?.pendingInvestorInvitations?.length"
            class="rounded-2xl border bg-card p-4 shadow-xs sm:p-5"
        >
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-sm font-semibold">
                    Pending investor invitations
                </h2>
                <Badge variant="secondary">{{
                    inviteContext.pendingInvestorInvitations.length
                }}</Badge>
            </div>
            <ul class="mt-3 divide-y text-sm">
                <li
                    v-for="invitation in inviteContext.pendingInvestorInvitations"
                    :key="invitation.code"
                    class="flex flex-wrap items-center justify-between gap-2 py-2"
                >
                    <span class="font-medium">
                        {{
                            invitation.email ??
                            invitation.phone ??
                            `Invitation #${invitation.code}`
                        }}
                    </span>
                    <div
                        class="flex items-center gap-3 text-xs text-muted-foreground"
                    >
                        <a
                            v-if="invitation.phoneUrl"
                            :href="invitation.phoneUrl"
                            class="underline underline-offset-4"
                            >Share phone invite link</a
                        >
                        <span v-if="invitation.createdAt">
                            Sent {{ formatInvestorDate(invitation.createdAt) }}
                        </span>
                    </div>
                </li>
            </ul>
        </section>

        <section class="space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold tracking-tight">
                        Agreement register
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        Agreement scope, funding progress, terms, documents, and
                        investor Q&amp;A.
                    </p>
                </div>

                <div
                    v-if="agreements.length"
                    role="group"
                    aria-label="Filter agreements"
                    class="flex flex-wrap gap-1.5"
                >
                    <Button
                        type="button"
                        size="sm"
                        :variant="
                            statusFilter === 'all' ? 'default' : 'outline'
                        "
                        @click="statusFilter = 'all'"
                    >
                        All ({{ agreements.length }})
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        :variant="
                            statusFilter === 'active' ? 'default' : 'outline'
                        "
                        @click="statusFilter = 'active'"
                    >
                        Active
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        :variant="
                            statusFilter === 'draft' ? 'default' : 'outline'
                        "
                        @click="statusFilter = 'draft'"
                    >
                        Draft
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        :variant="
                            statusFilter === 'other' ? 'default' : 'outline'
                        "
                        @click="statusFilter = 'other'"
                    >
                        Completed / Other
                    </Button>
                </div>
            </div>

            <div
                v-if="filteredAgreements.length > 0"
                class="grid gap-4 xl:grid-cols-2"
            >
                <article
                    v-for="agreement in filteredAgreements"
                    :key="agreement.id"
                    class="flex flex-col justify-between gap-4 rounded-2xl border bg-card p-4 text-sm shadow-xs sm:p-5"
                >
                    <div class="space-y-4">
                        <div
                            class="flex flex-wrap items-start justify-between gap-3"
                        >
                            <div class="min-w-0 space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <FarmTypeBadge
                                        :farm-type="agreement.farmType"
                                        :label="agreement.farmTypeLabel"
                                    />
                                    <Badge
                                        variant="outline"
                                        :class="
                                            agreementStatusBadgeClass(
                                                agreement.status,
                                            )
                                        "
                                    >
                                        {{ agreement.statusLabel }}
                                    </Badge>
                                    <Badge
                                        v-if="agreement.pendingApprovalsCount"
                                        variant="secondary"
                                        class="bg-amber-100 text-amber-900 dark:bg-amber-950/70 dark:text-amber-200"
                                    >
                                        {{ agreement.pendingApprovalsCount }}
                                        pending
                                    </Badge>
                                </div>
                                <h3 class="text-base font-semibold break-words">
                                    {{ agreement.title }}
                                </h3>
                                <p class="text-xs text-muted-foreground">
                                    {{ agreement.investorName }} ({{
                                        agreement.investorEmail
                                    }}) · {{ agreement.farmName }}
                                    <template
                                        v-if="agreement.productionCycleName"
                                    >
                                        · {{ agreement.productionCycleName }}
                                    </template>
                                </p>
                            </div>
                        </div>

                        <div
                            v-if="fundingPercentFor(agreement) !== null"
                            class="space-y-1.5"
                        >
                            <div
                                class="flex items-center justify-between text-xs text-muted-foreground"
                            >
                                <span>Commitment funded</span>
                                <span class="font-medium tabular-nums">
                                    {{ fundingPercentFor(agreement) }}%
                                </span>
                            </div>
                            <div
                                class="h-1.5 w-full overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    class="h-full rounded-full bg-primary transition-all"
                                    :style="{
                                        width: `${fundingPercentFor(agreement)}%`,
                                    }"
                                />
                            </div>
                        </div>

                        <dl
                            class="grid grid-cols-3 gap-3 rounded-xl bg-muted/40 p-3"
                        >
                            <div class="min-w-0">
                                <dt class="text-xs text-muted-foreground">
                                    Committed
                                </dt>
                                <dd
                                    class="mt-0.5 font-semibold break-words tabular-nums"
                                >
                                    {{
                                        formatCurrency(
                                            agreement.amountCommitted,
                                            agreement.currency,
                                        )
                                    }}
                                </dd>
                            </div>
                            <div class="min-w-0">
                                <dt class="text-xs text-muted-foreground">
                                    Funded
                                </dt>
                                <dd
                                    class="mt-0.5 font-semibold break-words tabular-nums"
                                >
                                    {{
                                        formatCurrency(
                                            agreement.amountFunded,
                                            agreement.currency,
                                        )
                                    }}
                                </dd>
                            </div>
                            <div class="min-w-0">
                                <dt class="text-xs text-muted-foreground">
                                    Profit split
                                </dt>
                                <dd class="mt-0.5 font-semibold tabular-nums">
                                    {{
                                        agreement.investorProfitSharePercentage
                                    }}/{{ agreement.farmProfitSharePercentage }}
                                </dd>
                            </div>
                        </dl>

                        <p v-if="agreement.publicNotes" class="break-words">
                            {{ agreement.publicNotes }}
                        </p>

                        <p
                            v-if="agreement.internalNotes"
                            class="rounded-lg border border-dashed bg-muted/30 px-3 py-2 text-xs text-muted-foreground"
                        >
                            <span class="font-medium text-foreground"
                                >Internal notes:</span
                            >
                            {{ agreement.internalNotes }}
                        </p>

                        <div
                            v-if="agreement.documents.length"
                            class="flex flex-wrap gap-2"
                        >
                            <a
                                v-for="document in agreement.documents"
                                :key="document.id"
                                :href="document.downloadUrl"
                                class="inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-xs font-medium underline-offset-4 hover:bg-muted hover:underline"
                            >
                                <Paperclip
                                    class="size-3.5"
                                    aria-hidden="true"
                                />
                                {{ document.fileName }}
                            </a>
                        </div>
                    </div>

                    <div class="space-y-3 border-t pt-3">
                        <div class="flex flex-wrap gap-2">
                            <Button
                                v-if="permissions.canManageInvestorAgreements"
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="openEdit(agreement)"
                            >
                                <Pencil class="size-3.5" aria-hidden="true" />
                                Edit terms
                            </Button>
                            <Button
                                v-if="currentTeamSlug"
                                type="button"
                                variant="outline"
                                size="sm"
                                @click="qaAgreementId = agreement.id"
                            >
                                <MessageSquare
                                    class="size-3.5"
                                    aria-hidden="true"
                                />
                                Questions ({{
                                    agreement.comments?.length ?? 0
                                }})
                            </Button>
                        </div>

                        <Form
                            v-if="permissions.canManageInvestorAgreements"
                            v-bind="
                                storeAgreementDocument.form({
                                    current_team: currentTeamSlug,
                                    investor_agreement: agreement.id,
                                })
                            "
                            class="grid gap-2 sm:grid-cols-[1fr_auto]"
                            v-slot="{ errors, processing, progress }"
                        >
                            <div class="grid gap-1">
                                <Label
                                    :for="`doc-${agreement.id}`"
                                    class="text-xs text-muted-foreground"
                                >
                                    Attach or replace agreement document
                                </Label>
                                <Input
                                    :id="`doc-${agreement.id}`"
                                    name="agreement_document"
                                    type="file"
                                    required
                                />
                                <InputError
                                    :message="errors.agreement_document"
                                />
                                <progress
                                    v-if="progress"
                                    :value="progress.percentage"
                                    max="100"
                                    class="h-1 w-full"
                                />
                            </div>
                            <div class="flex items-end">
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    :disabled="processing"
                                    class="min-h-10 sm:min-h-9"
                                >
                                    <FileUp class="size-4" aria-hidden="true" />
                                    Upload
                                </Button>
                            </div>
                        </Form>
                    </div>
                </article>
            </div>
            <EmptyState
                v-else
                :icon="Handshake"
                :title="
                    agreements.length
                        ? 'No agreements match this filter'
                        : 'No investor agreements created yet'
                "
                :description="
                    agreements.length
                        ? 'Switch filters to view other investor agreements.'
                        : 'Create your first agreement or invite an investor member to get started.'
                "
            />
        </section>

        <SlideOverForm
            v-if="permissions.canManageApprovalRequests && currentTeamSlug"
            :open="rulesOpen"
            title="Approval rules"
            description="Configure automatic approval thresholds and category requirements across your farm."
            @update:open="rulesOpen = $event"
        >
            <Form
                :key="settingsKey"
                v-bind="updateApprovalSettings.form(currentTeamSlug)"
                class="grid gap-4"
                v-slot="{ errors, processing }"
                @success="
                    settingsKey++;
                    rulesOpen = false;
                "
            >
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="approval-currency">Currency</Label>
                        <Input
                            id="approval-currency"
                            name="currency"
                            :model-value="approvalSettings.currency"
                            maxlength="3"
                        />
                        <InputError :message="errors.currency" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="approval-threshold"
                            >Expense threshold</Label
                        >
                        <Input
                            id="approval-threshold"
                            name="threshold_amount"
                            :model-value="approvalSettings.expenseThreshold"
                            inputmode="decimal"
                            required
                        />
                        <InputError :message="errors.threshold_amount" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label>Required expense categories</Label>
                    <div class="grid gap-2 sm:grid-cols-2">
                        <label
                            v-for="category in categories"
                            :key="category.id"
                            class="flex items-center gap-2 rounded-lg border px-3 py-2 text-sm"
                        >
                            <input
                                type="checkbox"
                                name="required_expense_category_ids[]"
                                :value="category.id"
                                :checked="
                                    requiredCategoryIds.includes(
                                        String(category.id),
                                    )
                                "
                            />
                            <span>{{ category.name }}</span>
                        </label>
                    </div>
                    <InputError
                        :message="errors.required_expense_category_ids"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="approval-reason">Reason for change</Label>
                    <Input id="approval-reason" name="reason" />
                    <InputError :message="errors.reason" />
                </div>

                <Button :disabled="processing" class="min-h-11">
                    Save rules
                </Button>
            </Form>
        </SlideOverForm>

        <SlideOverForm
            v-if="permissions.canManageInvestorAgreements && currentTeamSlug"
            :open="createOpen"
            wide
            title="Create investor agreement"
            description="Define investor scope, capital commitment, recovery rule, profit share, and private document."
            @update:open="createOpen = $event"
        >
            <Form
                v-if="investors.length > 0 && farms.length > 0"
                :key="formKey"
                v-bind="storeAgreement.form(currentTeamSlug)"
                class="grid gap-4 sm:grid-cols-2"
                v-slot="{ errors, processing, progress }"
                @success="
                    formKey++;
                    createOpen = false;
                "
            >
                <div class="grid gap-2">
                    <Label for="agreement-investor">Investor</Label>
                    <select
                        id="agreement-investor"
                        name="investor_id"
                        class="h-10 rounded-md border bg-background px-3 text-sm"
                        required
                    >
                        <option
                            v-for="investor in investors"
                            :key="investor.id"
                            :value="investor.id"
                        >
                            {{ investor.name }} ({{ investor.email }})
                        </option>
                    </select>
                    <InputError :message="errors.investor_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="agreement-farm">Farm</Label>
                    <select
                        id="agreement-farm"
                        v-model="agreementFarmId"
                        name="farm_id"
                        class="h-10 rounded-md border bg-background px-3 text-sm"
                        required
                    >
                        <option
                            v-for="farm in farms"
                            :key="farm.id"
                            :value="String(farm.id)"
                        >
                            {{ farm.name }}
                        </option>
                    </select>
                    <InputError :message="errors.farm_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="agreement-cycle">Production cycle</Label>
                    <select
                        id="agreement-cycle"
                        v-model="agreementCycleId"
                        name="production_cycle_id"
                        class="h-10 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">Farm-level (all cycles)</option>
                        <option
                            v-for="cycle in selectedFarm?.productionCycles ??
                            []"
                            :key="cycle.id"
                            :value="String(cycle.id)"
                        >
                            {{ cycle.name }}
                        </option>
                    </select>
                    <InputError :message="errors.production_cycle_id" />
                </div>

                <div class="grid gap-2">
                    <Label for="agreement-status">Status</Label>
                    <select
                        id="agreement-status"
                        v-model="agreementStatus"
                        name="status"
                        class="h-10 rounded-md border bg-background px-3 text-sm"
                    >
                        <option
                            v-for="option in options.agreementStatuses"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <InputError :message="errors.status" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <Label for="agreement-title">Title</Label>
                    <Input id="agreement-title" name="title" required />
                    <InputError :message="errors.title" />
                </div>

                <div class="grid gap-2">
                    <Label for="agreement-currency">Currency</Label>
                    <Input
                        id="agreement-currency"
                        name="currency"
                        :model-value="approvalSettings.currency"
                        maxlength="3"
                    />
                    <InputError :message="errors.currency" />
                </div>

                <div class="grid gap-2">
                    <Label for="agreement-capital-rule">Recovery rule</Label>
                    <select
                        id="agreement-capital-rule"
                        v-model="capitalRecoveryRule"
                        name="capital_recovery_rule"
                        class="h-10 rounded-md border bg-background px-3 text-sm"
                    >
                        <option
                            v-for="option in options.capitalRecoveryRules"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <InputError :message="errors.capital_recovery_rule" />
                </div>

                <div class="grid gap-2">
                    <Label for="agreement-committed">Committed amount</Label>
                    <Input
                        id="agreement-committed"
                        name="amount_committed"
                        inputmode="decimal"
                        required
                    />
                    <InputError :message="errors.amount_committed" />
                </div>

                <div class="grid gap-2">
                    <Label for="agreement-funded">Funded amount</Label>
                    <Input
                        id="agreement-funded"
                        name="amount_funded"
                        inputmode="decimal"
                    />
                    <InputError :message="errors.amount_funded" />
                </div>

                <div class="grid gap-2">
                    <Label for="agreement-investor-share"
                        >Investor share %</Label
                    >
                    <Input
                        id="agreement-investor-share"
                        name="investor_profit_share_percentage"
                        type="number"
                        :model-value="createInvestorShare"
                        min="0"
                        max="100"
                        required
                        @update:model-value="updateCreateInvestorShare"
                    />
                    <InputError
                        :message="errors.investor_profit_share_percentage"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="agreement-farm-share">Farm share %</Label>
                    <Input
                        id="agreement-farm-share"
                        v-model="createFarmShare"
                        name="farm_profit_share_percentage"
                        type="number"
                        min="0"
                        max="100"
                        required
                    />
                    <InputError
                        :message="errors.farm_profit_share_percentage"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="agreement-starts">Starts on</Label>
                    <Input id="agreement-starts" name="starts_on" type="date" />
                    <InputError :message="errors.starts_on" />
                </div>

                <div class="grid gap-2">
                    <Label for="agreement-ends">Ends on</Label>
                    <Input id="agreement-ends" name="ends_on" type="date" />
                    <InputError :message="errors.ends_on" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <Label for="agreement-funding-model"
                        >Funding arrangement</Label
                    >
                    <Input
                        id="agreement-funding-model"
                        name="funding_model"
                        placeholder="e.g. Phased tranche releases per milestone"
                    />
                    <InputError :message="errors.funding_model" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <Label for="agreement-responsibilities"
                        >Role responsibilities</Label
                    >
                    <textarea
                        id="agreement-responsibilities"
                        name="role_responsibilities"
                        rows="2"
                        class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                    />
                    <InputError :message="errors.role_responsibilities" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <Label for="agreement-public-notes"
                        >Public notes (visible to investor)</Label
                    >
                    <textarea
                        id="agreement-public-notes"
                        name="public_notes"
                        rows="2"
                        class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                    />
                    <InputError :message="errors.public_notes" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <Label for="agreement-internal-notes"
                        >Internal notes (farm team only)</Label
                    >
                    <textarea
                        id="agreement-internal-notes"
                        name="internal_notes"
                        rows="2"
                        class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                    />
                    <InputError :message="errors.internal_notes" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <Label for="agreement-document">Agreement document</Label>
                    <Input
                        id="agreement-document"
                        name="agreement_document"
                        type="file"
                    />
                    <InputError :message="errors.agreement_document" />
                    <progress
                        v-if="progress"
                        :value="progress.percentage"
                        max="100"
                        class="h-1 w-full"
                    />
                </div>

                <div class="sm:col-span-2">
                    <Button :disabled="processing" class="min-h-11 w-full">
                        Create agreement
                    </Button>
                </div>
            </Form>
            <p v-else class="text-sm text-muted-foreground">
                Invite an investor role member and create a farm before adding
                agreements.
            </p>
        </SlideOverForm>

        <SlideOverForm
            v-if="
                permissions.canManageInvestorAgreements &&
                currentTeamSlug &&
                editingAgreement
            "
            :open="editingAgreement !== null"
            wide
            title="Edit agreement"
            :description="`${editingAgreement.investorName} · ${editingAgreement.farmName}`"
            @update:open="
                if (!$event) {
                    editingAgreement = null;
                }
            "
        >
            <Form
                :key="`edit-${editingAgreement.id}-${editFormKey}`"
                v-bind="
                    updateAgreement.form({
                        current_team: currentTeamSlug,
                        investor_agreement: editingAgreement.id,
                    })
                "
                class="grid gap-4 sm:grid-cols-2"
                v-slot="{ errors, processing }"
                @success="editingAgreement = null"
            >
                <div class="grid gap-2">
                    <Label for="edit-agreement-status">Status</Label>
                    <select
                        id="edit-agreement-status"
                        v-model="editStatus"
                        name="status"
                        class="h-10 rounded-md border bg-background px-3 text-sm"
                    >
                        <option
                            v-for="option in options.agreementStatuses"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <InputError :message="errors.status" />
                </div>

                <div class="grid gap-2">
                    <Label for="edit-agreement-cycle">Production cycle</Label>
                    <select
                        id="edit-agreement-cycle"
                        v-model="editCycleId"
                        name="production_cycle_id"
                        class="h-10 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">Farm-level (all cycles)</option>
                        <option
                            v-for="cycle in editFarmCycles"
                            :key="cycle.id"
                            :value="String(cycle.id)"
                        >
                            {{ cycle.name }}
                        </option>
                    </select>
                    <InputError :message="errors.production_cycle_id" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <Label for="edit-agreement-title">Title</Label>
                    <Input
                        id="edit-agreement-title"
                        name="title"
                        :model-value="editingAgreement.title"
                        required
                    />
                    <InputError :message="errors.title" />
                </div>

                <div class="grid gap-2">
                    <Label for="edit-agreement-currency">Currency</Label>
                    <Input
                        id="edit-agreement-currency"
                        name="currency"
                        :model-value="editingAgreement.currency"
                        maxlength="3"
                    />
                    <InputError :message="errors.currency" />
                </div>

                <div class="grid gap-2">
                    <Label for="edit-agreement-recovery">Recovery rule</Label>
                    <select
                        id="edit-agreement-recovery"
                        v-model="editCapitalRule"
                        name="capital_recovery_rule"
                        class="h-10 rounded-md border bg-background px-3 text-sm"
                    >
                        <option
                            v-for="option in options.capitalRecoveryRules"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <InputError :message="errors.capital_recovery_rule" />
                </div>

                <div class="grid gap-2">
                    <Label for="edit-agreement-committed"
                        >Committed amount</Label
                    >
                    <Input
                        id="edit-agreement-committed"
                        name="amount_committed"
                        :model-value="editingAgreement.amountCommitted"
                        inputmode="decimal"
                        required
                    />
                    <InputError :message="errors.amount_committed" />
                </div>

                <div class="grid gap-2">
                    <Label for="edit-agreement-funded">Funded amount</Label>
                    <Input
                        id="edit-agreement-funded"
                        name="amount_funded"
                        :model-value="editingAgreement.amountFunded"
                        inputmode="decimal"
                    />
                    <InputError :message="errors.amount_funded" />
                </div>

                <div class="grid gap-2">
                    <Label for="edit-investor-share">Investor share %</Label>
                    <Input
                        id="edit-investor-share"
                        name="investor_profit_share_percentage"
                        type="number"
                        :model-value="editInvestorShare"
                        min="0"
                        max="100"
                        required
                        @update:model-value="updateEditInvestorShare"
                    />
                    <InputError
                        :message="errors.investor_profit_share_percentage"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="edit-farm-share">Farm share %</Label>
                    <Input
                        id="edit-farm-share"
                        v-model="editFarmShare"
                        name="farm_profit_share_percentage"
                        type="number"
                        min="0"
                        max="100"
                        required
                    />
                    <InputError
                        :message="errors.farm_profit_share_percentage"
                    />
                </div>

                <div class="grid gap-2">
                    <Label for="edit-starts">Starts on</Label>
                    <Input
                        id="edit-starts"
                        name="starts_on"
                        :model-value="editingAgreement.startsOn ?? ''"
                        type="date"
                    />
                    <InputError :message="errors.starts_on" />
                </div>

                <div class="grid gap-2">
                    <Label for="edit-ends">Ends on</Label>
                    <Input
                        id="edit-ends"
                        name="ends_on"
                        :model-value="editingAgreement.endsOn ?? ''"
                        type="date"
                    />
                    <InputError :message="errors.ends_on" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <Label for="edit-funding-model">Funding arrangement</Label>
                    <Input
                        id="edit-funding-model"
                        name="funding_model"
                        :model-value="editingAgreement.fundingModel ?? ''"
                    />
                    <InputError :message="errors.funding_model" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <Label for="edit-responsibilities"
                        >Role responsibilities</Label
                    >
                    <textarea
                        id="edit-responsibilities"
                        name="role_responsibilities"
                        :value="editingAgreement.roleResponsibilities ?? ''"
                        rows="2"
                        class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                    />
                    <InputError :message="errors.role_responsibilities" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <Label for="edit-public-notes"
                        >Public notes (visible to investor)</Label
                    >
                    <textarea
                        id="edit-public-notes"
                        name="public_notes"
                        :value="editingAgreement.publicNotes ?? ''"
                        rows="2"
                        class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                    />
                    <InputError :message="errors.public_notes" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <Label for="edit-internal-notes"
                        >Internal notes (farm team only)</Label
                    >
                    <textarea
                        id="edit-internal-notes"
                        name="internal_notes"
                        :value="editingAgreement.internalNotes ?? ''"
                        rows="2"
                        class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                    />
                    <InputError :message="errors.internal_notes" />
                </div>

                <div class="sm:col-span-2">
                    <Button :disabled="processing" class="min-h-11 w-full">
                        Save agreement changes
                    </Button>
                </div>
            </Form>
        </SlideOverForm>

        <SlideOverForm
            v-if="qaAgreement && currentTeamSlug"
            :open="qaAgreement !== null"
            wide
            title="Investor Q&A"
            :description="`${qaAgreement.title} · ${qaAgreement.investorName}`"
            @update:open="
                if (!$event) {
                    qaAgreementId = null;
                }
            "
        >
            <div class="space-y-5">
                <Form
                    v-bind="
                        storeInvestorComment.form({
                            current_team: currentTeamSlug,
                            investor_agreement: qaAgreement.id,
                        })
                    "
                    class="grid gap-3 rounded-xl border bg-muted/20 p-4"
                    reset-on-success
                    v-slot="{ errors, processing }"
                >
                    <div class="grid gap-1.5">
                        <Label :for="`farm-reply-${qaAgreement.id}`">
                            Reply to {{ qaAgreement.investorName }}
                        </Label>
                        <textarea
                            :id="`farm-reply-${qaAgreement.id}`"
                            name="body"
                            rows="3"
                            maxlength="2000"
                            placeholder="Write a clear response visible in the investor portal..."
                            class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                            required
                        />
                        <InputError :message="errors.body" />
                    </div>
                    <div>
                        <Button :disabled="processing" class="min-h-10">
                            <MessageSquare class="size-4" aria-hidden="true" />
                            Send reply
                        </Button>
                    </div>
                </Form>

                <div v-if="qaAgreement.comments?.length" class="space-y-3">
                    <div
                        v-for="comment in qaAgreement.comments"
                        :key="comment.id"
                        class="rounded-xl border bg-card p-3.5 text-sm"
                    >
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold">{{
                                    comment.authorName
                                }}</span>
                                <Badge
                                    variant="outline"
                                    :class="
                                        comment.isInvestor === false
                                            ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800/60 dark:bg-emerald-950/60 dark:text-emerald-200'
                                            : ''
                                    "
                                >
                                    {{
                                        comment.isInvestor === false
                                            ? 'Farm team'
                                            : 'Investor'
                                    }}
                                </Badge>
                                <Badge variant="secondary">
                                    {{ comment.subjectLabel }}
                                </Badge>
                            </div>
                            <time
                                v-if="comment.createdAt"
                                :datetime="comment.createdAt"
                                class="text-xs text-muted-foreground"
                                >{{
                                    formatInvestorDate(comment.createdAt)
                                }}</time
                            >
                        </div>
                        <p class="mt-2 break-words whitespace-pre-wrap">
                            {{ comment.body }}
                        </p>
                    </div>
                </div>
                <p
                    v-else
                    class="rounded-xl border border-dashed p-5 text-sm text-muted-foreground"
                >
                    No questions or replies recorded for this agreement yet.
                </p>
            </div>
        </SlideOverForm>

        <InviteMemberModal
            v-if="currentTeam && inviteContext"
            :open="inviteOpen"
            :team="currentTeam"
            :available-roles="inviteContext.availableRoles"
            :phone-login-enabled="inviteContext.phoneLoginEnabled"
            :calling-code="inviteContext.callingCode"
            :phone-countries="inviteContext.phoneCountries"
            default-role="investor"
            @update:open="inviteOpen = $event"
        />
    </div>
</template>
