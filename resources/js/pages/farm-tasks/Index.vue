<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3';
import { ListTodo } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import Heading from '@/components/Heading.vue';
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
const taskFarmId = ref(props.farms[0]?.id ? String(props.farms[0].id) : '');
const taskUnitId = ref('');
const taskCycleId = ref('');
const taskStatus = ref('planned');
const formKey = ref(0);

const selectedTaskFarm = computed(() =>
    props.farms.find((farm) => String(farm.id) === taskFarmId.value),
);

const bumpFormKey = () => {
    formKey.value++;
};

const syncFarmSelections = () => {
    const farmIds = props.farms.map((farm) => String(farm.id));
    const fallbackFarmId = farmIds[0] ?? '';

    if (!farmIds.includes(taskFarmId.value)) {
        taskFarmId.value = fallbackFarmId;
        taskUnitId.value = '';
        taskCycleId.value = '';
    }
};

watch(() => props.farms.map((farm) => farm.id), syncFarmSelections, {
    immediate: true,
});
watch(taskFarmId, () => {
    taskUnitId.value = '';
    taskCycleId.value = '';
});
</script>

<template>
    <Head title="Task Calendar" />

    <div class="flex flex-1 flex-col gap-8 p-4 md:p-6">
        <Heading
            title="Task calendar"
            description="Open tasks, delayed-task reasons, and completion flow"
        />

        <section
            v-if="permissions.canManageFarmOperations && currentTeamSlug"
            class="space-y-4 rounded-lg border p-4"
        >
            <div class="flex items-center gap-2">
                <ListTodo class="size-4" />
                <Heading
                    variant="small"
                    title="Schedule task"
                    description="Calendar task with delayed-task reason"
                />
            </div>

            <Form
                v-if="farms.length > 0"
                :key="formKey"
                v-bind="storeFarmTask.form(currentTeamSlug)"
                class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
                v-slot="{ errors, processing }"
                @success="bumpFormKey"
            >
                <div class="grid gap-2">
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
                    <InputError :message="errors.farm_id" />
                </div>
                <div class="grid gap-2">
                    <Label for="task-title">Title</Label>
                    <Input id="task-title" name="title" required />
                    <InputError :message="errors.title" />
                </div>
                <div class="grid gap-2">
                    <Label for="task-due">Due date</Label>
                    <Input id="task-due" name="due_on" type="date" required />
                    <InputError :message="errors.due_on" />
                </div>
                <div class="grid gap-2">
                    <Label for="task-type">Activity type</Label>
                    <Input id="task-type" name="activity_type" />
                    <InputError :message="errors.activity_type" />
                </div>
                <div class="grid gap-2">
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
                    <InputError :message="errors.production_unit_id" />
                </div>
                <div class="grid gap-2">
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
                    <InputError :message="errors.production_cycle_id" />
                </div>
                <div class="grid gap-2">
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
                    <InputError :message="errors.status" />
                </div>
                <div class="grid gap-2">
                    <Label for="task-reason">Status reason</Label>
                    <Input id="task-reason" name="status_reason" />
                    <InputError :message="errors.status_reason" />
                </div>
                <div class="md:col-span-2 xl:col-span-3">
                    <Button type="submit" :disabled="processing">
                        Schedule task
                    </Button>
                </div>
            </Form>

            <p v-else class="text-sm text-muted-foreground">
                No farm records yet.
            </p>
        </section>

        <section class="space-y-4">
            <div class="flex items-center gap-2">
                <ListTodo class="size-4" />
                <Heading
                    variant="small"
                    title="Open tasks"
                    description="Current work with status updates and delay reasons"
                />
            </div>

            <div v-if="upcomingTasks.length > 0" class="grid gap-3">
                <article
                    v-for="task in upcomingTasks"
                    :key="task.id"
                    class="space-y-3 rounded-lg border p-4 text-sm"
                >
                    <div
                        class="flex flex-wrap items-start justify-between gap-2"
                    >
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
                    <p v-if="task.description">{{ task.description }}</p>
                    <p
                        v-if="task.productionUnitName"
                        class="text-muted-foreground"
                    >
                        Unit: {{ task.productionUnitName }}
                    </p>
                    <p v-if="task.statusReason" class="text-muted-foreground">
                        Reason: {{ task.statusReason }}
                    </p>

                    <Form
                        v-if="
                            permissions.canManageFarmOperations &&
                            currentTeamSlug
                        "
                        v-bind="
                            updateFarmTaskStatus.form([
                                currentTeamSlug,
                                task.id,
                            ])
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
                        <Button type="submit" :disabled="processing">
                            Update
                        </Button>
                        <InputError :message="errors.status" />
                        <InputError :message="errors.status_reason" />
                    </Form>
                </article>
            </div>
            <p v-else class="text-sm text-muted-foreground">
                No open tasks yet.
            </p>
        </section>
    </div>
</template>
