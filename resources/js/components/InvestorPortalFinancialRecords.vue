<script setup lang="ts">
import { Paperclip } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InvestorPortalFundingTimeline from '@/components/InvestorPortalFundingTimeline.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatCurrency } from '@/lib/currency';
import { formatInvestorDate } from '@/lib/investor-portal';
import type { InvestorPortalAgreement } from '@/types';

const props = defineProps<{ agreement: InvestorPortalAgreement }>();

type SectionFilter =
    | 'all'
    | 'timeline'
    | 'transfers_expenses'
    | 'harvests_sales'
    | 'distributions';

const activeSection = ref<SectionFilter>('all');

const sectionOptions: Array<{ value: SectionFilter; label: string }> = [
    { value: 'all', label: 'All records' },
    { value: 'timeline', label: 'Funding timeline' },
    { value: 'transfers_expenses', label: 'Transfers & expenses' },
    { value: 'harvests_sales', label: 'Harvests & sales' },
    { value: 'distributions', label: 'Distributions' },
];

const recoveryPercent = computed(() => {
    if (!(props.agreement.amountFundedMinor > 0)) {
        return 0;
    }

    return Math.min(
        100,
        Math.round(
            ((props.agreement.summary.capitalRecoveredMinor ?? 0) /
                props.agreement.amountFundedMinor) *
                100,
        ),
    );
});

const showSection = (section: Exclude<SectionFilter, 'all'>): boolean =>
    activeSection.value === 'all' || activeSection.value === section;
</script>

<template>
    <div class="space-y-6">
        <section
            aria-label="Capital recovery and profit distribution summary"
            class="rounded-2xl border bg-card p-4 shadow-xs sm:p-5"
        >
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h3 class="text-sm font-semibold">
                        Capital recovery &amp; profit split
                    </h3>
                    <p class="text-xs text-muted-foreground">
                        {{ agreement.capitalRecoveryRuleLabel }} ·
                        {{ agreement.investorProfitSharePercentage }}% investor
                        / {{ agreement.farmProfitSharePercentage }}% farm
                    </p>
                </div>
                <span
                    class="text-xs font-medium text-muted-foreground tabular-nums"
                >
                    {{ recoveryPercent }}% funded capital recovered
                </span>
            </div>
            <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-muted">
                <div
                    class="h-full rounded-full bg-emerald-600 transition-all dark:bg-emerald-500"
                    :style="{ width: `${recoveryPercent}%` }"
                />
            </div>
            <dl class="mt-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                <div>
                    <dt class="text-xs text-muted-foreground">Funded</dt>
                    <dd class="mt-0.5 font-semibold tabular-nums">
                        {{
                            formatCurrency(
                                agreement.amountFunded,
                                agreement.currency,
                            )
                        }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">
                        Capital recovered
                    </dt>
                    <dd class="mt-0.5 font-semibold tabular-nums">
                        {{
                            formatCurrency(
                                agreement.summary.capitalRecovered,
                                agreement.currency,
                            )
                        }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">
                        Unrecovered capital
                    </dt>
                    <dd class="mt-0.5 font-semibold tabular-nums">
                        {{
                            formatCurrency(
                                agreement.summary.unrecoveredCapital,
                                agreement.currency,
                            )
                        }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">
                        Investor profit share
                    </dt>
                    <dd class="mt-0.5 font-semibold tabular-nums">
                        {{
                            formatCurrency(
                                agreement.summary.investorShare,
                                agreement.currency,
                            )
                        }}
                    </dd>
                </div>
            </dl>
        </section>

        <div
            role="group"
            aria-label="Filter financial sections"
            class="flex flex-wrap gap-1.5"
        >
            <Button
                v-for="option in sectionOptions"
                :key="option.value"
                type="button"
                size="sm"
                :variant="
                    activeSection === option.value ? 'default' : 'outline'
                "
                @click="activeSection = option.value"
            >
                {{ option.label }}
            </Button>
        </div>

        <div class="grid gap-x-8 gap-y-6 lg:grid-cols-2">
            <InvestorPortalFundingTimeline
                v-if="showSection('timeline')"
                :groups="agreement.fundingTimeline"
                class="lg:col-span-2"
            />

            <section v-if="showSection('transfers_expenses')" class="space-y-3">
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
                        class="rounded-xl border bg-card p-3.5 text-sm shadow-xs"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <p class="font-medium capitalize">
                                {{ transfer.transferType.replaceAll('_', ' ') }}
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
                        <p class="mt-1 text-xs text-muted-foreground">
                            <time :datetime="transfer.transferredOn">{{
                                formatInvestorDate(transfer.transferredOn)
                            }}</time>
                        </p>
                        <div
                            v-if="transfer.proof.length"
                            class="mt-2.5 flex flex-wrap gap-2"
                        >
                            <a
                                v-for="proof in transfer.proof"
                                :key="proof.id"
                                :href="proof.downloadUrl"
                                class="inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-xs font-medium underline-offset-4 hover:bg-muted hover:underline"
                            >
                                <Paperclip class="size-3" aria-hidden="true" />
                                {{ proof.fileName }}
                            </a>
                        </div>
                    </div>
                </div>
                <p
                    v-else
                    class="rounded-xl border border-dashed p-4 text-sm text-muted-foreground"
                >
                    No approved external transfers yet.
                </p>
            </section>

            <section v-if="showSection('transfers_expenses')" class="space-y-3">
                <Heading
                    variant="small"
                    title="Approved expenses"
                    description="Category, amount, status, and receipt evidence"
                />
                <div v-if="agreement.expenses.length" class="space-y-3">
                    <div
                        v-for="expense in agreement.expenses"
                        :key="expense.id"
                        class="rounded-xl border bg-card p-3.5 text-sm shadow-xs"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-medium">
                                    {{ expense.categoryName }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    <time :datetime="expense.incurredOn">{{
                                        formatInvestorDate(expense.incurredOn)
                                    }}</time>
                                </p>
                            </div>
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
                            class="mt-2.5 flex flex-wrap gap-2"
                        >
                            <a
                                v-for="receipt in expense.receipts"
                                :key="receipt.id"
                                :href="receipt.downloadUrl"
                                class="inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-xs font-medium underline-offset-4 hover:bg-muted hover:underline"
                            >
                                <Paperclip class="size-3" aria-hidden="true" />
                                {{ receipt.fileName }}
                            </a>
                        </div>
                    </div>
                </div>
                <p
                    v-else
                    class="rounded-xl border border-dashed p-4 text-sm text-muted-foreground"
                >
                    No approved expenses yet.
                </p>
            </section>

            <section v-if="showSection('harvests_sales')" class="space-y-3">
                <Heading
                    variant="small"
                    title="Harvest output"
                    description="Approved output entries and evidence"
                />
                <div v-if="agreement.harvestRecords.length" class="space-y-3">
                    <div
                        v-for="harvest in agreement.harvestRecords"
                        :key="harvest.id"
                        class="rounded-xl border bg-card p-3.5 text-sm shadow-xs"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <p class="font-medium">
                                {{ harvest.commodityName }}
                            </p>
                            <Badge variant="secondary">
                                {{ harvest.statusLabel }}
                            </Badge>
                        </div>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ harvest.stageLabel }} ·
                            <time :datetime="harvest.harvestedOn">{{
                                formatInvestorDate(harvest.harvestedOn)
                            }}</time>
                        </p>
                        <p class="mt-2 font-medium tabular-nums">
                            {{ harvest.quantity }} {{ harvest.quantityUnit }}
                        </p>
                        <p
                            v-if="harvest.qualityNotes"
                            class="mt-1 text-xs text-muted-foreground"
                        >
                            {{ harvest.qualityNotes }}
                        </p>
                        <div
                            v-if="harvest.evidence.length"
                            class="mt-2.5 flex flex-wrap gap-2"
                        >
                            <a
                                v-for="evidence in harvest.evidence"
                                :key="evidence.id"
                                :href="evidence.downloadUrl"
                                class="inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-xs font-medium underline-offset-4 hover:bg-muted hover:underline"
                            >
                                <Paperclip class="size-3" aria-hidden="true" />
                                {{ evidence.fileName }}
                            </a>
                        </div>
                    </div>
                </div>
                <p
                    v-else
                    class="rounded-xl border border-dashed p-4 text-sm text-muted-foreground"
                >
                    No approved harvest output yet.
                </p>
            </section>

            <section v-if="showSection('harvests_sales')" class="space-y-3">
                <Heading
                    variant="small"
                    title="Sales"
                    description="Approved buyer records and net proceeds"
                />
                <div v-if="agreement.saleRecords.length" class="space-y-3">
                    <div
                        v-for="sale in agreement.saleRecords"
                        :key="sale.id"
                        class="rounded-xl border bg-card p-3.5 text-sm shadow-xs"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-medium">
                                    {{ sale.buyerName }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ sale.commodityName }} ·
                                    <time :datetime="sale.soldOn">{{
                                        formatInvestorDate(sale.soldOn)
                                    }}</time>
                                </p>
                            </div>
                            <Badge variant="secondary">
                                {{
                                    formatCurrency(
                                        sale.netAmount,
                                        sale.currency,
                                    )
                                }}
                            </Badge>
                        </div>
                        <p class="mt-2 text-xs text-muted-foreground">
                            {{ sale.quantity }} {{ sale.quantityUnit }} · Gross
                            {{
                                formatCurrency(sale.grossAmount, sale.currency)
                            }}, deductions
                            {{
                                formatCurrency(
                                    sale.deductionAmount,
                                    sale.currency,
                                )
                            }}
                            · {{ sale.paymentStatusLabel }}
                        </p>
                        <div
                            v-if="sale.evidence.length"
                            class="mt-2.5 flex flex-wrap gap-2"
                        >
                            <a
                                v-for="evidence in sale.evidence"
                                :key="evidence.id"
                                :href="evidence.downloadUrl"
                                class="inline-flex items-center gap-1.5 rounded-md border px-2.5 py-1 text-xs font-medium underline-offset-4 hover:bg-muted hover:underline"
                            >
                                <Paperclip class="size-3" aria-hidden="true" />
                                {{ evidence.fileName }}
                            </a>
                        </div>
                    </div>
                </div>
                <p
                    v-else
                    class="rounded-xl border border-dashed p-4 text-sm text-muted-foreground"
                >
                    No approved sales yet.
                </p>
            </section>

            <section
                v-if="showSection('distributions')"
                class="space-y-3 lg:col-span-2"
            >
                <Heading
                    variant="small"
                    title="Distributions"
                    description="Capital recovery before profit share"
                />
                <div
                    v-if="agreement.distributionRecords.length"
                    class="grid gap-3 md:grid-cols-2"
                >
                    <div
                        v-for="distribution in agreement.distributionRecords"
                        :key="distribution.id"
                        class="rounded-xl border bg-card p-3.5 text-sm shadow-xs"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-medium">
                                    {{ distribution.buyerName }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    <time
                                        :datetime="distribution.calculatedAt"
                                        >{{
                                            formatInvestorDate(
                                                distribution.calculatedAt,
                                            )
                                        }}</time
                                    >
                                </p>
                            </div>
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
                <p
                    v-else
                    class="rounded-xl border border-dashed p-4 text-sm text-muted-foreground"
                >
                    No approved distributions yet.
                </p>
            </section>
        </div>
    </div>
</template>
