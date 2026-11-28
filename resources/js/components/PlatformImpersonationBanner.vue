<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { UserCheck } from 'lucide-vue-next';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import { destroy } from '@/routes/platform/impersonate';

const page = usePage();
const impersonation = computed(() => page.props.platformImpersonation);
const exitImpersonation = () =>
    router.delete(destroy().url, { preserveState: false });
</script>

<template>
    <div
        v-if="impersonation"
        role="status"
        class="border-b border-sky-300 bg-sky-50 px-4 py-2.5 text-sm text-sky-950 dark:border-sky-800 dark:bg-sky-950/60 dark:text-sky-100"
    >
        <div
            class="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-2"
        >
            <div class="flex min-w-0 items-center gap-2">
                <UserCheck
                    class="size-4 shrink-0 text-sky-700 dark:text-sky-300"
                />
                <span class="truncate">
                    <strong
                        >Impersonating
                        {{ impersonation.target_user_name }}</strong
                    >
                    <span
                        v-if="impersonation.target_user_email"
                        class="opacity-80"
                    >
                        ({{ impersonation.target_user_email }})
                    </span>
                    <span class="hidden opacity-80 sm:inline">
                        · Started by {{ impersonation.impersonator_name }} ·
                        {{ impersonation.reason }}
                    </span>
                </span>
            </div>
            <Button
                variant="outline"
                size="sm"
                class="shrink-0 border-sky-400 bg-white text-sky-950 hover:bg-sky-100 dark:border-sky-700 dark:bg-sky-900 dark:text-sky-50 dark:hover:bg-sky-800"
                @click="exitImpersonation"
            >
                Exit impersonation
            </Button>
        </div>
    </div>
</template>
