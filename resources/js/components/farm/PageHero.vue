<script setup lang="ts">
defineProps<{
    eyebrow?: string;
    title: string;
    description?: string;
}>();
</script>

<template>
    <section
        class="overflow-hidden rounded-2xl border border-[#245c4b]/30 bg-gradient-to-br from-[#1b4d3e] via-[#1f5948] to-[#163f33] text-white shadow-sm dark:border-emerald-900/50 dark:from-[#13352b] dark:via-[#163e32] dark:to-[#0e2a21]"
    >
        <div
            class="flex flex-col justify-between gap-4 px-4 py-5 sm:px-7 sm:py-7 lg:flex-row lg:items-end"
        >
            <div class="max-w-2xl space-y-2">
                <p
                    v-if="eyebrow"
                    class="text-xs font-semibold tracking-wider text-emerald-200/90 uppercase"
                >
                    {{ eyebrow }}
                </p>
                <div class="flex flex-wrap items-center gap-3">
                    <h1
                        class="text-2xl font-semibold tracking-tight sm:text-3xl"
                    >
                        {{ title }}
                    </h1>
                    <slot name="badges" />
                </div>
                <p
                    v-if="description"
                    class="max-w-xl text-sm leading-relaxed text-emerald-100/85"
                >
                    {{ description }}
                </p>
            </div>
            <div
                v-if="$slots.actions"
                class="flex flex-wrap items-center gap-2 [&>*]:flex-1 sm:[&>*]:flex-initial"
            >
                <slot name="actions" />
            </div>
        </div>
        <div
            v-if="$slots.metrics"
            class="grid grid-cols-2 border-t border-white/15 bg-black/10 sm:grid-cols-4"
        >
            <slot name="metrics" />
        </div>
    </section>
</template>
