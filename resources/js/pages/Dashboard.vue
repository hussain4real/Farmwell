<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import {
    CalendarDays,
    ClipboardList,
    ListTodo,
    MessageSquare,
    Sprout,
    Tractor,
} from 'lucide-vue-next';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Badge } from '@/components/ui/badge';
import { dashboard } from '@/routes';
import { index as farmTasksIndex } from '@/routes/farm-tasks';
import { index as farmsIndex } from '@/routes/farms';
import { index as fieldDiaryIndex } from '@/routes/field-diary';
import { index as whatsappIntakesIndex } from '@/routes/whatsapp-intakes';
import type {
    FarmActivity,
    FarmOperationPermissions,
    FarmStats,
    FarmTask,
    ProductionPlanChange,
    Team,
    WhatsappIntake,
} from '@/types';

type Props = {
    permissions: FarmOperationPermissions;
    stats: FarmStats;
    latestActivities: FarmActivity[];
    upcomingTasks: FarmTask[];
    pendingIntakes: WhatsappIntake[];
    latestPlanChanges: ProductionPlanChange[];
};

defineProps<Props>();
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
        ],
    }),
});

const currentTeam = computed(() => page.props.currentTeam as Team | null);
const currentTeamSlug = computed(() => currentTeam.value?.slug ?? '');

const workspaceLinks = computed(() => {
    if (!currentTeamSlug.value) {
        return [];
    }

    return [
        {
            title: 'Farm operations',
            href: farmsIndex(currentTeamSlug.value).url,
            description: 'Farms, units, cycles, commodities, and plan changes',
            icon: Tractor,
        },
        {
            title: 'Field diary',
            href: fieldDiaryIndex(currentTeamSlug.value).url,
            description: 'Activities, evidence, costs, and next actions',
            icon: ClipboardList,
        },
        {
            title: 'Task calendar',
            href: farmTasksIndex(currentTeamSlug.value).url,
            description: 'Open tasks, delay reasons, and completion flow',
            icon: ListTodo,
        },
        {
            title: 'WhatsApp intake',
            href: whatsappIntakesIndex(currentTeamSlug.value).url,
            description: 'Pending review, conversion, and rejection',
            icon: MessageSquare,
        },
    ];
});
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex flex-1 flex-col gap-8 p-4 md:p-6">
        <Heading
            title="Dashboard"
            description="High-level farm, activity, task, intake, and plan-change summary"
        />

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-lg border p-4">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm text-muted-foreground">Farms</span>
                    <Tractor class="size-4 text-muted-foreground" />
                </div>
                <p class="mt-3 text-2xl font-semibold">{{ stats.farms }}</p>
            </div>
            <div class="rounded-lg border p-4">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm text-muted-foreground">Cycles</span>
                    <CalendarDays class="size-4 text-muted-foreground" />
                </div>
                <p class="mt-3 text-2xl font-semibold">
                    {{ stats.productionCycles }}
                </p>
            </div>
            <div class="rounded-lg border p-4">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm text-muted-foreground">
                        Activities
                    </span>
                    <ClipboardList class="size-4 text-muted-foreground" />
                </div>
                <p class="mt-3 text-2xl font-semibold">
                    {{ stats.activities }}
                </p>
            </div>
            <div class="rounded-lg border p-4">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-sm text-muted-foreground">
                        Open tasks
                    </span>
                    <ListTodo class="size-4 text-muted-foreground" />
                </div>
                <p class="mt-3 text-2xl font-semibold">
                    {{ stats.openTasks }}
                </p>
            </div>
        </div>

        <section class="grid gap-4 xl:grid-cols-4">
            <Link
                v-for="workspace in workspaceLinks"
                :key="workspace.title"
                :href="workspace.href"
                class="rounded-lg border p-4 transition-colors hover:bg-accent"
            >
                <div class="flex items-start gap-3">
                    <component
                        :is="workspace.icon"
                        class="mt-1 size-4 text-muted-foreground"
                    />
                    <div>
                        <h3 class="font-medium">{{ workspace.title }}</h3>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ workspace.description }}
                        </p>
                    </div>
                </div>
            </Link>
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            <div class="space-y-4 rounded-lg border p-4">
                <div class="flex items-center gap-2">
                    <ClipboardList class="size-4" />
                    <Heading
                        variant="small"
                        title="Recent activities"
                        description="Latest diary entries and evidence"
                    />
                </div>

                <div v-if="latestActivities.length > 0" class="space-y-3">
                    <article
                        v-for="activity in latestActivities"
                        :key="activity.id"
                        class="space-y-2 rounded-lg border p-3 text-sm"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-medium">
                                    {{ activity.farmName }} /
                                    {{ activity.activityType }}
                                </p>
                                <p class="text-muted-foreground">
                                    {{ activity.activityDate }}
                                </p>
                            </div>
                            <Badge variant="secondary">
                                {{ activity.statusLabel }}
                            </Badge>
                        </div>
                        <p>{{ activity.description }}</p>
                    </article>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    No activity entries yet.
                </p>
            </div>

            <div class="space-y-4 rounded-lg border p-4">
                <div class="flex items-center gap-2">
                    <ListTodo class="size-4" />
                    <Heading
                        variant="small"
                        title="Upcoming tasks"
                        description="Open work that still needs attention"
                    />
                </div>

                <div v-if="upcomingTasks.length > 0" class="space-y-3">
                    <article
                        v-for="task in upcomingTasks"
                        :key="task.id"
                        class="space-y-2 rounded-lg border p-3 text-sm"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-medium">{{ task.title }}</p>
                                <p class="text-muted-foreground">
                                    {{ task.farmName }} / due {{ task.dueOn }}
                                </p>
                            </div>
                            <Badge variant="secondary">
                                {{ task.statusLabel }}
                            </Badge>
                        </div>
                        <p v-if="task.statusReason">
                            Reason: {{ task.statusReason }}
                        </p>
                    </article>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    No open tasks yet.
                </p>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            <div class="space-y-4 rounded-lg border p-4">
                <div class="flex items-center gap-2">
                    <MessageSquare class="size-4" />
                    <Heading
                        variant="small"
                        title="Pending WhatsApp intake"
                        description="Updates waiting for review"
                    />
                </div>

                <div v-if="pendingIntakes.length > 0" class="space-y-3">
                    <article
                        v-for="intake in pendingIntakes"
                        :key="intake.id"
                        class="rounded-lg border p-3 text-sm"
                    >
                        <p class="font-medium">
                            {{ intake.sourceSender ?? 'WhatsApp update' }}
                        </p>
                        <p class="mt-1 text-muted-foreground">
                            {{ intake.farmName ?? 'No farm selected' }}
                        </p>
                        <p class="mt-2">{{ intake.sourceMessage }}</p>
                    </article>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    No pending WhatsApp intake.
                </p>
            </div>

            <div class="space-y-4 rounded-lg border p-4">
                <div class="flex items-center gap-2">
                    <Sprout class="size-4" />
                    <Heading
                        variant="small"
                        title="Latest plan changes"
                        description="Recent reasons and operating impacts"
                    />
                </div>

                <div v-if="latestPlanChanges.length > 0" class="space-y-3">
                    <article
                        v-for="change in latestPlanChanges"
                        :key="change.id"
                        class="rounded-lg border p-3 text-sm"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <p class="font-medium">
                                {{ change.farmName }} / {{ change.cycleName }}
                            </p>
                            <Badge variant="secondary">
                                {{ change.changeTypeLabel }}
                            </Badge>
                        </div>
                        <p class="mt-2 text-muted-foreground">
                            {{ change.reason }}
                        </p>
                        <p class="mt-1">{{ change.impact }}</p>
                    </article>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    No plan changes yet.
                </p>
            </div>
        </section>
    </div>
</template>
