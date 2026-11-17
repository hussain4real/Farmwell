<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { formatCurrency } from '@/lib/currency';
import { formatInvestorDate } from '@/lib/investor-portal';
import type { InvestorFundingGroup } from '@/types';

defineProps<{ groups: InvestorFundingGroup[] }>();
</script>

<template>
    <section class="space-y-5">
        <Heading variant="small" title="Funding timeline" />
        <p v-if="!groups.length" class="text-sm text-muted-foreground">
            No funding milestones have been shared for this agreement yet.
        </p>
        <div
            v-for="group in groups"
            :key="group.cycleId ?? 'unassigned'"
            class="space-y-3"
        >
            <div>
                <h3 class="text-base font-semibold">
                    {{ group.cycleName ?? 'No season or batch assigned' }}
                </h3>
                <p
                    v-if="group.season && group.season !== group.cycleName"
                    class="text-sm text-muted-foreground"
                >
                    {{ group.season }}
                </p>
            </div>
            <ul class="space-y-3" role="list">
                <li
                    v-for="milestone in group.milestones"
                    :key="milestone.id"
                    class="min-w-0 space-y-4 rounded-xl border bg-card p-4 sm:p-5"
                >
                    <header>
                        <h4 class="font-medium wrap-break-word">
                            {{ milestone.name }}
                        </h4>
                        <p
                            v-if="
                                milestone.milestone &&
                                milestone.milestone !== milestone.name
                            "
                            class="text-sm wrap-break-word text-muted-foreground"
                        >
                            {{ milestone.milestone }}
                        </p>
                        <p
                            v-if="milestone.expectedOn"
                            class="mt-1 text-sm text-muted-foreground"
                        >
                            Expected
                            <time :datetime="milestone.expectedOn">{{
                                formatInvestorDate(milestone.expectedOn)
                            }}</time>
                        </p>
                    </header>
                    <ol
                        v-if="milestone.events.length"
                        role="list"
                        class="space-y-4 border-s ps-4 text-sm"
                    >
                        <li
                            v-for="event in milestone.events"
                            :key="event.id"
                            class="relative min-w-0"
                        >
                            <span
                                aria-hidden="true"
                                class="absolute start-[-1.25rem] top-1.5 size-2 rounded-full bg-primary"
                            ></span>
                            <div
                                class="flex flex-wrap items-baseline gap-x-3 gap-y-1"
                            >
                                <p class="font-medium">{{ event.label }}</p>
                                <time
                                    v-if="event.date"
                                    :datetime="event.date"
                                    class="text-muted-foreground"
                                    >{{ formatInvestorDate(event.date) }}</time
                                >
                                <span
                                    v-if="event.amount !== null"
                                    class="font-medium tabular-nums"
                                    >{{
                                        formatCurrency(
                                            event.amount,
                                            event.currency,
                                        )
                                    }}</span
                                >
                            </div>
                            <p
                                v-if="event.detail"
                                class="mt-1 wrap-break-word text-muted-foreground"
                            >
                                {{ event.detail }}
                            </p>
                            <ul
                                v-if="event.files.length"
                                role="list"
                                class="mt-1 flex flex-wrap gap-x-3"
                            >
                                <li
                                    v-for="file in event.files"
                                    :key="file.id"
                                    class="min-w-0"
                                >
                                    <a
                                        :href="file.downloadUrl"
                                        class="inline-flex min-h-10 items-center wrap-anywhere underline underline-offset-4"
                                    >
                                        {{ file.fileName }}
                                    </a>
                                </li>
                            </ul>
                        </li>
                    </ol>
                    <p v-else class="text-sm text-muted-foreground">
                        No funding events recorded yet.
                    </p>
                </li>
            </ul>
        </div>
    </section>
</template>
