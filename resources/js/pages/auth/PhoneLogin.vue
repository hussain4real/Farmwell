<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import NationalPhoneInput from '@/components/NationalPhoneInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { login } from '@/routes';
import { send, verify } from '@/routes/phone-login';

defineOptions({ layout: { title: 'Sign in with your phone' } });
defineProps<{
    invite: string | null;
    teamName: string | null;
    awaitingCode: boolean;
    callingCode: string;
}>();
const requestAnother = ref(false);
</script>

<template>
    <div class="grid gap-5">
        <Head title="Phone sign-in" />
        <p v-if="teamName">
            You've been invited to join <strong>{{ teamName }}</strong
            >.
        </p>
        <Form
            v-if="!awaitingCode || requestAnother"
            v-bind="send.form()"
            v-slot="{ errors, processing }"
            class="grid gap-3"
            @success="requestAnother = false"
        >
            <input v-if="invite" type="hidden" name="invite" :value="invite" />
            <NationalPhoneInput id="phone" :calling-code="callingCode" />
            <InputError :message="errors.phone || errors.service" />
            <Button type="submit" :disabled="processing"
                >Send sign-in code</Button
            >
        </Form>
        <template v-else>
            <p class="text-sm text-muted-foreground">
                If your number is eligible, you’ll receive a six-digit code. It
                expires in five minutes.
            </p>
            <Form
                v-bind="verify.form()"
                :reset-on-error="['otp']"
                v-slot="{ errors, processing }"
                class="grid gap-3"
            >
                <template v-if="invite">
                    <Label for="name">Your name</Label>
                    <Input
                        id="name"
                        name="name"
                        autocomplete="name"
                        maxlength="255"
                        required
                    />
                    <InputError :message="errors.name" />
                </template>
                <Label for="otp">SMS code</Label>
                <Input
                    id="otp"
                    name="otp"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    pattern="[0-9]{6}"
                    maxlength="6"
                    required
                />
                <InputError :message="errors.otp || errors.service" />
                <Button type="submit" :disabled="processing">{{
                    invite ? 'Verify and join farm' : 'Verify and sign in'
                }}</Button>
            </Form>
            <Button variant="outline" @click="requestAnother = true"
                >Request another code</Button
            >
        </template>
        <TextLink :href="login()">Sign in with email</TextLink>
    </div>
</template>
