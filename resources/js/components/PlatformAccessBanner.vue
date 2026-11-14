<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { destroy } from '@/routes/platform/access';

const page = usePage();
const exiting = ref(false);
function exitAccess() {
    exiting.value = true;
    router.delete(destroy().url, {
        onFinish: () => {
            exiting.value = false;
        },
    });
}
</script>

<template>
    <aside
        v-if="page.props.platformAccess"
        aria-label="Super Admin access"
        class="flex flex-wrap items-center justify-between gap-3 border-b border-amber-300 bg-amber-50 px-4 py-3 text-amber-950 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100"
    >
        <div class="min-w-0 text-sm">
            <p class="font-semibold">
                Super Admin · {{ page.props.platformAccess.team_name }} · Read
                only
            </p>
            <p class="mt-1 break-words">
                {{ page.props.platformAccess.reason }}
            </p>
            <p class="mt-1 text-xs">
                Every page visit and download is audited. Session expires
                {{
                    new Date(
                        page.props.platformAccess.expires_at * 1000,
                    ).toLocaleTimeString()
                }}.
            </p>
        </div>
        <Button variant="outline" :disabled="exiting" @click="exitAccess"
            >Exit admin session</Button
        >
    </aside>
</template>
