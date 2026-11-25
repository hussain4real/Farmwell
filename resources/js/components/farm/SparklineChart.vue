<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        values: (number | null)[];
        tone?: 'emerald' | 'cyan' | 'amber';
        height?: number;
    }>(),
    {
        tone: 'cyan',
        height: 48,
    },
);

const cleanPoints = computed(() =>
    props.values
        .map((v, i) => ({ v, i }))
        .filter((item): item is { v: number; i: number } => item.v !== null),
);

const pathD = computed(() => {
    const pts = cleanPoints.value;

    if (pts.length < 2) {
        return '';
    }

    const min = Math.min(...pts.map((p) => p.v));
    const max = Math.max(...pts.map((p) => p.v));
    const span = max - min || 1;
    const width = 160;
    const h = props.height - 8;

    return pts
        .map((p, idx) => {
            const x = (idx / (pts.length - 1)) * width;
            const y = h - ((p.v - min) / span) * (h - 6) + 4;

            return `${idx === 0 ? 'M' : 'L'} ${x.toFixed(1)} ${y.toFixed(1)}`;
        })
        .join(' ');
});

const strokeClass = computed(() => {
    switch (props.tone) {
        case 'amber':
            return 'stroke-amber-500';
        case 'emerald':
            return 'stroke-emerald-600 dark:stroke-emerald-400';
        default:
            return 'stroke-cyan-600 dark:stroke-cyan-400';
    }
});
</script>

<template>
    <div class="w-full">
        <svg
            v-if="pathD"
            :viewBox="`0 0 160 ${height}`"
            class="w-full overflow-visible"
            :style="{ height: `${height}px` }"
        >
            <path
                :d="pathD"
                fill="none"
                stroke-width="2.25"
                stroke-linecap="round"
                stroke-linejoin="round"
                :class="strokeClass"
            />
        </svg>
        <p v-else class="text-xs text-muted-foreground">
            Record at least 2 readings to plot trend.
        </p>
    </div>
</template>
