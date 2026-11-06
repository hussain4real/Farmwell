<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import NationalPhoneInput from '@/components/NationalPhoneInput.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { store as storeInvitation } from '@/routes/teams/invitations';
import type { RoleOption, Team } from '@/types';

type Props = {
    team: Team;
    availableRoles: RoleOption[];
    open: boolean;
    phoneLoginEnabled: boolean;
    callingCode: string | null;
};

const props = defineProps<Props>();
const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const inviteRole = ref('member');
const formKey = ref(0);
const channel = ref('email');

function handleOpenChange(value: boolean) {
    emit('update:open', value);

    if (!value) {
        inviteRole.value = 'member';
        channel.value = 'email';
        formKey.value++;
    }
}
</script>

<template>
    <Dialog :open="props.open" @update:open="handleOpenChange">
        <DialogContent>
            <Form
                :key="formKey"
                v-bind="storeInvitation.form(props.team.slug)"
                class="space-y-6"
                v-slot="{ errors, processing }"
                @success="emit('update:open', false)"
            >
                <DialogHeader>
                    <DialogTitle>Invite a team member</DialogTitle>
                    <DialogDescription>
                        Send an invitation to join this team.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4">
                    <div v-if="phoneLoginEnabled" class="grid gap-2">
                        <Label for="channel">Invite by</Label>
                        <Select v-model="channel">
                            <SelectTrigger id="channel"
                                ><SelectValue
                            /></SelectTrigger>
                            <SelectContent
                                ><SelectItem value="email">Email</SelectItem
                                ><SelectItem value="phone"
                                    >Phone</SelectItem
                                ></SelectContent
                            >
                        </Select>
                    </div>
                    <div v-if="channel === 'phone'" class="grid gap-2">
                        <NationalPhoneInput
                            v-if="callingCode"
                            id="invite-phone"
                            :calling-code="callingCode"
                        />
                        <InputError :message="errors.phone" />
                        <p class="text-sm text-muted-foreground">
                            Share the invitation link after saving. The
                            recipient confirms their number by SMS.
                        </p>
                    </div>
                    <div v-else class="grid gap-2">
                        <Label for="email">Email address</Label>
                        <Input
                            id="email"
                            name="email"
                            data-test="invite-email"
                            type="email"
                            placeholder="colleague@example.com"
                            required
                        />
                        <InputError :message="errors.email" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="role">Role</Label>
                        <Select
                            v-model="inviteRole"
                            name="role"
                            data-test="invite-role"
                        >
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Select a role" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="role in props.availableRoles"
                                    :key="role.value"
                                    :value="role.value"
                                >
                                    {{ role.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="errors.role" />
                    </div>
                </div>

                <DialogFooter class="gap-2">
                    <DialogClose as-child>
                        <Button variant="secondary"> Cancel </Button>
                    </DialogClose>

                    <Button
                        type="submit"
                        data-test="invite-submit"
                        :disabled="processing"
                    >
                        {{
                            channel === 'phone'
                                ? 'Create phone invitation'
                                : 'Send invitation'
                        }}
                    </Button>
                </DialogFooter>
            </Form>
        </DialogContent>
    </Dialog>
</template>
