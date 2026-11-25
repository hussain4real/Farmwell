<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import InvestorPortalFundingTimeline from '@/components/InvestorPortalFundingTimeline.vue';
import { Badge } from '@/components/ui/badge';
import { formatCurrency } from '@/lib/currency';
import type { InvestorPortalAgreement } from '@/types';

defineProps<{ agreement: InvestorPortalAgreement }>();
</script>

<template>
    <div class="grid gap-x-8 gap-y-6 lg:grid-cols-2">
        <InvestorPortalFundingTimeline
            :groups="agreement.fundingTimeline"
            class="lg:col-span-2"
        />
        <section class="space-y-3">
            <Heading
                variant="small"
                title="External transfers"
                description="Approved recorded releases and proof"
            />
            <div v-if="agreement.externalTransfers.length" class="space-y-3">
                <div
                    v-for="transfer in agreement.externalTransfers"
                    :key="transfer.id"
                    class="rounded-lg border p-3 text-sm"
                >
                    <div class="flex items-start justify-between gap-3">
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
                title="Approved expenses"
                description="Category, amount, status, and receipt evidence"
            />
            <div v-if="agreement.expenses.length" class="space-y-3">
                <div
                    v-for="expense in agreement.expenses"
                    :key="expense.id"
                    class="rounded-lg border p-3 text-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <p class="font-medium">
                            {{ expense.categoryName }}
                        </p>
                        <Badge variant="secondary">
                            {{
                                formatCurrency(expense.amount, expense.currency)
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
        <section class="space-y-3">
            <Heading
                variant="small"
                title="Harvest output"
                description="Approved output entries and evidence"
            />
            <div v-if="agreement.harvestRecords.length" class="space-y-3">
                <div
                    v-for="harvest in agreement.harvestRecords"
                    :key="harvest.id"
                    class="rounded-lg border p-3 text-sm"
                >
                    <div class="flex items-start justify-between gap-3">
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
            <div v-if="agreement.saleRecords.length" class="space-y-3">
                <div
                    v-for="sale in agreement.saleRecords"
                    :key="sale.id"
                    class="rounded-lg border p-3 text-sm"
                >
                    <div class="flex items-start justify-between gap-3">
                        <p class="font-medium">
                            {{ sale.buyerName }}
                        </p>
                        <Badge variant="secondary">
                            {{ formatCurrency(sale.netAmount, sale.currency) }}
                        </Badge>
                    </div>
                    <p class="mt-1 text-muted-foreground">
                        {{ sale.commodityName }} /
                        {{ sale.soldOn }}
                    </p>
                    <p class="mt-2">
                        Gross
                        {{ formatCurrency(sale.grossAmount, sale.currency) }},
                        deductions
                        {{
                            formatCurrency(sale.deductionAmount, sale.currency)
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
            <div v-if="agreement.distributionRecords.length" class="space-y-3">
                <div
                    v-for="distribution in agreement.distributionRecords"
                    :key="distribution.id"
                    class="rounded-lg border p-3 text-sm"
                >
                    <div class="flex items-start justify-between gap-3">
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
</template>
