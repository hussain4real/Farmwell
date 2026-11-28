<script setup lang="ts">
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';

withDefaults(
    defineProps<{
        open: boolean;
        title: string;
        description?: string;
        wide?: boolean;
    }>(),
    {
        wide: false,
    },
);

const emit = defineEmits<{
    'update:open': [value: boolean];
}>();
</script>

<template>
    <Sheet :open="open" @update:open="emit('update:open', $event)">
        <SheetContent
            side="right"
            :class="[
                'flex h-[100dvh] w-full max-w-[100vw] flex-col overflow-hidden p-0 data-[state=closed]:duration-150 data-[state=open]:duration-200',
                wide ? 'sm:max-w-xl' : 'sm:max-w-lg',
            ]"
        >
            <SheetHeader
                class="border-b px-4 pt-[calc(1rem+env(safe-area-inset-top))] pr-12 pb-3.5 sm:px-6 sm:py-4 sm:pr-12"
            >
                <SheetTitle>{{ title }}</SheetTitle>
                <SheetDescription v-if="description">
                    {{ description }}
                </SheetDescription>
            </SheetHeader>
            <div
                class="flex-1 overflow-y-auto overscroll-contain px-4 py-4 pb-[calc(1.5rem+env(safe-area-inset-bottom))] sm:px-6 sm:py-5"
            >
                <slot />
            </div>
        </SheetContent>
    </Sheet>
</template>
