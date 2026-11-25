<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import NationalPhoneInput from '@/components/NationalPhoneInput.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Label } from '@/components/ui/label';
import { login } from '@/routes';
import { send, show, verify } from '@/routes/phone-login';

defineOptions({ layout: { title: 'Sign in with your phone' } });
defineProps<{
    invite: string | null;
    teamName: string | null;
    awaitingCode: boolean;
    codeLifetimeMinutes: number;
    callingCode: string;
    pilot: boolean;
    emailSignInAllowed: boolean;
    countries: Record<string, string>;
}>();
const requestAnother = ref(false);
const phoneComplete = ref(false);
const otp = ref('');
const codeLength = 6;
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
            @success="
                requestAnother = false;
                otp = '';
            "
        >
            <input v-if="invite" type="hidden" name="invite" :value="invite" />
            <NationalPhoneInput
                :key="callingCode"
                id="phone"
                :calling-code="callingCode"
                @validity="phoneComplete = $event"
            />
            <InputError :message="errors.phone || errors.service" />
            <Button type="submit" :disabled="processing || !phoneComplete"
                >Send sign-in code</Button
            >
        </Form>
        <template v-else>
            <p class="text-sm text-muted-foreground">
                If your number is eligible, you’ll receive a six-digit code. It
                expires in {{ codeLifetimeMinutes }} minutes.
            </p>
            <Form
                v-bind="verify.form()"
                v-slot="{ errors, processing }"
                class="grid gap-3"
                @error="otp = ''"
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
                <InputOTP
                    id="otp"
                    v-model="otp"
                    name="otp"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    pattern="^[0-9]*$"
                    :minlength="codeLength"
                    :maxlength="codeLength"
                    :paste-transformer="
                        (text) => (text ?? '').replace(/\D/g, '')
                    "
                    :aria-invalid="Boolean(errors.otp)"
                    aria-describedby="otp-error"
                    required
                >
                    <InputOTPGroup>
                        <InputOTPSlot
                            v-for="digit in codeLength"
                            :key="digit"
                            :index="digit - 1"
                            class="size-11 text-lg tabular-nums"
                        />
                    </InputOTPGroup>
                </InputOTP>
                <InputError
                    id="otp-error"
                    :message="errors.otp || errors.service"
                />
                <Button
                    type="submit"
                    :disabled="processing || otp.length !== codeLength"
                    >{{
                        invite ? 'Verify and join farm' : 'Verify and sign in'
                    }}</Button
                >
            </Form>
            <Button variant="outline" @click="requestAnother = true"
                >Request another code</Button
            >
        </template>
        <template v-if="pilot && !invite && !awaitingCode">
            <template v-for="(prefix, region) in countries" :key="region">
                <TextLink
                    v-if="prefix !== callingCode"
                    :href="show({ query: { country: region } })"
                    >Use a {{ region === 'QA' ? 'Qatari' : 'Nigerian' }} number
                    ({{ prefix }})</TextLink
                >
            </template>
        </template>
        <TextLink
            v-if="emailSignInAllowed"
            :href="login({ query: { method: 'email' } })"
            >Sign in with email</TextLink
        >
    </div>
</template>
