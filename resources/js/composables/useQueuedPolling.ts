import { usePoll } from '@inertiajs/vue3';
import { watch } from 'vue';

export function useQueuedPolling(prop: string, hasQueued: () => boolean): void {
    const { start, stop } = usePoll(
        5000,
        { only: [prop] },
        { autoStart: false, mode: 'rest' },
    );

    watch(
        hasQueued,
        (pending) => {
            if (pending) {
                start();
            } else {
                stop();
            }
        },
        { immediate: true },
    );
}
