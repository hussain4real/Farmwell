<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type { FarmOption } from '@/types';
import CropDailyLogForm from './CropDailyLogForm.vue';
import FishDailyLogForm from './FishDailyLogForm.vue';
import PoultryDailyLogForm from './PoultryDailyLogForm.vue';
import type {
    LogInventoryOption,
    LogUnitOption,
} from './PoultryDailyLogForm.vue';
import SlideOverForm from './SlideOverForm.vue';

export type QuickLogFarm = {
    id: number;
    name: string;
    farmType: string;
    domain: string;
    productionUnits: LogUnitOption[];
    activeCycles: {
        id: number;
        name: string;
        currentGrowthStage: string | null;
    }[];
};

const props = defineProps<{
    open: boolean;
    teamSlug: string;
    farms: QuickLogFarm[];
    inventoryItems: LogInventoryOption[];
    growthStages: FarmOption[];
    pestPressures: FarmOption[];
    initialFarmId?: number | null;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();

const selectedFarmId = ref(
    props.initialFarmId
        ? String(props.initialFarmId)
        : props.farms[0]?.id
          ? String(props.farms[0].id)
          : '',
);

const selectedFarm = computed(() =>
    props.farms.find((farm) => String(farm.id) === selectedFarmId.value),
);

const selectedCycleId = ref(
    selectedFarm.value?.activeCycles[0]?.id
        ? String(selectedFarm.value.activeCycles[0].id)
        : '',
);

watch(
    () => props.initialFarmId,
    (id) => {
        if (id) {
            selectedFarmId.value = String(id);
        }
    },
);

watch(selectedFarm, (farm) => {
    selectedCycleId.value = farm?.activeCycles[0]?.id
        ? String(farm.activeCycles[0].id)
        : '';
});

const selectedCycle = computed(() =>
    selectedFarm.value?.activeCycles.find(
        (c) => String(c.id) === selectedCycleId.value,
    ),
);

const farmInventory = computed(() =>
    props.inventoryItems.filter(
        (item) =>
            item.farmId === null || item.farmId === selectedFarm.value?.id,
    ),
);
</script>

<template>
    <SlideOverForm
        :open="open"
        title="Quick daily farm log"
        description="Automatically adapts to your poultry, fish, or crop operation"
        wide
        @update:open="emit('update:open', $event)"
    >
        <div v-if="farms.length > 0" class="space-y-5">
            <div
                class="grid gap-3 rounded-xl border bg-muted/40 p-3.5 sm:grid-cols-2"
            >
                <div class="grid gap-1.5">
                    <label class="text-xs font-medium text-muted-foreground">
                        Farm
                    </label>
                    <select
                        v-model="selectedFarmId"
                        class="h-11 rounded-md border bg-background px-3 text-base md:h-9 md:text-sm"
                    >
                        <option
                            v-for="farm in farms"
                            :key="farm.id"
                            :value="String(farm.id)"
                        >
                            {{ farm.name }}
                        </option>
                    </select>
                </div>
                <div class="grid gap-1.5">
                    <label class="text-xs font-medium text-muted-foreground">
                        Active cycle / batch
                    </label>
                    <select
                        v-model="selectedCycleId"
                        class="h-11 rounded-md border bg-background px-3 text-base md:h-9 md:text-sm"
                    >
                        <option
                            v-for="cycle in selectedFarm?.activeCycles ?? []"
                            :key="cycle.id"
                            :value="String(cycle.id)"
                        >
                            {{ cycle.name }}
                        </option>
                    </select>
                </div>
            </div>

            <template v-if="selectedFarm && selectedCycle">
                <PoultryDailyLogForm
                    v-if="selectedFarm.domain === 'poultry'"
                    :team-slug="teamSlug"
                    :farm-id="selectedFarm.id"
                    :cycle-id="selectedCycle.id"
                    :units="selectedFarm.productionUnits"
                    :inventory-items="farmInventory"
                    @saved="emit('update:open', false)"
                />
                <FishDailyLogForm
                    v-else-if="selectedFarm.domain === 'aquaculture'"
                    :team-slug="teamSlug"
                    :farm-id="selectedFarm.id"
                    :cycle-id="selectedCycle.id"
                    :units="selectedFarm.productionUnits"
                    :inventory-items="farmInventory"
                    @saved="emit('update:open', false)"
                />
                <CropDailyLogForm
                    v-else
                    :team-slug="teamSlug"
                    :farm-id="selectedFarm.id"
                    :cycle-id="selectedCycle.id"
                    :units="selectedFarm.productionUnits"
                    :inventory-items="farmInventory"
                    :growth-stages="growthStages"
                    :pest-pressures="pestPressures"
                    :current-growth-stage="selectedCycle.currentGrowthStage"
                    @saved="emit('update:open', false)"
                />
            </template>
            <p v-else class="text-sm text-muted-foreground">
                This farm needs an active or planned production cycle before
                recording daily logs.
            </p>
        </div>
        <p v-else class="text-sm text-muted-foreground">
            Onboard a farm first to start recording daily logs.
        </p>
    </SlideOverForm>
</template>
