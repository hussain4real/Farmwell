<script setup lang="ts">
type Props = {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
};

withDefaults(defineProps<Props>(), {
    variant: 'default',
});
</script>

<template>
    <header
        :class="
            variant === 'small'
                ? ''
                : 'space-y-2 rounded-2xl bg-[#214d40] px-6 py-6 text-white sm:px-8 sm:py-8 dark:bg-[#194438]'
        "
    >
        <component
            :is="variant === 'small' ? 'h2' : 'h1'"
            :class="
                variant === 'small'
                    ? 'mb-0.5 text-base font-semibold'
                    : 'text-2xl font-semibold tracking-tight sm:text-3xl'
            "
        >
            {{ title }}
        </component>
        <p
            v-if="description"
            :class="
                variant === 'small'
                    ? 'text-sm text-muted-foreground'
                    : 'max-w-3xl text-sm leading-6 text-[#d6e8de]'
            "
        >
            {{ description }}
        </p>
    </header>
</template>
