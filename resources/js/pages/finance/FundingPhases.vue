<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { Banknote, Plus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import { index as financeIndex } from '@/routes/finance';
import {
    index as fundingPhasesIndex,
    store as storeFundingPhase,
} from '@/routes/finance/funding-phases';
import type {
    Budget,
    CarryForward,
    FinanceFarm,
    FinanceOptions,
    FinancePermissions,
    FundingPhase,
    Team,
} from '@/types';

type Props = {
    permissions: FinancePermissions;
    currency: string;
    farms: FinanceFarm[];
    budgets: Budget[];
    fundingPhases: FundingPhase[];
    carryForward: CarryForward;
    options: Pick<FinanceOptions, 'fundingPhaseStatuses'>;
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
                title: 'Finance',
                href: props.currentTeam
                    ? financeIndex(props.currentTeam.slug)
                    : '/',
            },
            {
                title: 'Funding Phases',
                href: props.currentTeam
                    ? fundingPhasesIndex(props.currentTeam.slug)
                    : '/',
            },
        ],
    }),
});

const currentTeam = computed(() => page.props.currentTeam as Team | null);
const currentTeamSlug = computed(() => currentTeam.value?.slug ?? '');
const phaseFarmId = ref(props.farms[0]?.id ? String(props.farms[0].id) : '');
const phaseCycleId = ref('');
const phaseBudgetId = ref('');
const phaseStatus = ref('draft');
const formKey = ref(0);

const selectedFarm = computed(() =>
    props.farms.find((farm) => String(farm.id) === phaseFarmId.value),
);

const farmBudgets = computed(() =>
    props.budgets.filter(
        (budget) => String(budget.farmId) === phaseFarmId.value,
    ),
);

watch(phaseFarmId, () => {
    phaseCycleId.value = '';
    phaseBudgetId.value = '';
});
</script>

<template>
    <Head title="Finance / Funding Phases" />

    <div class="flex flex-1 flex-col gap-8 p-4 md:p-6">
        <Heading
            title="Funding phases"
            description="Recorded releases, phase totals, milestones, and carry-forward balances"
        />

        <section class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-lg border p-4">
                <span class="text-sm text-muted-foreground">Released</span>
                <p class="mt-3 text-2xl font-semibold">
                    {{ carryForward.released }} {{ currency }}
                </p>
            </div>
            <div class="rounded-lg border p-4">
                <span class="text-sm text-muted-foreground">Spent</span>
                <p class="mt-3 text-2xl font-semibold">
                    {{ carryForward.spent }} {{ currency }}
                </p>
            </div>
            <div class="rounded-lg border p-4">
                <span class="text-sm text-muted-foreground">
                    Carry-forward
                </span>
                <p class="mt-3 text-2xl font-semibold">
                    {{ carryForward.carryForward }} {{ currency }}
                </p>
            </div>
        </section>

        <section
            v-if="permissions.canManageFinance && currentTeamSlug"
            class="space-y-4 rounded-lg border p-4"
        >
            <div class="flex items-center gap-2">
                <Plus class="size-4" />
                <Heading
                    variant="small"
                    title="Create funding phase"
                    description="External tracking only: recorded release and reconciliation support"
                />
            </div>

            <Form
                v-if="farms.length > 0"
                :key="formKey"
                v-bind="storeFundingPhase.form(currentTeamSlug)"
                class="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
                v-slot="{ errors, processing }"
                @success="formKey++"
            >
                <div class="grid gap-2">
                    <Label for="phase-farm">Farm</Label>
                    <select
                        id="phase-farm"
                        v-model="phaseFarmId"
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
                    <Label for="phase-cycle">Cycle</Label>
                    <select
                        id="phase-cycle"
                        v-model="phaseCycleId"
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
                    <Label for="phase-budget">Budget</Label>
                    <select
                        id="phase-budget"
                        v-model="phaseBudgetId"
                        name="budget_id"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">No budget</option>
                        <option
                            v-for="budget in farmBudgets"
                            :key="budget.id"
                            :value="String(budget.id)"
                        >
                            {{ budget.name }}
                        </option>
                    </select>
                    <InputError :message="errors.budget_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="phase-status">Status</Label>
                    <select
                        id="phase-status"
                        v-model="phaseStatus"
                        name="status"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option
                            v-for="option in options.fundingPhaseStatuses"
                            :key="option.value"
                            :value="option.value"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <InputError :message="errors.status" />
                </div>
                <div class="grid gap-2">
                    <Label for="phase-name">Name</Label>
                    <Input id="phase-name" name="name" required />
                    <InputError :message="errors.name" />
                </div>
                <div class="grid gap-2">
                    <Label for="phase-milestone">Milestone</Label>
                    <Input id="phase-milestone" name="milestone" />
                    <InputError :message="errors.milestone" />
                </div>
                <div class="grid gap-2">
                    <Label for="phase-planned">
                        Planned amount ({{ currency }})
                    </Label>
                    <Input id="phase-planned" name="planned_amount" />
                    <InputError :message="errors.planned_amount" />
                </div>
                <div class="grid gap-2">
                    <Label for="phase-requested">
                        Requested amount ({{ currency }})
                    </Label>
                    <Input id="phase-requested" name="requested_amount" />
                    <InputError :message="errors.requested_amount" />
                </div>
                <div class="grid gap-2">
                    <Label for="phase-approved">
                        Approved amount ({{ currency }})
                    </Label>
                    <Input id="phase-approved" name="approved_amount" />
                    <InputError :message="errors.approved_amount" />
                </div>
                <div class="grid gap-2">
                    <Label for="phase-released">
                        Recorded release ({{ currency }})
                    </Label>
                    <Input
                        id="phase-released"
                        name="externally_released_amount"
                    />
                    <InputError :message="errors.externally_released_amount" />
                </div>
                <div class="grid gap-2">
                    <Label for="phase-expected">Expected on</Label>
                    <Input id="phase-expected" name="expected_on" type="date" />
                    <InputError :message="errors.expected_on" />
                </div>
                <div class="grid gap-2">
                    <Label for="phase-released-on">Released on</Label>
                    <Input
                        id="phase-released-on"
                        name="released_on"
                        type="date"
                    />
                    <InputError :message="errors.released_on" />
                </div>
                <div class="flex items-end">
                    <Button :disabled="processing">Create phase</Button>
                </div>
            </Form>
        </section>

        <section class="space-y-4">
            <div class="flex items-center gap-2">
                <Banknote class="size-4" />
                <Heading
                    variant="small"
                    title="Funding phase register"
                    description="Phase totals and rolling balance"
                />
            </div>

            <div
                v-if="fundingPhases.length > 0"
                class="grid gap-4 xl:grid-cols-2"
            >
                <article
                    v-for="phase in fundingPhases"
                    :key="phase.id"
                    class="space-y-3 rounded-lg border p-4 text-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="font-medium">{{ phase.name }}</h3>
                            <p class="text-muted-foreground">
                                {{ phase.farmName }}
                                <span v-if="phase.productionCycleName">
                                    / {{ phase.productionCycleName }}
                                </span>
                            </p>
                        </div>
                        <Badge variant="secondary">
                            {{ phase.statusLabel }}
                        </Badge>
                    </div>
                    <div class="grid gap-2 md:grid-cols-4">
                        <p>Planned {{ phase.plannedAmount }}</p>
                        <p>Requested {{ phase.requestedAmount }}</p>
                        <p>Approved {{ phase.approvedAmount }}</p>
                        <p>Released {{ phase.externallyReleasedAmount }}</p>
                    </div>
                </article>
            </div>
            <p v-else class="text-sm text-muted-foreground">
                No funding phases created yet.
            </p>
        </section>
    </div>
</template>
