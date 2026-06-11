<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { FileUp, Handshake, Settings2 } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import { index as investorsIndex } from '@/routes/investors';
import { store as storeAgreement } from '@/routes/investors/agreements';
import { store as storeAgreementDocument } from '@/routes/investors/agreements/documents';
import { update as updateApprovalSettings } from '@/routes/investors/approval-settings';
import type {
    InvestorAgreement,
    InvestorApprovalSettings,
    InvestorExpenseCategory,
    InvestorFarm,
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
    agreements: InvestorAgreement[];
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
const agreementFarmId = ref(
    props.farms[0]?.id ? String(props.farms[0].id) : '',
);
const agreementCycleId = ref('');
const agreementStatus = ref('active');
const capitalRecoveryRule = ref('capital_first');
const formKey = ref(0);
const settingsKey = ref(0);

const selectedFarm = computed(() =>
    props.farms.find((farm) => String(farm.id) === agreementFarmId.value),
);

const requiredCategoryIds = computed(() =>
    props.categories
        .filter((category) => category.requiresInvestorApproval)
        .map((category) => String(category.id)),
);

watch(agreementFarmId, () => {
    agreementCycleId.value = '';
});
</script>

<template>
    <Head title="Investors" />

    <div class="flex flex-1 flex-col gap-8 p-4 md:p-6">
        <Heading
            title="Investors"
            description="Agreements, approval rules, and investor-safe transparency controls"
        />

        <section
            v-if="permissions.canManageApprovalRequests && currentTeamSlug"
            class="space-y-4 rounded-lg border p-4"
        >
            <div class="flex items-center gap-2">
                <Settings2 class="size-4" />
                <Heading
                    variant="small"
                    title="Approval rules"
                    description="Configure threshold and category-required investor approvals"
                />
            </div>

            <Form
                :key="settingsKey"
                v-bind="updateApprovalSettings.form(currentTeamSlug)"
                class="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
                v-slot="{ errors, processing }"
                @success="settingsKey++"
            >
                <div class="grid gap-2">
                    <Label for="approval-currency">Currency</Label>
                    <Input
                        id="approval-currency"
                        name="currency"
                        :value="approvalSettings.currency"
                        maxlength="3"
                    />
                    <InputError :message="errors.currency" />
                </div>
                <div class="grid gap-2">
                    <Label for="approval-threshold">Threshold</Label>
                    <Input
                        id="approval-threshold"
                        name="threshold_amount"
                        :value="approvalSettings.expenseThreshold"
                        inputmode="decimal"
                        required
                    />
                    <InputError :message="errors.threshold_amount" />
                </div>
                <div class="grid gap-2 md:col-span-2">
                    <Label>Required expense categories</Label>
                    <div class="grid gap-2 md:grid-cols-2">
                        <label
                            v-for="category in categories"
                            :key="category.id"
                            class="flex items-center gap-2 text-sm"
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
                <div class="grid gap-2 md:col-span-3">
                    <Label for="approval-reason">Reason</Label>
                    <Input id="approval-reason" name="reason" />
                    <InputError :message="errors.reason" />
                </div>
                <div class="flex items-end">
                    <Button :disabled="processing">Save rules</Button>
                </div>
            </Form>
        </section>

        <section
            v-if="permissions.canManageInvestorAgreements && currentTeamSlug"
            class="space-y-4 rounded-lg border p-4"
        >
            <div class="flex items-center gap-2">
                <Handshake class="size-4" />
                <Heading
                    variant="small"
                    title="Create agreement"
                    description="Capital-first terms, investor scope, and private agreement document"
                />
            </div>

            <Form
                v-if="investors.length > 0 && farms.length > 0"
                :key="formKey"
                v-bind="storeAgreement.form(currentTeamSlug)"
                class="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
                v-slot="{ errors, processing, progress }"
                @success="formKey++"
            >
                <div class="grid gap-2">
                    <Label for="agreement-investor">Investor</Label>
                    <select
                        id="agreement-investor"
                        name="investor_id"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                        required
                    >
                        <option
                            v-for="investor in investors"
                            :key="investor.id"
                            :value="investor.id"
                        >
                            {{ investor.name }} / {{ investor.email }}
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
                        class="h-9 rounded-md border bg-background px-3 text-sm"
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
                    <Label for="agreement-cycle">Cycle</Label>
                    <select
                        id="agreement-cycle"
                        v-model="agreementCycleId"
                        name="production_cycle_id"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">Farm-level</option>
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
                        class="h-9 rounded-md border bg-background px-3 text-sm"
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
                <div class="grid gap-2 md:col-span-2">
                    <Label for="agreement-title">Title</Label>
                    <Input id="agreement-title" name="title" required />
                    <InputError :message="errors.title" />
                </div>
                <div class="grid gap-2">
                    <Label for="agreement-currency">Currency</Label>
                    <Input
                        id="agreement-currency"
                        name="currency"
                        :value="approvalSettings.currency"
                        maxlength="3"
                    />
                    <InputError :message="errors.currency" />
                </div>
                <div class="grid gap-2">
                    <Label for="agreement-capital-rule"> Recovery rule </Label>
                    <select
                        id="agreement-capital-rule"
                        v-model="capitalRecoveryRule"
                        name="capital_recovery_rule"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
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
                    <Label for="agreement-investor-share">
                        Investor share %
                    </Label>
                    <Input
                        id="agreement-investor-share"
                        name="investor_profit_share_percentage"
                        type="number"
                        value="40"
                        min="0"
                        max="100"
                        required
                    />
                    <InputError
                        :message="errors.investor_profit_share_percentage"
                    />
                </div>
                <div class="grid gap-2">
                    <Label for="agreement-farm-share">Farm share %</Label>
                    <Input
                        id="agreement-farm-share"
                        name="farm_profit_share_percentage"
                        type="number"
                        value="60"
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
                <div class="grid gap-2 md:col-span-2">
                    <Label for="agreement-public-notes">Public notes</Label>
                    <Input id="agreement-public-notes" name="public_notes" />
                    <InputError :message="errors.public_notes" />
                </div>
                <div class="grid gap-2 md:col-span-2">
                    <Label for="agreement-internal-notes">Internal notes</Label>
                    <Input
                        id="agreement-internal-notes"
                        name="internal_notes"
                    />
                    <InputError :message="errors.internal_notes" />
                </div>
                <div class="grid gap-2">
                    <Label for="agreement-document">Document</Label>
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
                <div class="flex items-end">
                    <Button :disabled="processing">Create agreement</Button>
                </div>
            </Form>
            <p v-else class="text-sm text-muted-foreground">
                Invite an investor role member and create a farm before adding
                agreements.
            </p>
        </section>

        <section class="space-y-4">
            <Heading
                variant="small"
                title="Agreement register"
                description="Agreement scope, terms, documents, and visibility"
            />

            <div v-if="agreements.length > 0" class="grid gap-4 xl:grid-cols-2">
                <article
                    v-for="agreement in agreements"
                    :key="agreement.id"
                    class="space-y-4 rounded-lg border p-4 text-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="font-medium">{{ agreement.title }}</h3>
                            <p class="text-muted-foreground">
                                {{ agreement.investorName }} /
                                {{ agreement.farmName }}
                            </p>
                        </div>
                        <Badge variant="secondary">
                            {{ agreement.statusLabel }}
                        </Badge>
                    </div>
                    <div class="grid gap-2 md:grid-cols-3">
                        <p>
                            Committed {{ agreement.amountCommitted }}
                            {{ agreement.currency }}
                        </p>
                        <p>
                            Funded {{ agreement.amountFunded }}
                            {{ agreement.currency }}
                        </p>
                        <p>
                            Split
                            {{ agreement.investorProfitSharePercentage }}/{{
                                agreement.farmProfitSharePercentage
                            }}
                        </p>
                    </div>
                    <p v-if="agreement.publicNotes">
                        {{ agreement.publicNotes }}
                    </p>
                    <p
                        v-if="agreement.internalNotes"
                        class="text-muted-foreground"
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
                            class="underline underline-offset-4"
                        >
                            {{ document.fileName }}
                        </a>
                    </div>
                    <Form
                        v-if="permissions.canManageInvestorAgreements"
                        v-bind="
                            storeAgreementDocument.form({
                                current_team: currentTeamSlug,
                                investor_agreement: agreement.id,
                            })
                        "
                        class="grid gap-3 md:grid-cols-[1fr_auto]"
                        v-slot="{ errors, processing, progress }"
                    >
                        <div class="grid gap-2">
                            <Label :for="`doc-${agreement.id}`">
                                Attach document
                            </Label>
                            <Input
                                :id="`doc-${agreement.id}`"
                                name="agreement_document"
                                type="file"
                                required
                            />
                            <InputError :message="errors.agreement_document" />
                            <progress
                                v-if="progress"
                                :value="progress.percentage"
                                max="100"
                                class="h-1 w-full"
                            />
                        </div>
                        <div class="flex items-end">
                            <Button :disabled="processing">
                                <FileUp class="mr-2 size-4" />
                                Upload
                            </Button>
                        </div>
                    </Form>
                </article>
            </div>
            <p v-else class="text-sm text-muted-foreground">
                No investor agreements created yet.
            </p>
        </section>
    </div>
</template>
