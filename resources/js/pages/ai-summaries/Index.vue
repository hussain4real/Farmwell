<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { FileText } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import SummaryText from '@/components/SummaryText.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useQueuedPolling } from '@/composables/useQueuedPolling';
import { useSummaryReviewText } from '@/composables/useSummaryReviewText';
import { dashboard } from '@/routes';
import {
    index as summariesIndex,
    publish,
    review,
    store,
    withdraw,
} from '@/routes/ai-summaries';
import type { Team } from '@/types';

type Summary = {
    id: number;
    status:
        'queued' | 'draft' | 'approved' | 'rejected' | 'failed' | 'published';
    type: string;
    publication_current: boolean;
    source_snapshot:
        | {
              id: number;
              date: string;
              type: string;
              status: string;
              summary: string;
          }[]
        | null;
    generated_text: string | null;
    generated_html: string | null;
    final_html: string | null;
    final_text: string | null;
    created_at: string;
    agreement_label: string;
};

const props = defineProps<{
    summaries: Summary[];
    agreements: {
        id: number;
        label: string;
        farm_id: number;
        cycle_id: number | null;
    }[];
    farms: {
        id: number;
        label: string;
        cycles: { id: number; label: string }[];
    }[];
    intakes: {
        id: number;
        farm_id: number | null;
        cycle_id: number | null;
        date: string | null;
    }[];
    canManage: boolean;
    canManageInvestors: boolean;
    canManageOperations: boolean;
}>();

const summaryType = ref(
    props.canManageInvestors ? 'investor_progress' : 'weekly',
);
const selectedFarm = ref('');
const selectedCycle = ref('');
watch(selectedFarm, () => {
    selectedCycle.value = '';
});
const selectedFarmDetails = computed(() =>
    props.farms.find((farm) => farm.id === Number(selectedFarm.value)),
);
const availableIntakes = computed(() =>
    props.intakes.filter(
        (item) =>
            item.farm_id === Number(selectedFarm.value) &&
            (selectedCycle.value === '' ||
                item.cycle_id === Number(selectedCycle.value)),
    ),
);
const typeLabels: Record<string, string> = {
    investor_progress: 'Investor progress',
    weekly: 'Weekly summary',
    risk_decision: 'Risks and decisions',
    intake_cleanup: 'Intake cleanup',
};
const page = usePage();
const currentTeam = computed(() => page.props.currentTeam);
const currentTeamSlug = computed(() => currentTeam.value?.slug ?? '');

useQueuedPolling('summaries', () =>
    props.summaries.some((summary) => summary.status === 'queued'),
);
const reviewTexts = useSummaryReviewText(() => props.summaries);

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
                title: 'AI summaries',
                href: props.currentTeam
                    ? summariesIndex(props.currentTeam.slug)
                    : '/',
            },
        ],
    }),
});
</script>

<template>
    <Head title="AI summaries" />

    <div class="flex flex-1 flex-col gap-8 p-4 md:p-6">
        <Heading
            title="AI summaries"
            description="Generate and review farm summaries"
        />

        <section
            v-if="canManage && currentTeamSlug"
            class="space-y-4 rounded-xl border border-[#d6e5dc] bg-card p-5 dark:border-border"
        >
            <Heading
                variant="small"
                title="Create a draft"
                description="Selected records are sent to the configured AI provider and require review."
            />
            <Form
                v-bind="store.form(currentTeamSlug)"
                class="grid gap-4"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="summary-type">Summary type</Label>
                    <select
                        id="summary-type"
                        v-model="summaryType"
                        name="type"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option
                            v-if="canManageInvestors"
                            value="investor_progress"
                        >
                            Investor progress
                        </option>
                        <option v-if="canManageOperations" value="weekly">
                            Weekly summary
                        </option>
                        <option
                            v-if="canManageOperations"
                            value="risk_decision"
                        >
                            Risks and decisions
                        </option>
                        <option
                            v-if="canManageOperations"
                            value="intake_cleanup"
                        >
                            Intake cleanup
                        </option>
                    </select>
                    <InputError :message="errors.type" />
                    <template v-if="summaryType === 'investor_progress'">
                        <Label for="summary-agreement"
                            >Investor agreement</Label
                        >
                        <select
                            id="summary-agreement"
                            name="agreement_id"
                            class="h-9 rounded-md border bg-background px-3 text-sm"
                            required
                        >
                            <option value="">Select an agreement</option>
                            <option
                                v-for="agreement in agreements"
                                :key="agreement.id"
                                :value="agreement.id"
                            >
                                {{ agreement.label }}
                            </option>
                        </select>
                        <InputError :message="errors.agreement_id" />
                    </template>
                    <template v-else>
                        <Label for="summary-farm">Farm</Label>
                        <select
                            id="summary-farm"
                            v-model="selectedFarm"
                            name="farm_id"
                            class="h-9 rounded-md border bg-background px-3 text-sm"
                            required
                        >
                            <option value="">Select a farm</option>
                            <option
                                v-for="farm in farms"
                                :key="farm.id"
                                :value="farm.id"
                            >
                                {{ farm.label }}
                            </option>
                        </select>
                        <InputError :message="errors.farm_id" />
                        <Label for="summary-cycle"
                            >Production cycle (optional)</Label
                        >
                        <select
                            id="summary-cycle"
                            v-model="selectedCycle"
                            name="production_cycle_id"
                            class="h-9 rounded-md border bg-background px-3 text-sm"
                        >
                            <option value="">All cycles</option>
                            <option
                                v-for="cycle in selectedFarmDetails?.cycles ??
                                []"
                                :key="cycle.id"
                                :value="cycle.id"
                            >
                                {{ cycle.label }}
                            </option>
                        </select>
                        <InputError :message="errors.production_cycle_id" />
                    </template>
                </div>
                <div v-if="summaryType === 'intake_cleanup'" class="grid gap-2">
                    <Label for="summary-intake">Pending intake</Label>
                    <select
                        id="summary-intake"
                        name="whatsapp_intake_id"
                        required
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">Select a pending intake</option>
                        <option
                            v-for="intake in availableIntakes"
                            :key="intake.id"
                            :value="intake.id"
                        >
                            Intake #{{ intake.id
                            }}{{ intake.date ? ` — ${intake.date}` : '' }}
                        </option>
                    </select>
                    <InputError :message="errors.whatsapp_intake_id" />
                    <Label for="summary-excerpt">Sanitized excerpt</Label>
                    <p class="text-sm text-muted-foreground">
                        This excerpt is sent to the configured AI provider.
                        Remove names, bank details and other sensitive
                        information before submitting.
                    </p>
                    <textarea
                        id="summary-excerpt"
                        name="input_text"
                        required
                        maxlength="5000"
                        class="min-h-32 rounded-md border bg-background px-3 py-2 text-sm"
                    ></textarea>
                    <InputError :message="errors.input_text" />
                </div>
                <div class="flex items-end">
                    <Button
                        type="submit"
                        :disabled="
                            processing ||
                            (summaryType === 'investor_progress'
                                ? agreements.length === 0
                                : farms.length === 0)
                        "
                    >
                        Generate draft
                    </Button>
                </div>
            </Form>
            <p
                v-if="
                    summaryType === 'investor_progress' &&
                    agreements.length === 0
                "
                class="text-sm text-muted-foreground"
            >
                No eligible agreements are available.
            </p>
        </section>

        <section class="space-y-4">
            <div class="flex items-center gap-2">
                <FileText class="size-4" />
                <Heading
                    variant="small"
                    title="Summary drafts"
                    description="Review generated text before approving it for internal use."
                />
            </div>
            <div v-if="summaries.length" class="space-y-4">
                <article
                    v-for="summary in summaries"
                    :key="summary.id"
                    class="space-y-4 rounded-lg border p-4 text-sm"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-3"
                    >
                        <div>
                            <h3 class="font-medium">
                                {{ typeLabels[summary.type] ?? summary.type }} ·
                                {{ summary.agreement_label }}
                            </h3>
                            <p class="text-muted-foreground">
                                {{ summary.created_at }}
                            </p>
                        </div>
                        <Badge variant="secondary" class="capitalize">{{
                            summary.status
                        }}</Badge>
                    </div>
                    <p
                        v-if="summary.status === 'queued'"
                        class="text-muted-foreground"
                    >
                        Generation is queued.
                    </p>
                    <p
                        v-else-if="summary.status === 'failed'"
                        class="text-muted-foreground"
                    >
                        Generation failed. Create a new draft to try again.
                    </p>
                    <template v-else>
                        <div v-if="summary.generated_text" class="space-y-2">
                            <h4 class="font-medium">Generated draft</h4>
                            <SummaryText :html="summary.generated_html ?? ''" />
                        </div>
                        <div
                            v-if="
                                summary.final_text &&
                                (summary.status === 'approved' ||
                                    summary.status === 'published')
                            "
                            class="space-y-2"
                        >
                            <h4 class="font-medium">Reviewed text</h4>
                            <SummaryText :html="summary.final_html ?? ''" />
                        </div>
                        <details
                            v-if="
                                summary.type === 'investor_progress' &&
                                summary.source_snapshot?.length
                            "
                            class="space-y-2"
                        >
                            <summary class="cursor-pointer font-medium">
                                Source activities used
                            </summary>
                            <ul class="mt-2 space-y-2">
                                <li
                                    v-for="source in summary.source_snapshot"
                                    :key="source.id"
                                >
                                    {{ source.date }} · {{ source.type }} ·
                                    {{ source.status }} — {{ source.summary }}
                                </li>
                            </ul>
                        </details>
                        <Form
                            v-if="
                                canManage &&
                                summary.type === 'investor_progress' &&
                                summary.status === 'approved'
                            "
                            v-bind="
                                publish.form({
                                    current_team: currentTeamSlug,
                                    ai_summary: summary.id,
                                })
                            "
                            v-slot="{ errors, processing }"
                        >
                            <Button type="submit" :disabled="processing"
                                >Publish to investor</Button
                            >
                            <InputError :message="errors.publication" />
                        </Form>
                        <p
                            v-if="
                                summary.status === 'published' &&
                                !summary.publication_current
                            "
                            class="text-sm text-destructive"
                        >
                            Hidden from the investor because the agreement or
                            source activities changed. Withdraw and generate a
                            new draft.
                        </p>
                        <Form
                            v-if="canManage && summary.status === 'published'"
                            v-bind="
                                withdraw.form({
                                    current_team: currentTeamSlug,
                                    ai_summary: summary.id,
                                })
                            "
                            v-slot="{ errors, processing }"
                        >
                            <Button
                                type="submit"
                                variant="outline"
                                :disabled="processing"
                                >Withdraw from investor</Button
                            >
                            <InputError :message="errors.publication" />
                        </Form>
                        <Form
                            v-if="
                                canManage &&
                                currentTeamSlug &&
                                summary.status === 'draft'
                            "
                            v-bind="
                                review.form({
                                    current_team: currentTeamSlug,
                                    ai_summary: summary.id,
                                })
                            "
                            class="grid gap-3"
                            v-slot="{ errors, processing }"
                        >
                            <Label :for="`summary-final-${summary.id}`"
                                >Reviewed text</Label
                            >
                            <textarea
                                :id="`summary-final-${summary.id}`"
                                name="final_text"
                                v-model="reviewTexts[summary.id]"
                                class="min-h-32 rounded-md border bg-background px-3 py-2 text-sm"
                            ></textarea>
                            <InputError :message="errors.final_text" />
                            <InputError :message="errors.action" />
                            <div class="flex flex-wrap gap-2">
                                <Button
                                    type="submit"
                                    name="action"
                                    value="approve"
                                    :disabled="processing"
                                    >Approve</Button
                                >
                                <Button
                                    type="submit"
                                    name="action"
                                    value="reject"
                                    variant="outline"
                                    :disabled="processing"
                                    >Reject</Button
                                >
                            </div>
                        </Form>
                    </template>
                </article>
            </div>
            <p v-else class="text-sm text-muted-foreground">
                No summary drafts yet.
            </p>
        </section>
    </div>
</template>
