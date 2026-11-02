<script setup lang="ts">
import { Sprout } from 'lucide-vue-next';
</script>

<template>
    <div
        class="flex aspect-square size-9 items-center justify-center rounded-lg bg-[#214d40] text-white"
    >
        <Sprout class="size-5" />
    </div>
    <div class="ml-2 grid flex-1 text-left">
        <span
            class="truncate text-base leading-tight font-semibold tracking-tight"
            >Farmwell</span
        >
        <span class="truncate text-xs text-muted-foreground"
            >Farm management</span
        >
    </div>
</template>
