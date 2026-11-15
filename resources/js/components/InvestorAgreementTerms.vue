<script setup lang="ts">
import { formatCurrency } from '@/lib/currency';
import type { InvestorAgreement } from '@/types';

defineProps<{
    agreement: InvestorAgreement;
}>();
</script>

<template>
    <section
        :aria-labelledby="`agreement-terms-${agreement.id}`"
        class="space-y-6 rounded-xl border bg-muted/20 p-4 sm:p-5"
    >
        <div>
            <h3 :id="`agreement-terms-${agreement.id}`" class="font-semibold">
                Your agreement
            </h3>
            <p class="mt-1 text-sm text-muted-foreground">
                Capital commitments, agreed scope and how profit is shared.
            </p>
        </div>

        <dl class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
            <div class="min-w-0">
                <dt class="text-sm text-muted-foreground">Committed capital</dt>
                <dd class="mt-1 text-xl font-semibold break-words tabular-nums">
                    {{
                        formatCurrency(
                            agreement.amountCommitted,
                            agreement.currency,
                        )
                    }}
                </dd>
            </div>
            <div class="min-w-0">
                <dt class="text-sm text-muted-foreground">Recorded funding</dt>
                <dd class="mt-1 text-xl font-semibold break-words tabular-nums">
                    {{
                        formatCurrency(
                            agreement.amountFunded,
                            agreement.currency,
                        )
                    }}
                </dd>
                <dd class="mt-1 text-xs text-muted-foreground">
                    Funding recorded against this agreement, separate from the
                    commitment.
                </dd>
            </div>
            <div>
                <dt class="text-sm text-muted-foreground">
                    Investor profit share
                </dt>
                <dd class="mt-1 text-xl font-semibold tabular-nums">
                    {{ agreement.investorProfitSharePercentage }}%
                </dd>
                <dd class="mt-1 text-xs text-muted-foreground">
                    {{ agreement.investorName }}
                </dd>
            </div>
            <div>
                <dt class="text-sm text-muted-foreground">
                    Farm / operator profit share
                </dt>
                <dd class="mt-1 text-xl font-semibold tabular-nums">
                    {{ agreement.farmProfitSharePercentage }}%
                </dd>
                <dd class="mt-1 text-xs text-muted-foreground">
                    {{ agreement.farmName }}
                </dd>
            </div>
        </dl>

        <div class="grid gap-6 border-t pt-5 lg:grid-cols-2">
            <section class="min-w-0 space-y-3">
                <h4 class="text-sm font-semibold">
                    Crop, season and agreed scope
                </h4>
                <dl
                    v-if="
                        agreement.productionCycleName ||
                        agreement.startsOn ||
                        agreement.endsOn
                    "
                    class="space-y-2 text-sm"
                >
                    <div v-if="agreement.productionCycleName">
                        <dt class="text-muted-foreground">Production cycle</dt>
                        <dd>{{ agreement.productionCycleName }}</dd>
                    </div>
                    <div v-if="agreement.startsOn || agreement.endsOn">
                        <dt class="text-muted-foreground">Agreement period</dt>
                        <dd>
                            {{ agreement.startsOn ?? 'Start not recorded' }}
                            <span aria-hidden="true"> · </span>
                            {{
                                agreement.endsOn
                                    ? `Ends ${agreement.endsOn}`
                                    : 'End not recorded'
                            }}
                        </dd>
                    </div>
                </dl>
                <p class="text-sm leading-7 break-words whitespace-pre-line">
                    {{
                        agreement.publicNotes ||
                        'No crop or season breakdown has been recorded for this agreement.'
                    }}
                </p>
            </section>

            <section class="min-w-0 space-y-3">
                <h4 class="text-sm font-semibold">
                    Funding and profit-sharing terms
                </h4>
                <dl class="space-y-4 text-sm">
                    <div>
                        <dt class="text-muted-foreground">Capital recovery</dt>
                        <dd class="mt-1 font-medium">
                            {{ agreement.capitalRecoveryRuleLabel }}
                        </dd>
                        <dd class="mt-1 leading-6 text-muted-foreground">
                            The agreed capital treatment applies before profit
                            is shared. See the agreement scope and document for
                            its conditions.
                        </dd>
                    </div>
                    <div v-if="agreement.fundingModel">
                        <dt class="text-muted-foreground">
                            Funding arrangement
                        </dt>
                        <dd
                            class="mt-1 leading-6 break-words whitespace-pre-line"
                        >
                            {{ agreement.fundingModel }}
                        </dd>
                    </div>
                    <div v-if="agreement.roleResponsibilities">
                        <dt class="text-muted-foreground">Responsibilities</dt>
                        <dd
                            class="mt-1 leading-6 break-words whitespace-pre-line"
                        >
                            {{ agreement.roleResponsibilities }}
                        </dd>
                    </div>
                </dl>
            </section>
        </div>
    </section>
</template>
