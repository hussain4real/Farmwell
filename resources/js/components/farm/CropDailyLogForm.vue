<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { interceptOfflineFormSubmit } from '@/composables/useOfflineLogSubmit';
import { store as storeProductionLog } from '@/routes/farms/production-cycles/production-logs';
import type { FarmOption } from '@/types';
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
    growthStages: FarmOption[];
    pestPressures: FarmOption[];
    currentGrowthStage?: string | null;
}>();

const emit = defineEmits<{
    saved: [];
}>();

const today = new Date().toISOString().slice(0, 10);
const selectedUnitId = ref(props.units[0]?.id ? String(props.units[0].id) : '');
const selectedGrowthStage = ref(props.currentGrowthStage ?? '');
const selectedPestPressure = ref('none');
const selectedInventoryId = ref('');
const irrigationApplied = ref(false);

const cropInventoryItems = computed(() =>
    props.inventoryItems.filter((item) =>
        ['seed', 'fertilizer', 'agrochemical', 'other'].includes(item.category),
    ),
);

const handleOfflineSubmit = (event: SubmitEvent) => {
    const queued = interceptOfflineFormSubmit(event, {
        url: storeProductionLog.url([
            props.teamSlug,
            props.farmId,
            props.cycleId,
        ]),
        domain: 'crop',
        label: 'Crop daily log',
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
        <input type="hidden" name="domain" value="crop" />
        <input
            type="hidden"
            name="irrigation_applied"
            :value="irrigationApplied ? '1' : '0'"
        />

        <div class="grid gap-1.5">
            <Label for="crop-log-date">Log date</Label>
            <Input
                id="crop-log-date"
                name="log_date"
                type="date"
                :default-value="today"
                required
            />
            <InputError :message="errors.log_date" />
        </div>

        <div class="grid gap-1.5">
            <Label for="crop-log-unit">Field / Plot</Label>
            <select
                id="crop-log-unit"
                v-model="selectedUnitId"
                name="production_unit_id"
                class="h-11 rounded-md border bg-background px-3 text-base md:h-9 md:text-sm"
            >
                <option value="">All cycle plots</option>
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
            <Label for="crop-growth-stage">Growth stage</Label>
            <select
                id="crop-growth-stage"
                v-model="selectedGrowthStage"
                name="growth_stage"
                class="h-11 rounded-md border bg-background px-3 text-base md:h-9 md:text-sm"
            >
                <option value="">Unchanged</option>
                <option
                    v-for="stage in growthStages"
                    :key="stage.value"
                    :value="stage.value"
                >
                    {{ stage.label }}
                </option>
            </select>
            <InputError :message="errors.growth_stage" />
        </div>

        <div class="grid gap-1.5">
            <Label for="crop-pest-pressure">Pest pressure</Label>
            <select
                id="crop-pest-pressure"
                v-model="selectedPestPressure"
                name="pest_pressure"
                class="h-11 rounded-md border bg-background px-3 text-base md:h-9 md:text-sm"
            >
                <option
                    v-for="pressure in pestPressures"
                    :key="pressure.value"
                    :value="pressure.value"
                >
                    {{ pressure.label }}
                </option>
            </select>
            <InputError :message="errors.pest_pressure" />
        </div>

        <div class="grid gap-1.5">
            <Label for="crop-rainfall">Rainfall (mm)</Label>
            <Input
                id="crop-rainfall"
                name="rainfall_mm"
                type="number"
                inputmode="decimal"
                min="0"
                step="0.1"
                placeholder="0"
            />
            <InputError :message="errors.rainfall_mm" />
        </div>

        <div class="flex items-end pb-2">
            <label class="flex items-center gap-2 text-sm">
                <input
                    v-model="irrigationApplied"
                    type="checkbox"
                    class="size-4"
                />
                <span>Irrigation applied today</span>
            </label>
        </div>

        <div class="grid gap-1.5 sm:col-span-2">
            <Label for="crop-disease">Disease / deficiency observed</Label>
            <Input
                id="crop-disease"
                name="disease_observed"
                placeholder="e.g. Early blight on lower leaves, nitrogen yellowing"
            />
            <InputError :message="errors.disease_observed" />
        </div>

        <div class="grid gap-1.5">
            <Label for="crop-input-name"
                >Input applied (fertilizer/spray)</Label
            >
            <Input
                id="crop-input-name"
                name="input_applied_name"
                placeholder="e.g. NPK 15-15-15"
            />
            <InputError :message="errors.input_applied_name" />
        </div>

        <div class="grid grid-cols-2 gap-2">
            <div class="grid gap-1.5">
                <Label for="crop-input-qty">Quantity</Label>
                <Input
                    id="crop-input-qty"
                    name="input_applied_quantity"
                    type="number"
                    inputmode="decimal"
                    min="0"
                    step="0.01"
                />
                <InputError :message="errors.input_applied_quantity" />
            </div>
            <div class="grid gap-1.5">
                <Label for="crop-input-unit">Unit</Label>
                <Input
                    id="crop-input-unit"
                    name="input_applied_unit"
                    placeholder="bags / L"
                />
                <InputError :message="errors.input_applied_unit" />
            </div>
        </div>

        <div
            v-if="cropInventoryItems.length > 0"
            class="grid gap-1.5 sm:col-span-2"
        >
            <Label for="crop-stock-item">
                Deduct from input stock (optional)
            </Label>
            <select
                id="crop-stock-item"
                v-model="selectedInventoryId"
                name="inventory_item_id"
                class="h-11 rounded-md border bg-background px-3 text-base md:h-9 md:text-sm"
            >
                <option value="">Do not deduct stock</option>
                <option
                    v-for="item in cropInventoryItems"
                    :key="item.id"
                    :value="String(item.id)"
                >
                    {{ item.name }} ({{ item.currentQuantity }}
                    {{ item.unit }} left)
                </option>
            </select>
            <InputError :message="errors.inventory_item_id" />
        </div>

        <div class="grid gap-1.5 sm:col-span-2">
            <Label for="crop-notes">Field scouting notes</Label>
            <textarea
                id="crop-notes"
                name="notes"
                rows="2"
                placeholder="Canopy cover, weed status, moisture level..."
                class="rounded-md border bg-background px-3 py-2 text-base md:text-sm"
            />
            <InputError :message="errors.notes" />
        </div>

        <div class="grid gap-1.5 sm:col-span-2">
            <Label for="crop-evidence">Field photo (optional)</Label>
            <Input
                id="crop-evidence"
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
                Save crop log
            </Button>
        </div>
    </Form>
</template>
