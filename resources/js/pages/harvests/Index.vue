<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { FileUp, HandCoins, ReceiptText, Scale, Sprout } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { dashboard } from '@/routes';
import {
    index as harvestsIndex,
    store as storeHarvest,
} from '@/routes/harvests';
import { store as storeSale } from '@/routes/harvests/sales';
import type {
    DistributionRecord,
    HarvestCommodity,
    HarvestFarm,
    HarvestInvestorAgreement,
    HarvestOptions,
    HarvestPermissions,
    HarvestRecord,
    HarvestSummary,
    SaleRecord,
    Team,
} from '@/types';

type Props = {
    permissions: HarvestPermissions;
    currency: string;
    summary: HarvestSummary;
    farms: HarvestFarm[];
    commodities: HarvestCommodity[];
    investorAgreements: HarvestInvestorAgreement[];
    harvestRecords: HarvestRecord[];
    saleRecords: SaleRecord[];
    distributionRecords: DistributionRecord[];
    options: HarvestOptions;
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
                title: 'Harvests',
                href: props.currentTeam
                    ? harvestsIndex(props.currentTeam.slug)
                    : '/',
            },
        ],
    }),
});

const currentTeam = computed(() => page.props.currentTeam as Team | null);
const currentTeamSlug = computed(() => currentTeam.value?.slug ?? '');

const harvestFarmId = ref(props.farms[0]?.id ? String(props.farms[0].id) : '');
const harvestUnitId = ref('');
const harvestCycleId = ref('');
const harvestCommodityId = ref(
    props.commodities[0]?.id ? String(props.commodities[0].id) : '',
);
const harvestInvestorAgreementId = ref('');
const harvestStage = ref('single');
const harvestStatus = ref('recorded');
const harvestVisibility = ref('private');
const harvestFormKey = ref(0);

const saleHarvestId = ref(
    props.harvestRecords[0]?.id ? String(props.harvestRecords[0].id) : '',
);
const saleInvestorAgreementId = ref(
    props.harvestRecords[0]?.investorAgreementId
        ? String(props.harvestRecords[0].investorAgreementId)
        : '',
);
const salePaymentStatus = ref('paid');
const saleVisibility = ref('private');
const saleFormKey = ref(0);

const selectedFarm = computed(() =>
    props.farms.find((farm) => String(farm.id) === harvestFarmId.value),
);

const farmInvestorAgreements = computed(() =>
    props.investorAgreements.filter(
        (agreement) =>
            String(agreement.farmId) === harvestFarmId.value &&
            (!harvestCycleId.value ||
                agreement.productionCycleId === null ||
                String(agreement.productionCycleId) === harvestCycleId.value),
    ),
);

const selectedSaleHarvest = computed(() =>
    props.harvestRecords.find(
        (harvest) => String(harvest.id) === saleHarvestId.value,
    ),
);

const saleInvestorAgreements = computed(() =>
    props.investorAgreements.filter((agreement) => {
        const harvest = selectedSaleHarvest.value;

        if (!harvest) {
            return false;
        }

        return (
            agreement.farmId === harvest.farmId &&
            (agreement.productionCycleId === null ||
                agreement.productionCycleId === harvest.productionCycleId)
        );
    }),
);

watch(harvestFarmId, () => {
    harvestUnitId.value = '';
    harvestCycleId.value = '';
    harvestInvestorAgreementId.value = '';
});

watch(harvestCycleId, () => {
    harvestInvestorAgreementId.value = '';
});

watch(saleHarvestId, () => {
    saleInvestorAgreementId.value = selectedSaleHarvest.value
        ?.investorAgreementId
        ? String(selectedSaleHarvest.value.investorAgreementId)
        : '';
});
</script>

<template>
    <Head title="Harvests" />

    <div class="flex flex-1 flex-col gap-8 p-4 md:p-6">
        <Heading
            title="Harvests"
            description="Output records, sales, capital recovery, and investor distribution calculations"
        />

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
            <div class="rounded-lg border p-4">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm text-muted-foreground">
                        Harvest records
                    </span>
                    <Sprout class="size-4 text-muted-foreground" />
                </div>
                <p class="mt-3 text-2xl font-semibold">
                    {{ summary.harvestRecords }}
                </p>
            </div>
            <div
                v-if="permissions.canViewFinance"
                class="rounded-lg border p-4"
            >
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm text-muted-foreground">
                        Net sales
                    </span>
                    <ReceiptText class="size-4 text-muted-foreground" />
                </div>
                <p class="mt-3 text-2xl font-semibold">
                    {{ summary.saleNet }} {{ currency }}
                </p>
            </div>
            <div
                v-if="permissions.canViewFinance"
                class="rounded-lg border p-4"
            >
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm text-muted-foreground">
                        Capital recovered
                    </span>
                    <Scale class="size-4 text-muted-foreground" />
                </div>
                <p class="mt-3 text-2xl font-semibold">
                    {{ summary.capitalRecovered }} {{ currency }}
                </p>
            </div>
            <div
                v-if="permissions.canViewFinance"
                class="rounded-lg border p-4"
            >
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm text-muted-foreground">
                        Investor share
                    </span>
                    <HandCoins class="size-4 text-muted-foreground" />
                </div>
                <p class="mt-3 text-2xl font-semibold">
                    {{ summary.investorShare }} {{ currency }}
                </p>
            </div>
            <div
                v-if="permissions.canViewFinance"
                class="rounded-lg border p-4"
            >
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm text-muted-foreground">
                        Farm share
                    </span>
                    <HandCoins class="size-4 text-muted-foreground" />
                </div>
                <p class="mt-3 text-2xl font-semibold">
                    {{ summary.farmShare }} {{ currency }}
                </p>
            </div>
        </section>

        <section
            v-if="
                (permissions.canManageFarmOperations ||
                    permissions.canManageFinance) &&
                currentTeamSlug
            "
            class="space-y-4 rounded-lg border p-4"
        >
            <div class="flex items-center gap-2">
                <FileUp class="size-4" />
                <Heading
                    variant="small"
                    title="Record harvest"
                    description="Recurring and staged output entries with private evidence"
                />
            </div>

            <Form
                v-if="farms.length > 0 && commodities.length > 0"
                :key="harvestFormKey"
                v-bind="storeHarvest.form(currentTeamSlug)"
                class="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
                v-slot="{ errors, processing, progress }"
                @success="harvestFormKey++"
            >
                <div class="grid gap-2">
                    <Label for="harvest-farm">Farm</Label>
                    <select
                        id="harvest-farm"
                        v-model="harvestFarmId"
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
                    <Label for="harvest-unit">Unit</Label>
                    <select
                        id="harvest-unit"
                        v-model="harvestUnitId"
                        name="production_unit_id"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">Farm-level</option>
                        <option
                            v-for="unit in selectedFarm?.productionUnits ?? []"
                            :key="unit.id"
                            :value="String(unit.id)"
                        >
                            {{ unit.name }}
                        </option>
                    </select>
                    <InputError :message="errors.production_unit_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="harvest-cycle">Cycle</Label>
                    <select
                        id="harvest-cycle"
                        v-model="harvestCycleId"
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
                    <Label for="harvest-commodity">Commodity</Label>
                    <select
                        id="harvest-commodity"
                        v-model="harvestCommodityId"
                        name="commodity_id"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                        required
                    >
                        <option
                            v-for="commodity in commodities"
                            :key="commodity.id"
                            :value="String(commodity.id)"
                        >
                            {{ commodity.name }}
                        </option>
                    </select>
                    <InputError :message="errors.commodity_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="harvest-agreement">Agreement</Label>
                    <select
                        id="harvest-agreement"
                        v-model="harvestInvestorAgreementId"
                        name="investor_agreement_id"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">No investor agreement</option>
                        <option
                            v-for="agreement in farmInvestorAgreements"
                            :key="agreement.id"
                            :value="String(agreement.id)"
                        >
                            {{ agreement.title }}
                        </option>
                    </select>
                    <InputError :message="errors.investor_agreement_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="harvest-date">Harvested on</Label>
                    <Input
                        id="harvest-date"
                        name="harvested_on"
                        type="date"
                        required
                    />
                    <InputError :message="errors.harvested_on" />
                </div>
                <div class="grid gap-2">
                    <Label for="harvest-stage">Stage</Label>
                    <select
                        id="harvest-stage"
                        v-model="harvestStage"
                        name="stage"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                        required
                    >
                        <option
                            v-for="stage in options.harvestStages"
                            :key="stage.value"
                            :value="stage.value"
                        >
                            {{ stage.label }}
                        </option>
                    </select>
                    <InputError :message="errors.stage" />
                </div>
                <div class="grid gap-2">
                    <Label for="harvest-sequence">Sequence</Label>
                    <Input
                        id="harvest-sequence"
                        name="sequence_number"
                        type="number"
                        min="1"
                        value="1"
                        required
                    />
                    <InputError :message="errors.sequence_number" />
                </div>
                <div class="grid gap-2">
                    <Label for="harvest-quantity">Quantity</Label>
                    <Input
                        id="harvest-quantity"
                        name="quantity"
                        type="number"
                        min="0.01"
                        step="0.01"
                        required
                    />
                    <InputError :message="errors.quantity" />
                </div>
                <div class="grid gap-2">
                    <Label for="harvest-unit-text">Quantity unit</Label>
                    <Input
                        id="harvest-unit-text"
                        name="quantity_unit"
                        required
                    />
                    <InputError :message="errors.quantity_unit" />
                </div>
                <div class="grid gap-2">
                    <Label for="harvest-labour-cost">Labour cost</Label>
                    <Input
                        id="harvest-labour-cost"
                        name="labour_cost"
                        type="number"
                        min="0"
                        step="0.01"
                    />
                    <InputError :message="errors.labour_cost" />
                </div>
                <div class="grid gap-2">
                    <Label for="harvest-status">Status</Label>
                    <select
                        id="harvest-status"
                        v-model="harvestStatus"
                        name="status"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option
                            v-for="status in options.harvestStatuses"
                            :key="status.value"
                            :value="status.value"
                        >
                            {{ status.label }}
                        </option>
                    </select>
                    <InputError :message="errors.status" />
                </div>
                <div class="grid gap-2">
                    <Label for="harvest-visibility">Investor visibility</Label>
                    <select
                        id="harvest-visibility"
                        v-model="harvestVisibility"
                        name="investor_visibility_status"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option
                            v-for="status in options.investorVisibilityStatuses"
                            :key="status.value"
                            :value="status.value"
                        >
                            {{ status.label }}
                        </option>
                    </select>
                    <InputError :message="errors.investor_visibility_status" />
                </div>
                <div class="grid gap-2 md:col-span-2">
                    <Label for="harvest-quality">Quality notes</Label>
                    <textarea
                        id="harvest-quality"
                        name="quality_notes"
                        class="min-h-24 rounded-md border bg-background px-3 py-2 text-sm"
                    />
                    <InputError :message="errors.quality_notes" />
                </div>
                <div class="grid gap-2 md:col-span-2">
                    <Label for="harvest-evidence">Evidence</Label>
                    <Input
                        id="harvest-evidence"
                        name="evidence[]"
                        type="file"
                        multiple
                    />
                    <InputError :message="errors.evidence" />
                    <p v-if="progress" class="text-sm text-muted-foreground">
                        Uploading {{ progress.percentage }}%
                    </p>
                </div>
                <div class="flex items-end">
                    <Button :disabled="processing">Record harvest</Button>
                </div>
            </Form>
        </section>

        <section
            v-if="permissions.canManageFinance && currentTeamSlug"
            class="space-y-4 rounded-lg border p-4"
        >
            <div class="flex items-center gap-2">
                <ReceiptText class="size-4" />
                <Heading
                    variant="small"
                    title="Record sale"
                    description="Sales recalculate capital recovery and distribution records"
                />
            </div>

            <Form
                v-if="harvestRecords.length > 0"
                :key="saleFormKey"
                v-bind="storeSale.form(currentTeamSlug)"
                class="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
                v-slot="{ errors, processing, progress }"
                @success="saleFormKey++"
            >
                <div class="grid gap-2">
                    <Label for="sale-harvest">Harvest</Label>
                    <select
                        id="sale-harvest"
                        v-model="saleHarvestId"
                        name="harvest_record_id"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                        required
                    >
                        <option
                            v-for="harvest in harvestRecords"
                            :key="harvest.id"
                            :value="String(harvest.id)"
                        >
                            {{ harvest.commodityName }} /
                            {{ harvest.remainingQuantity }}
                            {{ harvest.quantityUnit }}
                        </option>
                    </select>
                    <InputError :message="errors.harvest_record_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="sale-agreement">Agreement</Label>
                    <select
                        id="sale-agreement"
                        v-model="saleInvestorAgreementId"
                        name="investor_agreement_id"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">No investor agreement</option>
                        <option
                            v-for="agreement in saleInvestorAgreements"
                            :key="agreement.id"
                            :value="String(agreement.id)"
                        >
                            {{ agreement.title }}
                        </option>
                    </select>
                    <InputError :message="errors.investor_agreement_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="sale-date">Sold on</Label>
                    <Input id="sale-date" name="sold_on" type="date" required />
                    <InputError :message="errors.sold_on" />
                </div>
                <div class="grid gap-2">
                    <Label for="sale-buyer">Buyer</Label>
                    <Input id="sale-buyer" name="buyer_name" required />
                    <InputError :message="errors.buyer_name" />
                </div>
                <div class="grid gap-2">
                    <Label for="sale-quantity">Quantity</Label>
                    <Input
                        id="sale-quantity"
                        name="quantity"
                        type="number"
                        min="0.01"
                        step="0.01"
                        required
                    />
                    <InputError :message="errors.quantity" />
                </div>
                <div class="grid gap-2">
                    <Label for="sale-unit">Quantity unit</Label>
                    <Input id="sale-unit" name="quantity_unit" required />
                    <InputError :message="errors.quantity_unit" />
                </div>
                <div class="grid gap-2">
                    <Label for="sale-unit-price">Unit price</Label>
                    <Input
                        id="sale-unit-price"
                        name="unit_price"
                        type="number"
                        min="0"
                        step="0.01"
                        required
                    />
                    <InputError :message="errors.unit_price" />
                </div>
                <div class="grid gap-2">
                    <Label for="sale-gross">Gross amount</Label>
                    <Input
                        id="sale-gross"
                        name="gross_amount"
                        type="number"
                        min="0"
                        step="0.01"
                        required
                    />
                    <InputError :message="errors.gross_amount" />
                </div>
                <div class="grid gap-2">
                    <Label for="sale-deductions">Deductions</Label>
                    <Input
                        id="sale-deductions"
                        name="deduction_amount"
                        type="number"
                        min="0"
                        step="0.01"
                    />
                    <InputError :message="errors.deduction_amount" />
                </div>
                <div class="grid gap-2">
                    <Label for="sale-payment-status">Payment status</Label>
                    <select
                        id="sale-payment-status"
                        v-model="salePaymentStatus"
                        name="payment_status"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option
                            v-for="status in options.salePaymentStatuses"
                            :key="status.value"
                            :value="status.value"
                        >
                            {{ status.label }}
                        </option>
                    </select>
                    <InputError :message="errors.payment_status" />
                </div>
                <div class="grid gap-2">
                    <Label for="sale-visibility">Investor visibility</Label>
                    <select
                        id="sale-visibility"
                        v-model="saleVisibility"
                        name="investor_visibility_status"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option
                            v-for="status in options.investorVisibilityStatuses"
                            :key="status.value"
                            :value="status.value"
                        >
                            {{ status.label }}
                        </option>
                    </select>
                    <InputError :message="errors.investor_visibility_status" />
                </div>
                <div class="grid gap-2">
                    <Label for="sale-reference">Reference</Label>
                    <Input id="sale-reference" name="reference" />
                    <InputError :message="errors.reference" />
                </div>
                <div class="grid gap-2 md:col-span-2">
                    <Label for="sale-evidence">Sale evidence</Label>
                    <Input
                        id="sale-evidence"
                        name="evidence[]"
                        type="file"
                        multiple
                    />
                    <InputError :message="errors.evidence" />
                    <p v-if="progress" class="text-sm text-muted-foreground">
                        Uploading {{ progress.percentage }}%
                    </p>
                </div>
                <div class="flex items-end">
                    <Button :disabled="processing">Record sale</Button>
                </div>
            </Form>
        </section>

        <section
            class="grid gap-6"
            :class="{ 'xl:grid-cols-2': permissions.canViewFinance }"
        >
            <div class="space-y-4 rounded-lg border p-4">
                <div class="flex items-center gap-2">
                    <Sprout class="size-4" />
                    <Heading
                        variant="small"
                        title="Harvest records"
                        description="Output entries by commodity, stage, and sale status"
                    />
                </div>
                <div v-if="harvestRecords.length" class="space-y-3">
                    <article
                        v-for="harvest in harvestRecords"
                        :key="harvest.id"
                        class="space-y-2 rounded-lg border p-3 text-sm"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-medium">
                                    {{ harvest.commodityName }} /
                                    {{ harvest.stageLabel }}
                                </p>
                                <p class="text-muted-foreground">
                                    {{ harvest.farmName }} /
                                    {{ harvest.harvestedOn }}
                                </p>
                            </div>
                            <Badge variant="secondary">
                                {{ harvest.statusLabel }}
                            </Badge>
                        </div>
                        <p>
                            {{ harvest.quantity }} {{ harvest.quantityUnit }}
                            harvested,
                            {{ harvest.remainingQuantity }}
                            {{ harvest.quantityUnit }} remaining
                        </p>
                    </article>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    No harvest records yet.
                </p>
            </div>

            <div
                v-if="permissions.canViewFinance"
                class="space-y-4 rounded-lg border p-4"
            >
                <div class="flex items-center gap-2">
                    <ReceiptText class="size-4" />
                    <Heading
                        variant="small"
                        title="Sales"
                        description="Buyer records, net proceeds, and payment status"
                    />
                </div>
                <div v-if="saleRecords.length" class="space-y-3">
                    <article
                        v-for="sale in saleRecords"
                        :key="sale.id"
                        class="space-y-2 rounded-lg border p-3 text-sm"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-medium">
                                    {{ sale.buyerName }}
                                </p>
                                <p class="text-muted-foreground">
                                    {{ sale.commodityName }} /
                                    {{ sale.soldOn }}
                                </p>
                            </div>
                            <Badge variant="secondary">
                                {{ sale.paymentStatusLabel }}
                            </Badge>
                        </div>
                        <p>
                            Net {{ sale.netAmount }} {{ sale.currency }} after
                            {{ sale.deductionAmount }}
                            deductions
                        </p>
                    </article>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    No sales recorded yet.
                </p>
            </div>
        </section>

        <section
            v-if="permissions.canViewFinance"
            class="space-y-4 rounded-lg border p-4"
        >
            <div class="flex items-center gap-2">
                <HandCoins class="size-4" />
                <Heading
                    variant="small"
                    title="Capital recovery and distributions"
                    description="Capital-first recovery before configurable profit sharing"
                />
            </div>
            <div v-if="distributionRecords.length" class="space-y-3">
                <article
                    v-for="distribution in distributionRecords"
                    :key="distribution.id"
                    class="grid gap-3 rounded-lg border p-3 text-sm lg:grid-cols-6"
                >
                    <div class="lg:col-span-2">
                        <p class="font-medium">
                            {{ distribution.investorAgreementTitle }}
                        </p>
                        <p class="text-muted-foreground">
                            {{ distribution.buyerName }}
                        </p>
                    </div>
                    <p>
                        Recovered
                        {{ distribution.capitalRecovered }}
                        {{ distribution.currency }}
                    </p>
                    <p>
                        Profit
                        {{ distribution.netProfit }}
                        {{ distribution.currency }}
                    </p>
                    <p>
                        Investor
                        {{ distribution.investorShare }}
                        {{ distribution.currency }}
                    </p>
                    <Badge variant="secondary" class="w-fit">
                        {{ distribution.statusLabel }}
                    </Badge>
                </article>
            </div>
            <p v-else class="text-sm text-muted-foreground">
                No distribution records yet.
            </p>
        </section>
    </div>
</template>
