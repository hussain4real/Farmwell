<script setup lang="ts">
import type { Component } from 'vue';

defineProps<{
    title: string;
    description: string;
    icon?: Component;
}>();
</script>

<template>
    <div
        class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-card/60 px-6 py-12 text-center"
    >
        <div
            v-if="icon"
            class="mb-4 flex size-12 items-center justify-center rounded-full bg-accent text-accent-foreground"
        >
            <component :is="icon" class="size-6" />
        </div>
        <h3 class="text-base font-semibold tracking-tight">{{ title }}</h3>
        <p class="mt-1.5 max-w-md text-sm text-muted-foreground">
            {{ description }}
        </p>
        <div
            v-if="$slots.action"
            class="mt-5 flex flex-wrap justify-center gap-2"
        >
            <slot name="action" />
        </div>
    </div>
</template>
