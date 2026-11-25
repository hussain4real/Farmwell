<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { interceptOfflineFormSubmit } from '@/composables/useOfflineLogSubmit';
import { store as storeProductionLog } from '@/routes/farms/production-cycles/production-logs';

export type LogUnitOption = { id: number; name: string };
export type LogInventoryOption = {
    id: number;
    farmId: number | null;
    name: string;
    category: string;
    unit: string;
    currentQuantity: string;
};

const props = defineProps<{
    teamSlug: string;
    farmId: number;
    cycleId: number;
    units: LogUnitOption[];
    inventoryItems: LogInventoryOption[];
    isLayer?: boolean;
}>();

const emit = defineEmits<{
    saved: [];
}>();

const today = new Date().toISOString().slice(0, 10);
const selectedUnitId = ref(props.units[0]?.id ? String(props.units[0].id) : '');
const selectedInventoryId = ref('');
const eggsCollected = ref('');

const feedInventoryItems = computed(() =>
    props.inventoryItems.filter(
        (item) => item.category === 'feed' || item.category === 'medication',
    ),
);

const estimatedCrates = computed(() => {
    const count = Number(eggsCollected.value);

    if (!count || Number.isNaN(count)) {
        return null;
    }

    return (count / 30).toFixed(1);
});

const handleOfflineSubmit = (event: SubmitEvent) => {
    const queued = interceptOfflineFormSubmit(event, {
        url: storeProductionLog.url([
            props.teamSlug,
            props.farmId,
            props.cycleId,
        ]),
        domain: 'poultry',
        label: 'Poultry daily log',
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
        <input type="hidden" name="domain" value="poultry" />

        <div class="grid gap-1.5">
            <Label for="poultry-log-date">Log date</Label>
            <Input
                id="poultry-log-date"
                name="log_date"
                type="date"
                :default-value="today"
                required
            />
            <InputError :message="errors.log_date" />
        </div>

        <div class="grid gap-1.5">
            <Label for="poultry-log-unit">Pen / House</Label>
            <select
                id="poultry-log-unit"
                v-model="selectedUnitId"
                name="production_unit_id"
                class="h-11 rounded-md border bg-background px-3 text-base md:h-9 md:text-sm"
            >
                <option value="">All cycle houses</option>
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
            <Label for="poultry-mortality">Mortality (birds)</Label>
            <Input
                id="poultry-mortality"
                name="mortality_count"
                type="number"
                inputmode="numeric"
                min="0"
                default-value="0"
            />
            <InputError :message="errors.mortality_count" />
        </div>

        <div class="grid gap-1.5">
            <Label for="poultry-culls">Culls (birds)</Label>
            <Input
                id="poultry-culls"
                name="cull_count"
                type="number"
                inputmode="numeric"
                min="0"
                default-value="0"
            />
            <InputError :message="errors.cull_count" />
        </div>

        <div class="grid gap-1.5">
            <Label for="poultry-feed">Feed given (kg)</Label>
            <Input
                id="poultry-feed"
                name="feed_used_kg"
                type="number"
                inputmode="decimal"
                min="0"
                step="0.01"
                placeholder="e.g. 50"
            />
            <InputError :message="errors.feed_used_kg" />
        </div>

        <div class="grid gap-1.5">
            <Label for="poultry-feed-type">Feed type</Label>
            <Input
                id="poultry-feed-type"
                name="feed_type"
                placeholder="Starter / Finisher / Layers Mash"
            />
            <InputError :message="errors.feed_type" />
        </div>

        <div
            v-if="feedInventoryItems.length > 0"
            class="grid gap-1.5 sm:col-span-2"
        >
            <Label for="poultry-stock-item">
                Deduct from feed stock (optional)
            </Label>
            <select
                id="poultry-stock-item"
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

        <div class="grid gap-1.5">
            <Label for="poultry-water">Water intake (liters)</Label>
            <Input
                id="poultry-water"
                name="water_used_liters"
                type="number"
                inputmode="decimal"
                min="0"
                step="0.1"
            />
            <InputError :message="errors.water_used_liters" />
        </div>

        <div class="grid gap-1.5">
            <Label for="poultry-weight">Sample avg weight (g)</Label>
            <Input
                id="poultry-weight"
                name="average_weight_g"
                type="number"
                inputmode="decimal"
                min="0"
                step="0.1"
                placeholder="e.g. 1450"
            />
            <InputError :message="errors.average_weight_g" />
        </div>

        <div class="grid gap-1.5">
            <Label for="poultry-eggs">
                Eggs collected (count)
                <span
                    v-if="estimatedCrates"
                    class="ml-1 text-xs font-normal text-emerald-700 dark:text-emerald-300"
                >
                    ≈ {{ estimatedCrates }} crates
                </span>
            </Label>
            <Input
                id="poultry-eggs"
                v-model="eggsCollected"
                name="eggs_collected"
                type="number"
                inputmode="numeric"
                min="0"
                :placeholder="isLayer ? 'e.g. 420' : 'Optional for broilers'"
            />
            <InputError :message="errors.eggs_collected" />
        </div>

        <div class="grid gap-1.5">
            <Label for="poultry-eggs-damaged">Cracked / damaged eggs</Label>
            <Input
                id="poultry-eggs-damaged"
                name="eggs_damaged"
                type="number"
                inputmode="numeric"
                min="0"
            />
            <InputError :message="errors.eggs_damaged" />
        </div>

        <div class="grid gap-1.5 sm:col-span-2">
            <Label for="poultry-notes">Observations & notes</Label>
            <textarea
                id="poultry-notes"
                name="notes"
                rows="2"
                placeholder="Litter condition, temperature, bird activity..."
                class="rounded-md border bg-background px-3 py-2 text-base md:text-sm"
            />
            <InputError :message="errors.notes" />
        </div>

        <div class="grid gap-1.5 sm:col-span-2">
            <Label for="poultry-evidence">Photo evidence (optional)</Label>
            <Input
                id="poultry-evidence"
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
                Save poultry log
            </Button>
        </div>
    </Form>
</template>
