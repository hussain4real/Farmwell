<script setup lang="ts">
import type { Component } from 'vue';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        label: string;
        value: string | number;
        subtext?: string;
        icon?: Component;
        tone?: 'default' | 'emerald' | 'amber' | 'cyan' | 'rose';
        progress?: number | null;
    }>(),
    {
        tone: 'default',
        progress: null,
    },
);

const iconClasses = computed(() => {
    switch (props.tone) {
        case 'emerald':
            return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/70 dark:text-emerald-300';
        case 'amber':
            return 'bg-amber-50 text-amber-700 dark:bg-amber-950/70 dark:text-amber-300';
        case 'cyan':
            return 'bg-cyan-50 text-cyan-700 dark:bg-cyan-950/70 dark:text-cyan-300';
        case 'rose':
            return 'bg-rose-50 text-rose-700 dark:bg-rose-950/70 dark:text-rose-300';
        default:
            return 'bg-muted text-muted-foreground';
    }
});

const barClasses = computed(() => {
    switch (props.tone) {
        case 'amber':
            return 'bg-amber-500';
        case 'cyan':
            return 'bg-cyan-600';
        case 'rose':
            return 'bg-rose-500';
        default:
            return 'bg-primary';
    }
});
</script>

<template>
    <div
        class="flex min-w-0 flex-col justify-between rounded-xl border bg-card p-4 shadow-xs transition-shadow hover:shadow-sm"
    >
        <div class="flex items-start justify-between gap-3">
            <span class="text-xs font-medium text-muted-foreground">
                {{ label }}
            </span>
            <span
                v-if="icon"
                class="flex size-8 shrink-0 items-center justify-center rounded-lg"
                :class="iconClasses"
            >
                <component :is="icon" class="size-4" />
            </span>
        </div>
        <div class="mt-3">
            <p
                class="text-xl font-semibold tracking-tight break-words tabular-nums sm:text-2xl"
            >
                {{ value }}
            </p>
            <p v-if="subtext" class="mt-1 text-xs text-muted-foreground">
                {{ subtext }}
            </p>
        </div>
        <div
            v-if="progress !== null"
            class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-muted"
        >
            <div
                class="h-full rounded-full transition-all"
                :class="barClasses"
                :style="{ width: `${Math.min(100, Math.max(0, progress))}%` }"
            />
        </div>
    </div>
</template>
