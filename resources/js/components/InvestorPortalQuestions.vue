<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { MessageSquare } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { formatInvestorDate } from '@/lib/investor-portal';
import { store as storeInvestorComment } from '@/routes/investor-comments';
import type { InvestorPortalAgreement } from '@/types';

const props = defineProps<{
    agreement: InvestorPortalAgreement;
    currentTeamSlug: string;
}>();

const selectedSubject = ref('');

type SubjectOption = {
    value: string;
    label: string;
    type: string;
    id: number;
};

const subjectOptions = computed<SubjectOption[]>(() => {
    const items: SubjectOption[] = [];

    for (const group of props.agreement.fundingTimeline) {
        for (const milestone of group.milestones) {
            items.push({
                value: `funding_phase:${milestone.id}`,
                label: `Funding: ${milestone.name}`,
                type: 'funding_phase',
                id: milestone.id,
            });
        }
    }

    for (const expense of props.agreement.expenses) {
        items.push({
            value: `expense:${expense.id}`,
            label: `Expense: ${expense.categoryName} (${expense.incurredOn})`,
            type: 'expense',
            id: expense.id,
        });
    }

    for (const harvest of props.agreement.harvestRecords) {
        items.push({
            value: `harvest:${harvest.id}`,
            label: `Harvest: ${harvest.commodityName} (${harvest.harvestedOn})`,
            type: 'harvest',
            id: harvest.id,
        });
    }

    for (const sale of props.agreement.saleRecords) {
        items.push({
            value: `sale:${sale.id}`,
            label: `Sale: ${sale.buyerName} (${sale.soldOn})`,
            type: 'sale',
            id: sale.id,
        });
    }

    for (const distribution of props.agreement.distributionRecords) {
        items.push({
            value: `distribution:${distribution.id}`,
            label: `Distribution: ${distribution.buyerName}`,
            type: 'distribution',
            id: distribution.id,
        });
    }

    for (const activity of props.agreement.activities) {
        items.push({
            value: `activity:${activity.id}`,
            label: `Update: ${activity.activityType} (${activity.activityDate})`,
            type: 'activity',
            id: activity.id,
        });
    }

    return items;
});

const parsedSubject = computed(() => {
    if (!selectedSubject.value) {
        return { type: '', id: '' };
    }

    const [type, id] = selectedSubject.value.split(':');

    return { type: type ?? '', id: id ?? '' };
});
</script>

<template>
    <section class="space-y-4">
        <Heading
            variant="small"
            title="Questions"
            description="Send a question about this agreement or a specific record to the farm team."
        />

        <Form
            v-if="currentTeamSlug"
            v-bind="
                storeInvestorComment.form({
                    current_team: currentTeamSlug,
                    investor_agreement: agreement.id,
                })
            "
            class="grid gap-3 rounded-2xl border bg-card p-4 shadow-xs sm:p-5"
            reset-on-success
            v-slot="{ errors, processing }"
            @success="selectedSubject = ''"
        >
            <div v-if="subjectOptions.length" class="grid gap-1.5">
                <Label :for="`comment-subject-${agreement.id}`">
                    Related record (optional)
                </Label>
                <select
                    :id="`comment-subject-${agreement.id}`"
                    v-model="selectedSubject"
                    class="h-10 w-full rounded-md border bg-background px-3 text-sm"
                >
                    <option value="">General agreement question</option>
                    <option
                        v-for="option in subjectOptions"
                        :key="option.value"
                        :value="option.value"
                    >
                        {{ option.label }}
                    </option>
                </select>
                <input
                    v-if="parsedSubject.type"
                    type="hidden"
                    name="subject_type"
                    :value="parsedSubject.type"
                />
                <input
                    v-if="parsedSubject.id"
                    type="hidden"
                    name="subject_id"
                    :value="parsedSubject.id"
                />
            </div>

            <div class="grid gap-1.5">
                <Label :for="`comment-${agreement.id}`">Question or note</Label>
                <textarea
                    :id="`comment-${agreement.id}`"
                    rows="3"
                    maxlength="2000"
                    name="body"
                    placeholder="Ask about a milestone, expense, yield, or update..."
                    class="w-full rounded-md border bg-background px-3 py-2 text-sm"
                    required
                />
                <InputError :message="errors.body" />
            </div>

            <div>
                <Button :disabled="processing" class="min-h-11 sm:min-h-9">
                    <MessageSquare class="size-4" aria-hidden="true" />
                    Send question
                </Button>
            </div>
        </Form>

        <div v-if="agreement.comments.length" class="space-y-3">
            <div
                v-for="comment in agreement.comments"
                :key="comment.id"
                class="rounded-2xl border bg-card p-4 text-sm shadow-xs"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="font-semibold">{{ comment.authorName }}</p>
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
                        >{{ formatInvestorDate(comment.createdAt) }}</time
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
            No questions recorded for this agreement yet.
        </p>
    </section>
</template>
