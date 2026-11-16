<script setup lang="ts">
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InvestorUpdateEvidence from '@/components/InvestorUpdateEvidence.vue';
import SummaryText from '@/components/SummaryText.vue';
import { Label } from '@/components/ui/label';
import { formatInvestorDate } from '@/lib/investor-portal';
import type {
    InvestorActivityEvidence,
    InvestorPortalAgreement,
} from '@/types';

const props = defineProps<{ agreement: InvestorPortalAgreement }>();

type UpdateKind = 'summary' | 'activity' | 'task' | 'plan';
type Update = {
    id: string;
    kind: UpdateKind;
    title: string;
    date: string | null;
    detail?: string | null;
    html?: string;
    evidence?: InvestorActivityEvidence[];
};

const labels: Record<UpdateKind, string> = {
    summary: 'Reviewed summary',
    activity: 'Field activity',
    task: 'Task',
    plan: 'Plan change',
};
const filter = ref<'all' | UpdateKind>('all');

const updates = computed(() => {
    const { aiSummaries, activities, tasks, planChanges } = props.agreement;
    const items: Update[] = [
        ...aiSummaries.map((summary): Update => ({
            id: `summary-${summary.id}`,
            kind: 'summary',
            title: 'Reviewed progress summary',
            date: summary.publishedAt,
            html: summary.html,
        })),
        ...activities.map((activity): Update => ({
            id: `activity-${activity.id}`,
            kind: 'activity',
            title: activity.activityType,
            date: activity.activityDate,
            detail: activity.summary,
            evidence: activity.evidence,
        })),
        ...tasks.map((task): Update => ({
            id: `task-${task.id}`,
            kind: 'task',
            title: task.title,
            date: task.dueOn,
            detail: task.status.replaceAll('_', ' '),
        })),
        ...planChanges.map((change): Update => ({
            id: `plan-${change.id}`,
            kind: 'plan',
            title: change.changeType.replaceAll('_', ' '),
            date: change.createdAt,
            detail: change.summary ?? change.impact,
        })),
    ];

    return items.sort((first, second) =>
        (second.date ?? '').localeCompare(first.date ?? ''),
    );
});

const visibleUpdates = computed(() =>
    filter.value === 'all'
        ? updates.value
        : updates.value.filter((update) => update.kind === filter.value),
);
</script>

<template>
    <section class="space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <Heading
                variant="small"
                title="Farm updates"
                description="Field activity, tasks and progress shared with you."
            />
            <div v-if="updates.length" class="flex items-center gap-2">
                <Label :for="`update-filter-${agreement.id}`">Show</Label>
                <select
                    :id="`update-filter-${agreement.id}`"
                    v-model="filter"
                    class="h-9 rounded-md border bg-background px-2 text-sm"
                >
                    <option value="all">All updates</option>
                    <option
                        v-for="(label, kind) in labels"
                        :key="kind"
                        :value="kind"
                    >
                        {{ label }}
                    </option>
                </select>
            </div>
        </div>
        <ol
            v-if="visibleUpdates.length"
            class="ms-2 space-y-6 border-s border-border ps-5 sm:ps-7"
        >
            <li
                v-for="update in visibleUpdates"
                :key="update.id"
                class="relative min-w-0"
            >
                <span
                    aria-hidden="true"
                    class="absolute start-[-1.55rem] top-1.5 size-2.5 rounded-full bg-[#34775c] ring-4 ring-background sm:start-[-2.05rem] dark:bg-emerald-400"
                />
                <div
                    class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-muted-foreground"
                >
                    <span>{{ labels[update.kind] }}</span>
                    <span v-if="update.date">
                        <span v-if="update.kind === 'task'">Due </span
                        ><time :datetime="update.date">{{
                            formatInvestorDate(update.date)
                        }}</time>
                    </span>
                </div>
                <h3 class="mt-1 font-semibold break-words">
                    {{ update.title }}
                </h3>
                <SummaryText
                    v-if="update.html"
                    class="mt-2 text-sm"
                    :html="update.html"
                />
                <p
                    v-else-if="update.detail"
                    class="mt-1 text-sm break-words whitespace-pre-wrap text-muted-foreground"
                    :class="{ capitalize: update.kind === 'task' }"
                >
                    {{ update.detail }}
                </p>
                <InvestorUpdateEvidence
                    v-if="update.evidence?.length"
                    :evidence="update.evidence"
                />
            </li>
        </ol>
        <p
            v-else
            role="status"
            class="rounded-lg border border-dashed p-5 text-sm text-muted-foreground"
        >
            {{
                updates.length
                    ? 'No updates match this filter.'
                    : 'No farm updates have been shared for this agreement yet.'
            }}
        </p>
    </section>
</template>
