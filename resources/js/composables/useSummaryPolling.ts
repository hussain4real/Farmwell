import { usePoll } from '@inertiajs/vue3';
import { watch } from 'vue';

export function useSummaryPolling(hasQueuedSummaries: () => boolean): void {
    const { start, stop } = usePoll(
        5000,
        { only: ['summaries'] },
        { autoStart: false, mode: 'rest' },
    );

    watch(
        hasQueuedSummaries,
        (hasQueued) => {
            if (hasQueued) {
                start();
            } else {
                stop();
            }
        },
        { immediate: true },
    );
}
