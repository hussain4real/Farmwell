<script setup lang="ts">
import { Bird, Fish, Layers, Sprout, Tractor } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    farmType: string;
    label?: string;
}>();

const config = computed(() => {
    switch (props.farmType) {
        case 'poultry':
            return {
                icon: Bird,
                text: props.label ?? 'Poultry',
                classes:
                    'bg-amber-50 text-amber-800 border-amber-200/80 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800/50',
            };
        case 'aquaculture':
            return {
                icon: Fish,
                text: props.label ?? 'Aquaculture',
                classes:
                    'bg-cyan-50 text-cyan-800 border-cyan-200/80 dark:bg-cyan-950/60 dark:text-cyan-300 dark:border-cyan-800/50',
            };
        case 'crop':
        case 'plantation':
            return {
                icon: Sprout,
                text: props.label ?? 'Crop',
                classes:
                    'bg-emerald-50 text-emerald-800 border-emerald-200/80 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800/50',
            };
        case 'mixed':
            return {
                icon: Layers,
                text: props.label ?? 'Mixed',
                classes:
                    'bg-lime-50 text-lime-800 border-lime-200/80 dark:bg-lime-950/60 dark:text-lime-300 dark:border-lime-800/50',
            };
        default:
            return {
                icon: Tractor,
                text: props.label ?? 'Farm',
                classes: 'bg-secondary text-secondary-foreground border-border',
            };
    }
});
</script>

<template>
    <span
        class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 text-xs font-medium"
        :class="config.classes"
    >
        <component :is="config.icon" class="size-3.5 shrink-0" />
        <span>{{ config.text }}</span>
    </span>
</template>
