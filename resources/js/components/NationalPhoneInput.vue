<script setup lang="ts">
import { computed, ref } from 'vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { internationalPhoneNumber } from '@/lib/phoneNumber';

const props = defineProps<{ id: string; callingCode: string }>();
const national = ref('');
const phone = computed(() =>
    internationalPhoneNumber(props.callingCode, national.value),
);
</script>
<template>
    <div class="grid gap-2">
        <Label :for="id">Mobile number</Label>
        <div class="flex items-center gap-2">
            <span
                :id="id + '-prefix'"
                class="rounded-md border bg-muted px-3 py-2 text-sm tabular-nums"
                dir="ltr"
            >
                <span class="sr-only">Country calling code </span
                >{{ callingCode }}
            </span>
            <Input
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
        <input type="hidden" name="phone" :value="phone" />
    </div>
</template>
