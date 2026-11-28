<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { RefreshCw, Trash2, WifiOff } from 'lucide-vue-next';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import {
    OFFLINE_QUEUE_EVENT,
    getOfflineLogs,
    removeOfflineLog,
    syncOfflineLogs,
} from '@/lib/offlineLogQueue';
import type { QueuedProductionLog } from '@/lib/offlineLogQueue';

const isOnline = ref(true);
const queuedLogs = ref<QueuedProductionLog[]>([]);
const syncing = ref(false);

const refreshState = () => {
    isOnline.value = typeof navigator === 'undefined' ? true : navigator.onLine;
    queuedLogs.value = getOfflineLogs();
};

const handleSync = async (silentIfEmpty = false) => {
    if (syncing.value || !isOnline.value) {
        return;
    }

    if (getOfflineLogs().length === 0) {
        if (!silentIfEmpty) {
            refreshState();
        }

        return;
    }

    syncing.value = true;

    try {
        const result = await syncOfflineLogs();
        refreshState();

        if (result.synced > 0) {
            toast.success(
                `Synced ${result.synced} offline farm log${result.synced === 1 ? '' : 's'}.`,
            );
            router.reload();
        }

        if (result.failed > 0) {
            toast.error(
                `${result.failed} queued log${result.failed === 1 ? '' : 's'} need attention before syncing.`,
            );
        }
    } finally {
        syncing.value = false;
    }
};

const handleOnline = () => {
    refreshState();
    void handleSync(true);
};

onMounted(() => {
    refreshState();
    window.addEventListener('online', handleOnline);
    window.addEventListener('offline', refreshState);
    window.addEventListener(OFFLINE_QUEUE_EVENT, refreshState);

    if (isOnline.value && queuedLogs.value.length > 0) {
        void handleSync(true);
    }
});

onBeforeUnmount(() => {
    window.removeEventListener('online', handleOnline);
    window.removeEventListener('offline', refreshState);
    window.removeEventListener(OFFLINE_QUEUE_EVENT, refreshState);
});
</script>

<template>
    <div
        v-if="!isOnline || queuedLogs.length > 0"
        class="border-b px-4 py-2.5 text-xs sm:px-6"
        :class="
            !isOnline
                ? 'border-amber-300 bg-amber-50 text-amber-950 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200'
                : 'border-emerald-200 bg-emerald-50/80 text-emerald-950 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200'
        "
    >
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <WifiOff v-if="!isOnline" class="size-4 shrink-0" />
                <RefreshCw
                    v-else
                    class="size-4 shrink-0"
                    :class="syncing ? 'animate-spin' : ''"
                />
                <span class="font-medium">
                    <template v-if="!isOnline">
                        Offline mode — daily logs you record will queue on this
                        device
                    </template>
                    <template v-else>
                        Back online — {{ queuedLogs.length }} queued log{{
                            queuedLogs.length === 1 ? '' : 's'
                        }}
                        ready to sync
                    </template>
                </span>
            </div>

            <div class="flex items-center gap-2">
                <Button
                    v-if="isOnline && queuedLogs.length > 0"
                    type="button"
                    size="sm"
                    variant="secondary"
                    class="h-7 px-2.5 text-xs"
                    :disabled="syncing"
                    @click="handleSync(false)"
                >
                    Sync now ({{ queuedLogs.length }})
                </Button>
            </div>
        </div>

        <ul
            v-if="queuedLogs.some((item) => item.lastError)"
            class="mt-2 space-y-1"
        >
            <li
                v-for="item in queuedLogs.filter((entry) => entry.lastError)"
                :key="item.id"
                class="flex items-center justify-between gap-2 rounded-md bg-background/70 px-2.5 py-1 text-[11px] text-rose-700 dark:text-rose-300"
            >
                <span>{{ item.label }}: {{ item.lastError }}</span>
                <button
                    type="button"
                    class="inline-flex items-center gap-1 font-medium underline"
                    @click="removeOfflineLog(item.id)"
                >
                    <Trash2 class="size-3" />
                    Dismiss
                </button>
            </li>
        </ul>
    </div>
</template>
