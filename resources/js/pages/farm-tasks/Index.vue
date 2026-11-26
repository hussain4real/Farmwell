<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { ListTodo, Plus } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import EmptyState from '@/components/farm/EmptyState.vue';
import PageHero from '@/components/farm/PageHero.vue';
import SlideOverForm from '@/components/farm/SlideOverForm.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { dashboard } from '@/routes';
import {
    index as farmTasksIndex,
    store as storeFarmTask,
} from '@/routes/farm-tasks';
import { update as updateFarmTaskStatus } from '@/routes/farm-tasks/status';
import type {
    Farm,
    FarmOperationOptions,
    FarmOperationPermissions,
    FarmTask,
    Team,
} from '@/types';

type Props = {
    permissions: FarmOperationPermissions;
    farms: Farm[];
    upcomingTasks: FarmTask[];
    options: Pick<FarmOperationOptions, 'taskStatuses'>;
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
                title: 'Task Calendar',
                href: props.currentTeam
                    ? farmTasksIndex(props.currentTeam.slug)
                    : '/',
            },
        ],
    }),
});

const currentTeam = computed(() => page.props.currentTeam as Team | null);
const currentTeamSlug = computed(() => currentTeam.value?.slug ?? '');
const drawerOpen = ref(false);
const farmFilter = ref('all');
const statusFilter = ref('all');
const taskFarmId = ref(props.farms[0]?.id ? String(props.farms[0].id) : '');
const taskUnitId = ref('');
const taskCycleId = ref('');
const taskStatus = ref('planned');
const formKey = ref(0);

const selectedTaskFarm = computed(() =>
    props.farms.find((farm) => String(farm.id) === taskFarmId.value),
);

const todayIso = new Date().toISOString().slice(0, 10);

function isTaskOverdue(task: FarmTask): boolean {
    return (
        task.status !== 'completed' &&
        task.status !== 'cancelled' &&
        task.dueOn < todayIso
    );
}

const filteredTasks = computed(() =>
    props.upcomingTasks.filter((t) => {
        const matchesFarm =
            farmFilter.value === 'all' || String(t.farmId) === farmFilter.value;
        const matchesStatus =
            statusFilter.value === 'all' ||
            (statusFilter.value === 'overdue'
                ? isTaskOverdue(t)
                : t.status === statusFilter.value);

        return matchesFarm && matchesStatus;
    }),
);

const openTasksCount = computed(
    () =>
        props.upcomingTasks.filter(
            (t) => t.status !== 'completed' && t.status !== 'cancelled',
        ).length,
);

const overdueTasksCount = computed(
    () => props.upcomingTasks.filter(isTaskOverdue).length,
);

const delayedTasksCount = computed(
    () => props.upcomingTasks.filter((t) => t.status === 'delayed').length,
);

const bumpFormKey = () => {
    formKey.value++;
    drawerOpen.value = false;
};

watch(
    () => props.farms.map((farm) => farm.id),
    () => {
        const ids = props.farms.map((farm) => String(farm.id));

        if (!ids.includes(taskFarmId.value)) {
            taskFarmId.value = ids[0] ?? '';
            taskUnitId.value = '';
            taskCycleId.value = '';
        }
    },
    { immediate: true },
);

watch(taskFarmId, () => {
    taskUnitId.value = '';
    taskCycleId.value = '';
});
</script>

<template>
    <div class="flex flex-1 flex-col gap-6 p-4 md:p-6">
        <Head title="Task Calendar" />

        <PageHero
            eyebrow="Work & Reminders"
            title="Task calendar"
            description="Schedule farm tasks, capture delay reasons, and keep poultry, crop & fish work on track."
        >
            <template #actions>
                <Button
                    v-if="
                        permissions.canManageFieldWork &&
                        currentTeamSlug &&
                        farms.length > 0
                    "
                    type="button"
                    class="bg-white text-[#1b4d3e] hover:bg-emerald-50"
                    @click="drawerOpen = true"
                >
                    <Plus class="mr-1.5 size-4" />
                    Schedule task
                </Button>
            </template>
            <template #metrics>
                <div class="px-4 py-3 sm:px-6">
                    <p class="text-xs text-emerald-100/75">Open tasks</p>
                    <p class="mt-0.5 text-lg font-semibold tabular-nums">
                        {{ openTasksCount }}
                    </p>
                </div>
                <div class="border-s border-white/15 px-4 py-3 sm:px-6">
                    <p class="text-xs text-emerald-100/75">Overdue</p>
                    <p class="mt-0.5 text-lg font-semibold tabular-nums">
                        {{ overdueTasksCount }}
                    </p>
                </div>
                <div
                    class="col-span-2 border-t border-white/15 px-4 py-3 sm:col-span-2 sm:border-s sm:border-t-0 sm:px-6"
                >
                    <p class="text-xs text-emerald-100/75">Delayed</p>
                    <p class="mt-0.5 text-lg font-semibold tabular-nums">
                        {{ delayedTasksCount }}
                    </p>
                </div>
            </template>
        </PageHero>

        <div class="flex flex-wrap items-center gap-2">
            <select
                v-model="farmFilter"
                aria-label="Filter tasks by farm"
                class="h-9 rounded-md border bg-card px-3 text-sm"
            >
                <option value="all">All farms</option>
                <option
                    v-for="farm in farms"
                    :key="farm.id"
                    :value="String(farm.id)"
                >
                    {{ farm.name }}
                </option>
            </select>
            <select
                v-model="statusFilter"
                aria-label="Filter tasks by status"
                class="h-9 rounded-md border bg-card px-3 text-sm"
            >
                <option value="all">All statuses</option>
                <option value="overdue">Overdue</option>
                <option
                    v-for="option in options.taskStatuses"
                    :key="option.value"
                    :value="option.value"
                >
                    {{ option.label }}
                </option>
            </select>
        </div>

        <section v-if="filteredTasks.length > 0" class="grid gap-3">
            <article
                v-for="task in filteredTasks"
                :key="task.id"
                class="space-y-3 rounded-2xl border bg-card p-4 text-sm shadow-xs"
                :class="
                    isTaskOverdue(task)
                        ? 'border-amber-300/70 dark:border-amber-800/70'
                        : ''
                "
            >
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="font-semibold">{{ task.title }}</p>
                        <p class="text-xs text-muted-foreground">
                            {{ task.farmName }} · due {{ task.dueOn }}
                            <span v-if="task.productionUnitName">
                                · {{ task.productionUnitName }}
                            </span>
                        </p>
                    </div>
                    <div class="flex flex-wrap items-center gap-1.5">
                        <Badge v-if="isTaskOverdue(task)" variant="destructive">
                            Overdue
                        </Badge>
                        <Badge variant="secondary">{{
                            task.statusLabel
                        }}</Badge>
                    </div>
                </div>
                <p v-if="task.description">{{ task.description }}</p>
                <p
                    v-if="task.statusReason"
                    class="text-xs text-muted-foreground"
                >
                    Reason: {{ task.statusReason }}
                </p>

                <Form
                    v-if="permissions.canManageFieldWork && currentTeamSlug"
                    v-bind="
                        updateFarmTaskStatus.form([currentTeamSlug, task.id])
                    "
                    class="grid gap-2 md:grid-cols-[1fr_1fr_auto]"
                    v-slot="{ errors, processing }"
                >
                    <select
                        name="status"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                        required
                    >
                        <option
                            v-for="option in options.taskStatuses"
                            :key="option.value"
                            :value="option.value"
                            :selected="option.value === task.status"
                        >
                            {{ option.label }}
                        </option>
                    </select>
                    <Input
                        name="status_reason"
                        :value="task.statusReason ?? ''"
                        placeholder="Reason when delayed"
                    />
                    <Button type="submit" :disabled="processing">Update</Button>
                    <InputError :message="errors.status" />
                    <InputError :message="errors.status_reason" />
                </Form>
            </article>
        </section>

        <EmptyState
            v-else
            title="No open tasks"
            description="Schedule vaccinations, weeding, pond sampling, or maintenance reminders."
            :icon="ListTodo"
        />

        <SlideOverForm
            :open="drawerOpen"
            title="Schedule task"
            description="Assign work to a farm, unit, or cycle"
            @update:open="drawerOpen = $event"
        >
            <Form
                v-if="farms.length > 0 && currentTeamSlug"
                :key="formKey"
                v-bind="storeFarmTask.form(currentTeamSlug)"
                class="grid gap-4"
                v-slot="{ errors, processing }"
                @success="bumpFormKey"
            >
                <div class="grid gap-1.5">
                    <Label for="task-farm">Farm</Label>
                    <select
                        id="task-farm"
                        v-model="taskFarmId"
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
                    <Label for="task-title">Title</Label>
                    <Input id="task-title" name="title" required />
                    <InputError :message="errors.title" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="task-due">Due date</Label>
                    <Input id="task-due" name="due_on" type="date" required />
                    <InputError :message="errors.due_on" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="task-type">Activity type</Label>
                    <Input id="task-type" name="activity_type" />
                </div>
                <div class="grid gap-1.5">
                    <Label for="task-unit">Production unit</Label>
                    <select
                        id="task-unit"
                        v-model="taskUnitId"
                        name="production_unit_id"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">None</option>
                        <option
                            v-for="unit in selectedTaskFarm?.productionUnits ??
                            []"
                            :key="unit.id"
                            :value="String(unit.id)"
                        >
                            {{ unit.name }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="task-cycle">Production cycle</Label>
                    <select
                        id="task-cycle"
                        v-model="taskCycleId"
                        name="production_cycle_id"
                        class="h-9 rounded-md border bg-background px-3 text-sm"
                    >
                        <option value="">None</option>
                        <option
                            v-for="cycle in selectedTaskFarm?.productionCycles ??
                            []"
                            :key="cycle.id"
                            :value="String(cycle.id)"
                        >
                            {{ cycle.name }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="task-status">Status</Label>
                    <Select v-model="taskStatus" name="status">
                        <SelectTrigger id="task-status" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="option in options.taskStatuses"
                                :key="option.value"
                                :value="option.value"
                            >
                                {{ option.label }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>
                <div class="grid gap-1.5">
                    <Label for="task-reason">Status reason</Label>
                    <Input id="task-reason" name="status_reason" />
                </div>
                <Button type="submit" :disabled="processing"
                    >Schedule task</Button
                >
            </Form>
        </SlideOverForm>
    </div>
</template>
