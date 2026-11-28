<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { interceptOfflineFormSubmit } from '@/composables/useOfflineLogSubmit';
import { store as storeProductionLog } from '@/routes/farms/production-cycles/production-logs';
import type {
    LogInventoryOption,
    LogUnitOption,
} from './PoultryDailyLogForm.vue';

const props = defineProps<{
    teamSlug: string;
    farmId: number;
    cycleId: number;
    units: LogUnitOption[];
    inventoryItems: LogInventoryOption[];
}>();

const emit = defineEmits<{
    saved: [];
}>();

const today = new Date().toISOString().slice(0, 10);
const selectedUnitId = ref(props.units[0]?.id ? String(props.units[0].id) : '');
const selectedInventoryId = ref('');
const waterExchanged = ref(false);

const feedInventoryItems = computed(() =>
    props.inventoryItems.filter(
        (item) => item.category === 'feed' || item.category === 'medication',
    ),
);

const handleOfflineSubmit = (event: SubmitEvent) => {
    const queued = interceptOfflineFormSubmit(event, {
        url: storeProductionLog.url([
            props.teamSlug,
            props.farmId,
            props.cycleId,
        ]),
        domain: 'aquaculture',
        label: 'Pond daily log',
    });

    if (queued) {
        emit('saved');
    }
};
</script>

<template>
    <Form
        v-bind="storeProductionLog.form([teamSlug, farmId, cycleId])"
        class="grid gap-4 sm:grid-cols-2"
        reset-on-success
        v-slot="{ errors, processing }"
        @submit.capture="handleOfflineSubmit"
        @success="emit('saved')"
    >
        <input type="hidden" name="domain" value="aquaculture" />
        <input
            type="hidden"
            name="water_exchanged"
            :value="waterExchanged ? '1' : '0'"
        />

        <div class="grid gap-1.5">
            <Label for="fish-log-date">Log date</Label>
            <Input
                id="fish-log-date"
                name="log_date"
                type="date"
                :default-value="today"
                required
            />
            <InputError :message="errors.log_date" />
        </div>

        <div class="grid gap-1.5">
            <Label for="fish-log-unit">Pond / Tank</Label>
            <select
                id="fish-log-unit"
                v-model="selectedUnitId"
                name="production_unit_id"
                class="h-11 rounded-md border bg-background px-3 text-base md:h-9 md:text-sm"
            >
                <option value="">All cycle ponds</option>
                <option
                    v-for="unit in units"
                    :key="unit.id"
                    :value="String(unit.id)"
                >
                    {{ unit.name }}
                </option>
            </select>
            <InputError :message="errors.production_unit_id" />
        </div>

        <div class="grid gap-1.5">
            <Label for="fish-feed">Feed given (kg)</Label>
            <Input
                id="fish-feed"
                name="feed_used_kg"
                type="number"
                inputmode="decimal"
                min="0"
                step="0.01"
                placeholder="e.g. 15"
            />
            <InputError :message="errors.feed_used_kg" />
        </div>

        <div class="grid gap-1.5">
            <Label for="fish-pellet">Pellet size</Label>
            <Input
                id="fish-pellet"
                name="feed_pellet_size_mm"
                placeholder="e.g. 2mm, 4mm, 6mm"
            />
            <InputError :message="errors.feed_pellet_size_mm" />
        </div>

        <div
            v-if="feedInventoryItems.length > 0"
            class="grid gap-1.5 sm:col-span-2"
        >
            <Label for="fish-stock-item">
                Deduct from feed stock (optional)
            </Label>
            <select
                id="fish-stock-item"
                v-model="selectedInventoryId"
                name="inventory_item_id"
                class="h-11 rounded-md border bg-background px-3 text-base md:h-9 md:text-sm"
            >
                <option value="">Do not deduct stock</option>
                <option
                    v-for="item in feedInventoryItems"
                    :key="item.id"
                    :value="String(item.id)"
                >
                    {{ item.name }} ({{ item.currentQuantity }}
                    {{ item.unit }} left)
                </option>
            </select>
            <InputError :message="errors.inventory_item_id" />
        </div>

        <div
            class="grid gap-3 rounded-xl border bg-cyan-50/40 p-3.5 sm:col-span-2 sm:grid-cols-2 dark:bg-cyan-950/20"
        >
            <p
                class="text-xs font-semibold text-cyan-900 sm:col-span-2 dark:text-cyan-200"
            >
                Water quality readings (safe targets: pH 6.5–8.5, DO ≥ 4.0 mg/L,
                Ammonia ≤ 0.5 mg/L)
            </p>
            <div class="grid gap-1.5">
                <Label for="fish-ph">Water pH</Label>
                <Input
                    id="fish-ph"
                    name="water_ph"
                    type="number"
                    inputmode="decimal"
                    min="0"
                    max="14"
                    step="0.01"
                    placeholder="7.2"
                />
                <InputError :message="errors.water_ph" />
            </div>
            <div class="grid gap-1.5">
                <Label for="fish-temp">Temperature (°C)</Label>
                <Input
                    id="fish-temp"
                    name="water_temp_c"
                    type="number"
                    inputmode="decimal"
                    min="0"
                    max="60"
                    step="0.1"
                    placeholder="28.0"
                />
                <InputError :message="errors.water_temp_c" />
            </div>
            <div class="grid gap-1.5">
                <Label for="fish-do">Dissolved oxygen (mg/L)</Label>
                <Input
                    id="fish-do"
                    name="dissolved_oxygen_mg_l"
                    type="number"
                    inputmode="decimal"
                    min="0"
                    max="30"
                    step="0.01"
                    placeholder="5.5"
                />
                <InputError :message="errors.dissolved_oxygen_mg_l" />
            </div>
            <div class="grid gap-1.5">
                <Label for="fish-ammonia">Ammonia (mg/L)</Label>
                <Input
                    id="fish-ammonia"
                    name="ammonia_mg_l"
                    type="number"
                    inputmode="decimal"
                    min="0"
                    max="20"
                    step="0.001"
                    placeholder="0.10"
                />
                <InputError :message="errors.ammonia_mg_l" />
            </div>
            <label
                class="flex min-h-10 items-center gap-2.5 text-sm sm:col-span-2"
            >
                <input
                    v-model="waterExchanged"
                    type="checkbox"
                    class="size-4"
                />
                <span>Water flush / partial exchange performed today</span>
            </label>
        </div>

        <div class="grid gap-1.5">
            <Label for="fish-mortality">Mortality count</Label>
            <Input
                id="fish-mortality"
                name="mortality_count"
                type="number"
                inputmode="numeric"
                min="0"
                default-value="0"
            />
            <InputError :message="errors.mortality_count" />
        </div>

        <div class="grid gap-1.5">
            <Label for="fish-weight">Sample avg weight (g)</Label>
            <Input
                id="fish-weight"
                name="average_weight_g"
                type="number"
                inputmode="decimal"
                min="0"
                step="0.1"
                placeholder="e.g. 320"
            />
            <InputError :message="errors.average_weight_g" />
        </div>

        <div class="grid gap-1.5 sm:col-span-2">
            <Label for="fish-notes">Feeding response & notes</Label>
            <textarea
                id="fish-notes"
                name="notes"
                rows="2"
                placeholder="Swimming behavior, water color, appetite..."
                class="rounded-md border bg-background px-3 py-2 text-base md:text-sm"
            />
            <InputError :message="errors.notes" />
        </div>

        <div class="grid gap-1.5 sm:col-span-2">
            <Label for="fish-evidence">Photo evidence (optional)</Label>
            <Input
                id="fish-evidence"
                name="evidence[]"
                type="file"
                multiple
                accept=".jpg,.jpeg,.png,.webp,.pdf"
            />
            <InputError :message="errors.evidence" />
        </div>

        <div class="flex justify-end sm:col-span-2">
            <Button
                type="submit"
                :disabled="processing"
                class="min-h-11 w-full sm:min-h-9 sm:w-auto"
            >
                Save pond log
            </Button>
        </div>
    </Form>
</template>
