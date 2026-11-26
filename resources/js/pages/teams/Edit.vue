<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import { ChevronDown, Mail, UserPlus, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { toast } from 'vue-sonner';
import CancelInvitationModal from '@/components/CancelInvitationModal.vue';
import DeleteTeamModal from '@/components/DeleteTeamModal.vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import InviteMemberModal from '@/components/InviteMemberModal.vue';
import RemoveMemberModal from '@/components/RemoveMemberModal.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useInitials } from '@/composables/useInitials';
import { edit, index, update } from '@/routes/teams';
import { update as updateMember } from '@/routes/teams/members';
import { update as updateTeamSetting } from '@/routes/teams/settings';
import type {
    RoleOption,
    Team,
    TeamInvitation,
    TeamMember,
    TeamPermissions,
} from '@/types';

type Props = {
    team: Team;
    members: TeamMember[];
    invitations: TeamInvitation[];
    permissions: TeamPermissions;
    investorMode: boolean;
    dailyRemindersEnabled: boolean;
    availableRoles: RoleOption[];
    phoneLoginEnabled: boolean;
    callingCode: string | null;
    phoneCountries: Record<string, string> | null;
};

const props = defineProps<Props>();

defineOptions({
    layout: (props: { team: Team }) => ({
        breadcrumbs: [
            {
                title: 'Teams',
                href: index(),
            },
            {
                title: props.team.name,
                href: edit(props.team.slug),
            },
        ],
    }),
});

const { getInitials } = useInitials();

const inviteDialogOpen = ref(false);
const deleteDialogOpen = ref(false);
const removeMemberDialogOpen = ref(false);
const memberToRemove = ref<TeamMember | null>(null);
const cancelInvitationDialogOpen = ref(false);
const invitationToCancel = ref<TeamInvitation | null>(null);

const pageTitle = computed(() =>
    props.permissions.canUpdateTeam
        ? `Edit ${props.team.name}`
        : `View ${props.team.name}`,
);

const updateMemberRole = (member: TeamMember, newRole: string) => {
    router.visit(updateMember([props.team.slug, member.id]), {
        data: { role: newRole },
        preserveScroll: true,
    });
};

const togglingInvestorMode = ref(false);
const toggleInvestorMode = () => {
    togglingInvestorMode.value = true;
    router.patch(
        updateTeamSetting.url([props.team.slug, 'workspace', 'investor_mode']),
        {
            value: { enabled: !props.investorMode },
            reason: !props.investorMode
                ? 'Enabled investor transparency mode'
                : 'Switched to standalone farm mode',
        },
        {
            preserveScroll: true,
            onFinish: () => {
                togglingInvestorMode.value = false;
            },
        },
    );
};

const togglingDailyReminders = ref(false);
const toggleDailyReminders = () => {
    togglingDailyReminders.value = true;
    router.patch(
        updateTeamSetting.url([
            props.team.slug,
            'operations',
            'daily_reminders',
        ]),
        {
            value: { enabled: !props.dailyRemindersEnabled },
            reason: !props.dailyRemindersEnabled
                ? 'Enabled daily farm operational reminders'
                : 'Paused daily farm operational reminders',
        },
        {
            preserveScroll: true,
            onFinish: () => {
                togglingDailyReminders.value = false;
            },
        },
    );
};

const confirmRemoveMember = (member: TeamMember) => {
    memberToRemove.value = member;
    removeMemberDialogOpen.value = true;
};

const confirmCancelInvitation = (invitation: TeamInvitation) => {
    invitationToCancel.value = invitation;
    cancelInvitationDialogOpen.value = true;
};

const copyInvitationLink = async (url: string | null | undefined) => {
    if (!url) {
        return;
    }

    try {
        await navigator.clipboard.writeText(url);
        toast.success('Invitation link copied.');
    } catch {
        toast.error(
            'Could not copy the link. Select the link to copy it manually.',
        );
    }
};
</script>

<template>
    <Head :title="pageTitle" />

    <h1 class="sr-only">{{ pageTitle }}</h1>

    <div class="flex flex-col space-y-10">
        <!-- Team Name Section -->
        <div v-if="permissions.canUpdateTeam" class="space-y-6">
            <Heading
                variant="small"
                title="Team settings"
                description="Update your team name and settings"
            />

            <Form
                v-bind="update.form(team.slug)"
                class="space-y-6"
                v-slot="{ errors, processing }"
            >
                <div class="grid gap-2">
                    <Label for="name">Team name</Label>
                    <Input
                        id="name"
                        name="name"
                        data-test="team-name-input"
                        :default-value="team.name"
                        required
                    />
                    <InputError :message="errors.name" />
                </div>

                <div class="flex items-center gap-4">
                    <Button
                        type="submit"
                        data-test="team-save-button"
                        :disabled="processing"
                    >
                        Save
                    </Button>
                </div>
            </Form>
        </div>

        <div v-else class="space-y-6">
            <Heading variant="small" :title="team.name" />
        </div>

        <!-- Workspace Mode Section -->
        <div
            v-if="permissions.canManageSettings"
            class="space-y-4 rounded-xl border p-5"
        >
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <h3 class="text-base font-medium">
                            Investor Transparency Mode
                        </h3>
                        <Badge
                            :variant="investorMode ? 'default' : 'secondary'"
                        >
                            {{
                                investorMode
                                    ? 'Enabled'
                                    : 'Standalone Farm Mode'
                            }}
                        </Badge>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{
                            investorMode
                                ? 'Investor agreements, approvals, and investor-safe visibility fields are shown across the workspace.'
                                : 'Optimized for standalone farmers. Enable this if you raise external farm capital and need investor approvals and statements.'
                        }}
                    </p>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    data-test="toggle-investor-mode"
                    :disabled="togglingInvestorMode"
                    @click="toggleInvestorMode"
                >
                    {{
                        investorMode
                            ? 'Switch to Standalone Mode'
                            : 'Enable Investor Mode'
                    }}
                </Button>
            </div>
        </div>

        <!-- Daily Reminders Section -->
        <div
            v-if="permissions.canManageSettings"
            class="space-y-4 rounded-xl border p-5"
        >
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        <h3 class="text-base font-medium">
                            Daily Farm Alert Emails
                        </h3>
                        <Badge
                            :variant="
                                dailyRemindersEnabled ? 'default' : 'secondary'
                            "
                        >
                            {{ dailyRemindersEnabled ? 'Enabled' : 'Paused' }}
                        </Badge>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{
                            dailyRemindersEnabled
                                ? 'Owners, admins, and operators receive a morning email digest when vaccinations, withdrawal periods, low stock, or water-quality alerts need attention.'
                                : 'Morning operational email reminders are currently paused for this team.'
                        }}
                    </p>
                </div>
                <Button
                    type="button"
                    variant="outline"
                    data-test="toggle-daily-reminders"
                    class="min-h-11 w-full sm:min-h-9 sm:w-auto"
                    :disabled="togglingDailyReminders"
                    @click="toggleDailyReminders"
                >
                    {{
                        dailyRemindersEnabled
                            ? 'Pause Daily Alerts'
                            : 'Enable Daily Alerts'
                    }}
                </Button>
            </div>
        </div>

        <!-- Members Section -->
        <div class="space-y-6">
            <div class="flex items-center justify-between">
                <Heading
                    variant="small"
                    title="Team members"
                    :description="
                        permissions.canCreateInvitation
                            ? 'Manage who belongs to this team'
                            : ''
                    "
                />

                <Button
                    v-if="permissions.canCreateInvitation"
                    data-test="invite-member-button"
                    @click="inviteDialogOpen = true"
                >
                    <UserPlus /> Invite member
                </Button>
            </div>

            <div class="space-y-3">
                <div
                    v-for="member in members"
                    :key="member.id"
                    data-test="member-row"
                    class="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-4"
                >
                    <div class="flex min-w-0 items-center gap-3">
                        <Avatar class="h-10 w-10 shrink-0">
                            <AvatarImage
                                v-if="member.avatar"
                                :src="member.avatar"
                                :alt="member.name"
                            />
                            <AvatarFallback>{{
                                getInitials(member.name)
                            }}</AvatarFallback>
                        </Avatar>
                        <div class="min-w-0">
                            <div class="truncate font-medium">
                                {{ member.name }}
                            </div>
                            <div class="truncate text-sm text-muted-foreground">
                                {{ member.email }}
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <DropdownMenu
                            v-if="
                                member.role !== 'owner' &&
                                permissions.canUpdateMember
                            "
                        >
                            <DropdownMenuTrigger as-child>
                                <Button
                                    data-test="member-role-trigger"
                                    variant="outline"
                                    size="sm"
                                >
                                    {{ member.role_label }}
                                    <ChevronDown
                                        class="ml-2 h-4 w-4 opacity-50"
                                    />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent>
                                <DropdownMenuItem
                                    v-for="role in availableRoles"
                                    :key="role.value"
                                    data-test="member-role-option"
                                    @click="
                                        updateMemberRole(member, role.value)
                                    "
                                >
                                    {{ role.label }}
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                        <Badge v-else variant="secondary">
                            {{ member.role_label }}
                        </Badge>

                        <TooltipProvider
                            v-if="
                                member.role !== 'owner' &&
                                permissions.canRemoveMember
                            "
                        >
                            <Tooltip>
                                <TooltipTrigger as-child>
                                    <Button
                                        data-test="member-remove-button"
                                        aria-label="Remove member"
                                        variant="ghost"
                                        size="sm"
                                        @click="confirmRemoveMember(member)"
                                    >
                                        <X class="h-4 w-4" />
                                    </Button>
                                </TooltipTrigger>
                                <TooltipContent>
                                    <p>Remove member</p>
                                </TooltipContent>
                            </Tooltip>
                        </TooltipProvider>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Invitations Section -->
        <div v-if="invitations.length > 0" class="space-y-6">
            <Heading
                variant="small"
                title="Pending invitations"
                description="Invitations that haven't been accepted yet"
            />

            <div class="space-y-3">
                <div
                    v-for="invitation in invitations"
                    :key="invitation.code"
                    data-test="invitation-row"
                    class="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-4"
                >
                    <div class="flex items-center gap-4">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-full bg-muted"
                        >
                            <Mail class="h-5 w-5 text-muted-foreground" />
                        </div>
                        <div>
                            <div class="font-medium">
                                {{ invitation.email ?? invitation.phone }}
                            </div>
                            <div class="text-sm text-muted-foreground">
                                {{ invitation.role_label }}
                            </div>
                        </div>
                    </div>

                    <div
                        v-if="
                            invitation.phone &&
                            invitation.phone_url &&
                            phoneLoginEnabled &&
                            permissions.canCreateInvitation
                        "
                        class="order-last grid w-full min-w-0 gap-1 sm:order-none sm:w-auto sm:flex-1"
                    >
                        <Label :for="'share-' + invitation.code"
                            >Invitation link</Label
                        >
                        <div class="flex gap-2">
                            <Input
                                :id="'share-' + invitation.code"
                                readonly
                                :model-value="invitation.phone_url"
                                @focus="
                                    ($event.target as HTMLInputElement).select()
                                "
                            />
                            <Button
                                type="button"
                                variant="outline"
                                class="shrink-0"
                                @click="
                                    copyInvitationLink(invitation.phone_url)
                                "
                            >
                                Copy link
                            </Button>
                        </div>
                    </div>
                    <TooltipProvider v-if="permissions.canCancelInvitation">
                        <Tooltip>
                            <TooltipTrigger as-child>
                                <Button
                                    data-test="invitation-cancel-button"
                                    variant="ghost"
                                    size="sm"
                                    @click="confirmCancelInvitation(invitation)"
                                >
                                    <X class="h-4 w-4" />
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>
                                <p>Cancel invitation</p>
                            </TooltipContent>
                        </Tooltip>
                    </TooltipProvider>
                </div>
            </div>
        </div>

        <!-- Danger Zone -->
        <div
            v-if="permissions.canDeleteTeam && !team.isPersonal"
            class="space-y-6"
        >
            <Heading
                variant="small"
                title="Delete team"
                description="Permanently delete your team"
            />
            <div
                class="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10"
            >
                <div
                    class="relative space-y-0.5 text-red-600 dark:text-red-100"
                >
                    <p class="font-medium">Warning</p>
                    <p class="text-sm">
                        Please proceed with caution, this cannot be undone.
                    </p>
                </div>
                <Button
                    data-test="delete-team-button"
                    variant="destructive"
                    @click="deleteDialogOpen = true"
                    >Delete team</Button
                >
            </div>
        </div>
    </div>

    <InviteMemberModal
        v-if="permissions.canCreateInvitation"
        :team="team"
        :available-roles="availableRoles"
        :phone-login-enabled="phoneLoginEnabled"
        :calling-code="callingCode"
        :phone-countries="phoneCountries"
        :open="inviteDialogOpen"
        @update:open="inviteDialogOpen = $event"
    />

    <RemoveMemberModal
        :team="team"
        :member="memberToRemove"
        :open="removeMemberDialogOpen"
        @update:open="removeMemberDialogOpen = $event"
    />

    <CancelInvitationModal
        :team="team"
        :invitation="invitationToCancel"
        :open="cancelInvitationDialogOpen"
        @update:open="cancelInvitationDialogOpen = $event"
    />

    <DeleteTeamModal
        v-if="permissions.canDeleteTeam && !team.isPersonal"
        :team="team"
        :open="deleteDialogOpen"
        @update:open="deleteDialogOpen = $event"
    />
</template>
