<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Building2,
    Search,
    ShieldCheck,
    Tractor,
    Users,
} from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index } from '@/routes/platform';
import { store as enterWorkspace } from '@/routes/platform/access';
import {
    store as storeFarm,
    update as updateFarm,
} from '@/routes/platform/farms';
import { update as updateMembership } from '@/routes/platform/memberships';
import { update as updateUser } from '@/routes/platform/users';

type Workspace = { id: number; name: string; slug: string };
type Farm = {
    id: number;
    name: string;
    farm_type: string;
    location: string | null;
    contact_name: string | null;
    contact_phone: string | null;
    description: string | null;
    team: Workspace;
};
type User = {
    id: number;
    name: string;
    email: string | null;
    platform_role: string | null;
    teams: (Workspace & { pivot: { role: string } })[];
};
type Page<T> = {
    data: T[];
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};
const props = defineProps<{
    search: string;
    counts: { farms: number; users: number; teams: number };
    farms: Page<Farm>;
    users: Page<User>;
    workspaces: Workspace[];
    roles: { value: string; label: string }[];
    farmTypes: { value: string; label: string }[];
    auditEvents: {
        id: number;
        action: string;
        reason: string;
        created_at: string;
        actor: { name: string } | null;
        team: { name: string } | null;
    }[];
}>();
const accessWorkspace = ref<Workspace | null>(null);
const accessOpen = ref(false);
const accessForm = useForm({ team_id: 0, reason: '' });
function openWorkspace(workspace: Workspace) {
    accessWorkspace.value = workspace;
    accessForm.reset();
    accessForm.clearErrors();
    accessForm.team_id = workspace.id;
    accessOpen.value = true;
}
function enterWorkspaceRecords() {
    accessForm.post(enterWorkspace().url);
}
const tab = ref('farms');
const search = ref(props.search);
const farmOpen = ref(false);
const farmId = ref<number | null>(null);
const userOpen = ref(false);
const selectedUser = ref<User | null>(null);
const farmForm = useForm({
    team_id: '',
    name: '',
    farm_type: 'crop',
    location: '',
    contact_name: '',
    contact_phone: '',
    description: '',
    reason: '',
});
const userForm = useForm({ name: '', reason: '' });
const membershipForm = useForm({ team_id: '', role: 'operator', reason: '' });
const currentMembership = computed(() =>
    selectedUser.value?.teams.find(
        (team) => String(team.id) === membershipForm.team_id,
    ),
);
const isWorkspaceOwner = computed(
    () => currentMembership.value?.pivot.role === 'owner',
);
watch(
    () => membershipForm.team_id,
    () => {
        membershipForm.role = currentMembership.value?.pivot.role ?? 'operator';
    },
);

function editFarm(farm?: Farm) {
    farmForm.reset();
    farmForm.clearErrors();
    farmId.value = farm?.id ?? null;

    if (farm) {
        Object.assign(farmForm, {
            team_id: String(farm.team.id),
            name: farm.name,
            farm_type: farm.farm_type,
            location: farm.location ?? '',
            contact_name: farm.contact_name ?? '',
            contact_phone: farm.contact_phone ?? '',
            description: farm.description ?? '',
        });
    }

    farmOpen.value = true;
}
function saveFarm() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            farmOpen.value = false;
        },
    };

    if (farmId.value) {
        farmForm
            .transform((fields) =>
                Object.fromEntries(
                    Object.entries(fields).filter(([key]) => key !== 'team_id'),
                ),
            )
            .patch(updateFarm(farmId.value).url, options);
    } else {
        farmForm.transform((fields) => fields).post(storeFarm().url, options);
    }
}
function editUser(user: User) {
    selectedUser.value = user;
    userForm.reset();
    userForm.clearErrors();
    userForm.name = user.name;
    membershipForm.reset();
    membershipForm.clearErrors();
    userOpen.value = true;
}
function saveUser() {
    if (!selectedUser.value) {
        return;
    }

    userForm.patch(updateUser(selectedUser.value.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            userOpen.value = false;
        },
    });
}
function saveMembership() {
    if (!selectedUser.value) {
        return;
    }

    membershipForm.put(updateMembership(selectedUser.value.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            userOpen.value = false;
        },
    });
}
function filter() {
    router.get(
        index().url,
        { search: search.value },
        { preserveState: true, preserveScroll: true },
    );
}
</script>

<template>
    <div class="mx-auto w-full max-w-7xl space-y-6 p-4 md:p-8">
        <Head title="Platform administration" />
        <header
            class="rounded-2xl bg-primary p-6 text-primary-foreground md:p-8"
        >
            <div class="mb-3 flex items-center gap-2 text-sm opacity-80">
                <ShieldCheck class="size-4" /> Super Admin
            </div>
            <h1 class="text-2xl font-semibold tracking-tight md:text-3xl">
                Platform administration
            </h1>
            <p class="mt-2 max-w-2xl text-sm opacity-80">
                Manage farms and user access across Farmwell. Each change names
                its workspace and is recorded for review.
            </p>
        </header>
        <section class="grid grid-cols-3 gap-3" aria-label="Platform totals">
            <div
                v-for="item in [
                    { label: 'Farms', value: counts.farms, icon: Tractor },
                    { label: 'Users', value: counts.users, icon: Users },
                    {
                        label: 'Workspaces',
                        value: counts.teams,
                        icon: Building2,
                    },
                ]"
                :key="item.label"
                class="rounded-xl border bg-card p-4 md:p-5"
            >
                <component :is="item.icon" class="mb-3 size-5 text-primary" />
                <div class="text-2xl font-semibold">{{ item.value }}</div>
                <div class="text-sm text-muted-foreground">
                    {{ item.label }}
                </div>
            </div>
        </section>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex gap-2" aria-label="Administration views">
                <Button
                    v-for="item in ['farms', 'users', 'audit']"
                    :key="item"
                    :variant="tab === item ? 'default' : 'outline'"
                    :aria-pressed="tab === item"
                    @click="tab = item"
                    >{{
                        item === 'audit'
                            ? 'Audit trail'
                            : item === 'farms'
                              ? 'Farms'
                              : 'Users'
                    }}</Button
                >
            </div>
            <form
                v-if="tab !== 'audit'"
                class="flex w-full gap-2 sm:w-auto"
                @submit.prevent="filter"
            >
                <Label class="sr-only" for="platform-search"
                    >Search farms or users</Label
                ><Input
                    id="platform-search"
                    v-model="search"
                    placeholder="Search farms or users"
                    maxlength="100"
                /><Button variant="outline" aria-label="Search"
                    ><Search class="size-4"
                /></Button>
            </form>
        </div>
        <section v-if="tab === 'farms'" class="space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold">
                    All farms
                    <span class="text-sm font-normal text-muted-foreground"
                        >({{ farms.total }})</span
                    >
                </h2>
                <Button @click="editFarm()">Add farm</Button>
            </div>
            <p
                v-if="!farms.data.length"
                class="rounded-xl border border-dashed p-8 text-center text-muted-foreground"
            >
                No farms match your search.
            </p>
            <div class="grid gap-4 md:grid-cols-2">
                <article
                    v-for="farm in farms.data"
                    :key="farm.id"
                    class="flex flex-col gap-4 rounded-xl border bg-card p-5"
                >
                    <div>
                        <p
                            class="text-xs font-medium tracking-wide text-muted-foreground uppercase"
                        >
                            {{ farm.team.name }}
                        </p>
                        <h3 class="mt-1 text-lg font-semibold">
                            {{ farm.name }}
                        </h3>
                        <p class="mt-1 text-sm text-muted-foreground">
                            {{ farm.location || 'Location not recorded' }} ·
                            {{ farm.farm_type }}
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        class="self-start"
                        @click="editFarm(farm)"
                        >Edit farm details</Button
                    >
                    <Button
                        variant="outline"
                        class="self-start"
                        @click="openWorkspace(farm.team)"
                        >Open workspace records</Button
                    >
                </article>
            </div>
            <nav class="flex gap-3" aria-label="Farm pages">
                <Link
                    v-if="farms.prev_page_url"
                    :href="farms.prev_page_url"
                    preserve-state
                    class="text-sm underline"
                    >Previous farms</Link
                ><Link
                    v-if="farms.next_page_url"
                    :href="farms.next_page_url"
                    preserve-state
                    class="text-sm underline"
                    >Next farms</Link
                >
            </nav>
        </section>
        <section v-else-if="tab === 'users'" class="space-y-4">
            <h2 class="text-lg font-semibold">
                All users
                <span class="text-sm font-normal text-muted-foreground"
                    >({{ users.total }})</span
                >
            </h2>
            <p
                v-if="!users.data.length"
                class="rounded-xl border border-dashed p-8 text-center text-muted-foreground"
            >
                No users match your search.
            </p>
            <article
                v-for="user in users.data"
                :key="user.id"
                class="flex flex-col justify-between gap-4 rounded-xl border bg-card p-5 sm:flex-row sm:items-center"
            >
                <div class="min-w-0">
                    <h3 class="font-semibold">
                        {{ user.name }}
                        <span
                            v-if="user.platform_role === 'super_admin'"
                            class="ml-2 text-xs text-primary"
                            >Super Admin</span
                        >
                    </h3>
                    <p class="mt-1 text-sm break-words text-muted-foreground">
                        {{ user.email || 'Phone sign-in account' }}
                    </p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <span
                            v-for="team in user.teams"
                            :key="team.id"
                            class="rounded-md bg-muted px-2 py-1 text-xs"
                            >{{ team.name }} · {{ team.pivot.role }}</span
                        ><span
                            v-if="!user.teams.length"
                            class="text-xs text-muted-foreground"
                            >No workspace memberships</span
                        >
                    </div>
                </div>
                <Button
                    variant="outline"
                    class="self-start sm:self-center"
                    @click="editUser(user)"
                    >Manage user</Button
                >
            </article>
            <nav class="flex gap-3" aria-label="User pages">
                <Link
                    v-if="users.prev_page_url"
                    :href="users.prev_page_url"
                    preserve-state
                    class="text-sm underline"
                    >Previous users</Link
                ><Link
                    v-if="users.next_page_url"
                    :href="users.next_page_url"
                    preserve-state
                    class="text-sm underline"
                    >Next users</Link
                >
            </nav>
        </section>
        <section v-else class="space-y-3">
            <h2 class="text-lg font-semibold">Recent platform changes</h2>
            <p class="text-sm text-muted-foreground">
                The latest 20 administrative actions.
            </p>
            <article
                v-for="event in auditEvents"
                :key="event.id"
                class="rounded-xl border bg-card p-4"
            >
                <p class="font-medium">
                    {{
                        event.action
                            .replaceAll('platform.', '')
                            .replaceAll('_', ' ')
                    }}
                </p>
                <p class="mt-1 text-sm">{{ event.reason }}</p>
                <p class="mt-2 text-xs text-muted-foreground">
                    {{ event.actor?.name || 'Authorized console' }} ·
                    {{ event.team?.name || 'Platform' }} ·
                    {{ new Date(event.created_at).toLocaleString() }}
                </p>
            </article>
            <p v-if="!auditEvents.length" class="text-sm text-muted-foreground">
                No platform changes recorded yet.
            </p>
        </section>
        <Dialog v-model:open="accessOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Open {{ accessWorkspace?.name }}</DialogTitle>
                    <DialogDescription
                        >Read-only access to this workspace’s diary, finance,
                        investor records and evidence for 30 minutes. All visits
                        and downloads are audited. This includes every farm in
                        the selected workspace.</DialogDescription
                    >
                </DialogHeader>
                <form
                    class="grid gap-4"
                    @submit.prevent="enterWorkspaceRecords"
                >
                    <Label for="access-reason">Reason for access</Label>
                    <Input
                        id="access-reason"
                        v-model="accessForm.reason"
                        required
                        maxlength="1000"
                    />
                    <InputError
                        :message="
                            accessForm.errors.reason ||
                            accessForm.errors.team_id
                        "
                    />
                    <Button :disabled="accessForm.processing"
                        >Open read-only session</Button
                    >
                </form>
            </DialogContent>
        </Dialog>
        <Dialog v-model:open="farmOpen"
            ><DialogContent class="max-h-[90dvh] overflow-y-auto"
                ><DialogHeader
                    ><DialogTitle>{{
                        farmId ? 'Edit farm details' : 'Add a farm'
                    }}</DialogTitle
                    ><DialogDescription
                        >Farm records stay in their workspace. Enter a reason
                        for the audit trail.</DialogDescription
                    ></DialogHeader
                >
                <form class="grid gap-4" @submit.prevent="saveFarm">
                    <div class="grid gap-2">
                        <Label for="farm-workspace">Workspace</Label
                        ><select
                            id="farm-workspace"
                            v-model="farmForm.team_id"
                            :disabled="!!farmId"
                            required
                            class="h-10 w-full rounded-md border bg-background px-3"
                        >
                            <option value="" disabled>Select workspace</option>
                            <option
                                v-for="team in workspaces"
                                :key="team.id"
                                :value="String(team.id)"
                            >
                                {{ team.name }}
                            </option></select
                        ><InputError :message="farmForm.errors.team_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="farm-name">Farm name</Label
                        ><Input
                            id="farm-name"
                            v-model="farmForm.name"
                            required
                            maxlength="255"
                        /><InputError :message="farmForm.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="farm-type">Farm type</Label
                        ><select
                            id="farm-type"
                            v-model="farmForm.farm_type"
                            class="h-10 rounded-md border bg-background px-3"
                        >
                            <option
                                v-for="type in farmTypes"
                                :key="type.value"
                                :value="type.value"
                            >
                                {{ type.label }}
                            </option></select
                        ><InputError :message="farmForm.errors.farm_type" />
                    </div>
                    <div
                        v-for="field in [
                            { key: 'location' as const, label: 'Location' },
                            {
                                key: 'contact_name' as const,
                                label: 'Contact name',
                            },
                            {
                                key: 'contact_phone' as const,
                                label: 'Contact phone',
                            },
                            {
                                key: 'description' as const,
                                label: 'Description',
                            },
                        ]"
                        :key="field.key"
                        class="grid gap-2"
                    >
                        <Label :for="`farm-${field.key}`">{{
                            field.label
                        }}</Label
                        ><Input
                            :id="`farm-${field.key}`"
                            v-model="farmForm[field.key]"
                        /><InputError :message="farmForm.errors[field.key]" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="farm-reason">Reason for change</Label
                        ><Input
                            id="farm-reason"
                            v-model="farmForm.reason"
                            required
                            maxlength="1000"
                        /><InputError :message="farmForm.errors.reason" />
                    </div>
                    <Button :disabled="farmForm.processing">{{
                        farmForm.processing ? 'Saving…' : 'Save farm'
                    }}</Button>
                </form>
            </DialogContent></Dialog
        >
        <Dialog v-model:open="userOpen"
            ><DialogContent class="max-h-[90dvh] overflow-y-auto"
                ><DialogHeader
                    ><DialogTitle>Manage {{ selectedUser?.name }}</DialogTitle
                    ><DialogDescription
                        >Profile and workspace access changes are audited.
                        Account sign-in details and workspace ownership stay
                        protected.</DialogDescription
                    ></DialogHeader
                >
                <form class="grid gap-3" @submit.prevent="saveUser">
                    <Label for="user-name">Display name</Label
                    ><Input
                        id="user-name"
                        v-model="userForm.name"
                        required
                        maxlength="255"
                    /><InputError :message="userForm.errors.name" /><Label
                        for="user-reason"
                        >Reason for profile change</Label
                    ><Input
                        id="user-reason"
                        v-model="userForm.reason"
                        required
                        maxlength="1000"
                    /><InputError :message="userForm.errors.reason" /><Button
                        :disabled="userForm.processing"
                        >Save profile</Button
                    >
                </form>
                <form
                    class="mt-3 grid gap-3 border-t pt-5"
                    @submit.prevent="saveMembership"
                >
                    <h3 class="font-semibold">Workspace access</h3>
                    <Label for="member-workspace">Workspace</Label
                    ><select
                        id="member-workspace"
                        v-model="membershipForm.team_id"
                        required
                        class="h-10 rounded-md border bg-background px-3"
                    >
                        <option value="" disabled>Select workspace</option>
                        <option
                            v-for="team in workspaces"
                            :key="team.id"
                            :value="String(team.id)"
                        >
                            {{ team.name }}
                        </option></select
                    ><InputError
                        :message="membershipForm.errors.team_id"
                    /><Label for="member-role">New role</Label
                    ><select
                        id="member-role"
                        v-model="membershipForm.role"
                        :disabled="isWorkspaceOwner"
                        class="h-10 rounded-md border bg-background px-3"
                    >
                        <option v-if="isWorkspaceOwner" value="owner">
                            Owner
                        </option>
                        <option
                            v-for="role in roles"
                            :key="role.value"
                            :value="role.value"
                        >
                            {{ role.label }}
                        </option>
                        <option value="remove" :disabled="!currentMembership">
                            Remove workspace access
                        </option>
                    </select>
                    <p
                        v-if="membershipForm.team_id"
                        class="text-sm text-muted-foreground"
                    >
                        {{
                            isWorkspaceOwner
                                ? 'Workspace ownership cannot be changed here.'
                                : currentMembership
                                  ? `Current role: ${currentMembership.pivot.role}`
                                  : 'This user currently has no access to this workspace.'
                        }}
                    </p>
                    <InputError :message="membershipForm.errors.role" /><Label
                        for="member-reason"
                        >Reason for access change</Label
                    ><Input
                        id="member-reason"
                        v-model="membershipForm.reason"
                        required
                        maxlength="1000"
                    /><InputError
                        :message="membershipForm.errors.reason"
                    /><Button
                        variant="outline"
                        :disabled="
                            membershipForm.processing ||
                            isWorkspaceOwner ||
                            !membershipForm.team_id
                        "
                        >Apply access change</Button
                    >
                </form>
            </DialogContent></Dialog
        >
    </div>
</template>
