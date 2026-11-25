<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import {
    Download,
    HandCoins,
    Plus,
    ReceiptText,
    Scale,
    ShieldAlert,
    Sprout,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import MetricCard from '@/components/farm/MetricCard.vue';
import PageHero from '@/components/farm/PageHero.vue';
import SlideOverForm from '@/components/farm/SlideOverForm.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatCurrency } from '@/lib/currency';
import { dashboard } from '@/routes';
import {
    index as harvestsIndex,
    store as storeHarvest,
} from '@/routes/harvests';
import { index as distributionExports } from '@/routes/harvests/distributions/exports';
import { index as harvestExports } from '@/routes/harvests/exports';
import { store as storeSale } from '@/routes/harvests/sales';
import { index as salesExports } from '@/routes/harvests/sales/exports';
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

type ActiveWithdrawalWarning = {
    id: number;
    title: string;
    productName: string | null;
    farmName: string;
    cycleName: string | null;
    withdrawalEndsOn: string | null;
};

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
    activeWithdrawals?: ActiveWithdrawalWarning[];
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
const investorMode = computed(
    () => page.props.currentTeamFeatures?.investorMode === true,
);

const activeDrawer = ref<'harvest' | 'sale' | null>(null);

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
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Head title="Harvests & Sales" />

        <PageHero
            eyebrow="Output & Revenue"
            title="Harvests & sales"
            :description="
                investorMode
                    ? 'Output records, buyer sales, capital recovery, and investor distributions.'
                    : 'Track harvests, egg/fish/crop output, buyer sales, and net proceeds.'
            "
        >
            <template #actions>
                <Button
                    v-if="
                        (permissions.canManageFarmOperations ||
                            permissions.canManageFinance) &&
                        currentTeamSlug &&
                        farms.length > 0 &&
                        commodities.length > 0
                    "
                    type="button"
                    class="bg-white text-[#1b4d3e] hover:bg-emerald-50"
                    @click="activeDrawer = 'harvest'"
                >
                    <Plus class="mr-1.5 size-4" />
                    Record harvest
                </Button>
                <Button
                    v-if="
                        permissions.canManageFinance &&
                        currentTeamSlug &&
                        harvestRecords.length > 0
                    "
                    type="button"
                    variant="secondary"
                    @click="activeDrawer = 'sale'"
                >
                    <ReceiptText class="mr-1.5 size-4" />
                    Record sale
                </Button>
            </template>
        </PageHero>

        <section
            v-if="activeWithdrawals && activeWithdrawals.length > 0"
            class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200"
        >
            <div class="flex items-start gap-3">
                <ShieldAlert class="mt-0.5 size-5 shrink-0" />
                <div>
                    <p class="font-semibold">
                        Medication / chemical withdrawal active before harvest
                    </p>
                    <p
                        v-for="w in activeWithdrawals"
                        :key="w.id"
                        class="mt-1 text-xs"
                    >
                        <strong>{{ w.farmName }}</strong>
                        <span v-if="w.cycleName"> ({{ w.cycleName }})</span> —
                        {{ w.title }}: safe harvest on/after
                        <strong>{{ w.withdrawalEndsOn }}</strong>
                    </p>
                </div>
            </div>
        </section>

        <nav
            v-if="
                currentTeamSlug &&
                (permissions.canManageFarmOperations ||
                    permissions.canManageFinance)
            "
            aria-label="Report exports"
            class="flex flex-wrap gap-3 text-xs font-medium"
        >
            <Link
                :href="harvestExports(currentTeamSlug)"
                class="inline-flex items-center gap-1.5 rounded-lg border bg-card px-3 py-1.5 hover:bg-accent"
            >
                <Download class="size-3.5" />
                Export harvests XLSX
            </Link>
            <Link
                v-if="permissions.canManageFinance"
                :href="salesExports(currentTeamSlug)"
                class="inline-flex items-center gap-1.5 rounded-lg border bg-card px-3 py-1.5 hover:bg-accent"
            >
                <Download class="size-3.5" />
                Export sales XLSX
            </Link>
            <Link
                v-if="investorMode && permissions.canManageFinance"
                :href="distributionExports(currentTeamSlug)"
                class="inline-flex items-center gap-1.5 rounded-lg border bg-card px-3 py-1.5 hover:bg-accent"
            >
                <Download class="size-3.5" />
                Export distributions XLSX
            </Link>
        </nav>

        <section class="grid grid-cols-2 gap-4 xl:grid-cols-5">
            <MetricCard
                label="Harvest records"
                :value="summary.harvestRecords"
                :icon="Sprout"
                tone="emerald"
            />
            <MetricCard
                v-if="permissions.canViewFinance"
                label="Net sales"
                :value="formatCurrency(summary.saleNet, currency)"
                :icon="ReceiptText"
                tone="emerald"
            />
            <MetricCard
                v-if="investorMode && permissions.canViewFinance"
                label="Capital recovered"
                :value="formatCurrency(summary.capitalRecovered, currency)"
                :icon="Scale"
            />
            <MetricCard
                v-if="investorMode && permissions.canViewFinance"
                label="Investor share"
                :value="formatCurrency(summary.investorShare, currency)"
                :icon="HandCoins"
            />
            <MetricCard
                v-if="investorMode && permissions.canViewFinance"
                label="Farm share"
                :value="formatCurrency(summary.farmShare, currency)"
                :icon="HandCoins"
            />
        </section>

        <section
            class="grid gap-6"
            :class="{ 'xl:grid-cols-2': permissions.canViewFinance }"
        >
            <div class="space-y-4 rounded-2xl border bg-card p-5">
                <div class="flex items-center gap-2">
                    <Sprout class="size-4 text-primary" />
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
                        class="space-y-1.5 rounded-xl border p-3.5 text-sm"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold">
                                    {{ harvest.commodityName }} ·
                                    {{ harvest.stageLabel }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ harvest.farmName }} ·
                                    {{ harvest.harvestedOn }}
                                </p>
                            </div>
                            <Badge variant="secondary">
                                {{ harvest.statusLabel }}
                            </Badge>
                        </div>
                        <p class="text-xs text-muted-foreground tabular-nums">
                            {{ harvest.quantity }}
                            {{ harvest.quantityUnit }} harvested ·
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
                class="space-y-4 rounded-2xl border bg-card p-5"
            >
                <div class="flex items-center gap-2">
                    <ReceiptText class="size-4 text-primary" />
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
                        class="space-y-1.5 rounded-xl border p-3.5 text-sm"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-semibold">
                                    {{ sale.buyerName }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ sale.commodityName }} · {{ sale.soldOn }}
                                </p>
                            </div>
                            <Badge variant="secondary">
                                {{ sale.paymentStatusLabel }}
                            </Badge>
                        </div>
                        <p class="text-xs text-muted-foreground tabular-nums">
                            Net
                            {{ formatCurrency(sale.netAmount, sale.currency) }}
                            after
                            {{
                                formatCurrency(
                                    sale.deductionAmount,
                                    sale.currency,
                                )
                            }}
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
            v-if="investorMode && permissions.canViewFinance"
            class="space-y-4 rounded-2xl border bg-card p-5"
        >
            <div class="flex items-center gap-2">
                <HandCoins class="size-4 text-primary" />
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
                    class="grid gap-3 rounded-xl border p-3 text-sm lg:grid-cols-6"
                >
                    <div class="lg:col-span-2">
                        <p class="font-medium">
                            {{ distribution.investorAgreementTitle }}
                        </p>
                        <p class="text-xs text-muted-foreground">
                            {{ distribution.buyerName }}
                        </p>
                    </div>
                    <p class="tabular-nums">
                        Recovered
                        {{
                            formatCurrency(
                                distribution.capitalRecovered,
                                distribution.currency,
                            )
                        }}
                    </p>
                    <p class="tabular-nums">
                        Profit
                        {{
                            formatCurrency(
                                distribution.netProfit,
                                distribution.currency,
                            )
                        }}
                    </p>
                    <p class="tabular-nums">
                        Investor
                        {{
                            formatCurrency(
                                distribution.investorShare,
                                distribution.currency,
                            )
                        }}
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

        <!-- Record Harvest Drawer -->
        <SlideOverForm
            :open="activeDrawer === 'harvest'"
            title="Record harvest / output"
            wide
            @update:open="if (!$event) activeDrawer = null;"
        >
            <Form
                v-if="
                    farms.length > 0 &&
                    commodities.length > 0 &&
                    currentTeamSlug
                "
                :key="harvestFormKey"
                v-bind="storeHarvest.form(currentTeamSlug)"
                class="grid gap-4 sm:grid-cols-2"
                v-slot="{ errors, processing, progress }"
                @success="
                    harvestFormKey++;
                    activeDrawer = null;
                "
            >
                <div class="grid gap-1.5">
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
                </div>
                <div class="grid gap-1.5">
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
                </div>
                <div class="grid gap-1.5">
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
                </div>
                <div class="grid gap-1.5">
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
                </div>
                <div class="grid gap-1.5">
                    <Label for="harvest-date">Harvested on</Label>
                    <Input
                        id="harvest-date"
                        name="harvested_on"
                        type="date"
                        required
                    />
                    <InputError :message="errors.harvested_on" />
                </div>
                <div class="grid gap-1.5">
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
                </div>
                <div class="grid gap-1.5">
                    <Label for="harvest-sequence">Sequence</Label>
                    <Input
                        id="harvest-sequence"
                        name="sequence_number"
                        type="number"
                        min="1"
                        value="1"
                        required
                    />
                </div>
                <div class="grid gap-1.5">
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
                <div class="grid gap-1.5">
                    <Label for="harvest-unit-text">Quantity unit</Label>
                    <Input
                        id="harvest-unit-text"
                        name="quantity_unit"
                        required
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="harvest-labour-cost">Labour cost</Label>
                    <Input
                        id="harvest-labour-cost"
                        name="labour_cost"
                        type="number"
                        min="0"
                        step="0.01"
                    />
                </div>
                <div class="grid gap-1.5">
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
                </div>
                <template v-if="investorMode">
                    <div class="grid gap-1.5">
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
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="harvest-visibility"
                            >Investor visibility</Label
                        >
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
                    </div>
                </template>
                <input
                    v-else
                    type="hidden"
                    name="investor_visibility_status"
                    value="private"
                />
                <div class="grid gap-1.5 sm:col-span-2">
                    <Label for="harvest-quality">Quality notes</Label>
                    <textarea
                        id="harvest-quality"
                        name="quality_notes"
                        class="min-h-20 rounded-md border bg-background px-3 py-2 text-sm"
                    />
                </div>
                <div class="grid gap-1.5 sm:col-span-2">
                    <Label for="harvest-evidence">Evidence</Label>
                    <Input
                        id="harvest-evidence"
                        name="evidence[]"
                        type="file"
                        multiple
                    />
                    <p v-if="progress" class="text-xs text-muted-foreground">
                        Uploading {{ progress.percentage }}%
                    </p>
                </div>
                <div class="sm:col-span-2">
                    <Button :disabled="processing">Record harvest</Button>
                </div>
            </Form>
        </SlideOverForm>

        <!-- Record Sale Drawer -->
        <SlideOverForm
            :open="activeDrawer === 'sale'"
            title="Record sale"
            wide
            @update:open="if (!$event) activeDrawer = null;"
        >
            <Form
                v-if="harvestRecords.length > 0 && currentTeamSlug"
                :key="saleFormKey"
                v-bind="storeSale.form(currentTeamSlug)"
                class="grid gap-4 sm:grid-cols-2"
                v-slot="{ errors, processing, progress }"
                @success="
                    saleFormKey++;
                    activeDrawer = null;
                "
            >
                <div class="grid gap-1.5">
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
                </div>
                <div class="grid gap-1.5">
                    <Label for="sale-date">Sold on</Label>
                    <Input id="sale-date" name="sold_on" type="date" required />
                    <InputError :message="errors.sold_on" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="sale-buyer">Buyer</Label>
                    <Input id="sale-buyer" name="buyer_name" required />
                    <InputError :message="errors.buyer_name" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="sale-quantity">Quantity</Label>
                    <Input
                        id="sale-quantity"
                        name="quantity"
                        type="number"
                        min="0.01"
                        step="0.01"
                        required
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="sale-unit">Quantity unit</Label>
                    <Input id="sale-unit" name="quantity_unit" required />
                </div>
                <div class="grid gap-1.5">
                    <Label for="sale-unit-price">Unit price</Label>
                    <Input
                        id="sale-unit-price"
                        name="unit_price"
                        type="number"
                        min="0"
                        step="0.01"
                        required
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="sale-gross">Gross amount</Label>
                    <Input
                        id="sale-gross"
                        name="gross_amount"
                        type="number"
                        min="0"
                        step="0.01"
                        required
                    />
                </div>
                <div class="grid gap-1.5">
                    <Label for="sale-deductions">Deductions</Label>
                    <Input
                        id="sale-deductions"
                        name="deduction_amount"
                        type="number"
                        min="0"
                        step="0.01"
                    />
                </div>
                <div class="grid gap-1.5">
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
                </div>
                <div class="grid gap-1.5">
                    <Label for="sale-reference">Reference</Label>
                    <Input id="sale-reference" name="reference" />
                </div>
                <template v-if="investorMode">
                    <div class="grid gap-1.5">
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
                    </div>
                    <div class="grid gap-1.5">
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
                    </div>
                </template>
                <input
                    v-else
                    type="hidden"
                    name="investor_visibility_status"
                    value="private"
                />
                <div class="grid gap-1.5 sm:col-span-2">
                    <Label for="sale-evidence">Sale evidence</Label>
                    <Input
                        id="sale-evidence"
                        name="evidence[]"
                        type="file"
                        multiple
                    />
                    <p v-if="progress" class="text-xs text-muted-foreground">
                        Uploading {{ progress.percentage }}%
                    </p>
                </div>
                <div class="sm:col-span-2">
                    <Button :disabled="processing">Record sale</Button>
                </div>
            </Form>
        </SlideOverForm>
    </div>
</template>
