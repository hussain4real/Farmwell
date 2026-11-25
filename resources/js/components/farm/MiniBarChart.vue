<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        labels: string[];
        values: number[];
        unit?: string;
        tone?: 'emerald' | 'amber' | 'cyan' | 'rose';
        height?: number;
    }>(),
    {
        unit: '',
        tone: 'emerald',
        height: 112,
    },
);

const maxVal = computed(() => Math.max(1, ...props.values));

const fillClass = computed(() => {
    switch (props.tone) {
        case 'amber':
            return 'fill-amber-500/85 hover:fill-amber-500';
        case 'cyan':
            return 'fill-cyan-600/85 hover:fill-cyan-600';
        case 'rose':
            return 'fill-rose-500/85 hover:fill-rose-500';
        default:
            return 'fill-primary/85 hover:fill-primary';
    }
});
</script>

<template>
    <div class="w-full">
        <div
            v-if="values.length === 0"
            class="flex h-24 items-center justify-center text-xs text-muted-foreground"
        >
            No logged trend data yet.
        </div>
        <div v-else class="space-y-1.5">
            <svg
                :viewBox="`0 0 ${Math.max(120, values.length * 24)} ${height}`"
                class="w-full overflow-visible"
                :style="{ height: `${height}px` }"
                role="img"
            >
                <g v-for="(val, idx) in values" :key="idx">
                    <rect
                        :x="idx * 24 + 4"
                        :y="
                            height - Math.max(4, (val / maxVal) * (height - 10))
                        "
                        width="16"
                        :height="Math.max(4, (val / maxVal) * (height - 10))"
                        rx="3"
                        class="transition-all"
                        :class="fillClass"
                    >
                        <title>
                            {{ labels[idx] }}: {{ val
                            }}{{ unit ? ` ${unit}` : '' }}
                        </title>
                    </rect>
                </g>
            </svg>
            <div
                class="flex items-center justify-between text-[10px] text-muted-foreground tabular-nums"
            >
                <span>{{ labels[0] }}</span>
                <span v-if="labels.length > 1">{{
                    labels[labels.length - 1]
                }}</span>
            </div>
        </div>
    </div>
</template>
