<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Input } from '@/components/ui/input';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Label } from '@/components/ui/label';
import {
    internationalPhoneNumber,
    nationalNumberLength,
    pastedNationalDigits,
} from '@/lib/phoneNumber';

const props = defineProps<{ id: string; callingCode: string }>();
const emit = defineEmits<{ validity: [boolean] }>();
const national = ref('');
const length = computed(() => nationalNumberLength(props.callingCode));
const phone = computed(() =>
    internationalPhoneNumber(props.callingCode, national.value),
);
watch(
    () =>
        length.value
            ? national.value.length === length.value
            : Boolean(phone.value),
    (valid) => emit('validity', valid),
    { immediate: true },
);
</script>
<template>
    <div class="grid gap-2">
        <Label :for="id">Mobile number</Label>
        <div class="flex min-w-0 items-center gap-1.5" dir="ltr">
            <span
                :id="id + '-prefix'"
                class="rounded-md border bg-muted px-3 py-2 text-sm tabular-nums"
                dir="ltr"
            >
                <span class="sr-only">Country calling code </span
                >{{ callingCode }}
            </span>
            <InputOTP
                v-if="length"
                :id="id"
                v-model="national"
                :aria-describedby="id + '-prefix ' + id + '-hint'"
                type="tel"
                inputmode="numeric"
                autocomplete="tel-national"
                pattern="^[1-9][0-9]*$"
                :maxlength="length"
                :minlength="length"
                :paste-transformer="
                    (text) => pastedNationalDigits(callingCode, text)
                "
                push-password-manager-strategy="none"
                class="min-w-0 flex-1"
                required
            >
                <InputOTPGroup class="w-full">
                    <InputOTPSlot
                        v-for="digit in length"
                        :key="digit"
                        :index="digit - 1"
                        class="h-11 min-w-0 flex-1 border-input text-base tabular-nums"
                    />
                </InputOTPGroup>
            </InputOTP>
            <Input
                v-else
                v-model="national"
                :id="id"
                :aria-describedby="id + '-prefix'"
                type="tel"
                inputmode="tel"
                autocomplete="tel-national"
                dir="ltr"
                required
            />
        </div>
        <p
            v-if="length"
            :id="id + '-hint'"
            class="text-sm text-muted-foreground"
        >
            Enter {{ length }} digits after {{ callingCode }}.
        </p>
        <input type="hidden" name="phone" :value="phone" />
    </div>
</template>
