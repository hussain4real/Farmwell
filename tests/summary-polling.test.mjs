import assert from 'node:assert/strict';
import test from 'node:test';
import { router } from '@inertiajs/vue3';
import { createRenderer, defineComponent, h, nextTick, ref } from 'vue';
import { useQueuedPolling } from '../resources/js/composables/useQueuedPolling.ts';

for (const prop of ['summaries', 'exports', 'statements']) {
    test(prop + ' polling runs only while work is queued and is cleaned up', async () => {
        const events = [];
        const originalPoll = router.poll;
        router.poll = (interval, requestOptions, options) => {
            assert.equal(interval, 5000);
            assert.deepEqual(requestOptions.only, [prop]);
            assert.equal(options.autoStart, false);
            assert.equal(options.mode, 'rest');

            return {
                start: () => events.push('start'),
                stop: () => events.push('stop'),
                destroy: () => events.push('destroy'),
            };
        };

        try {
            const queued = ref(false);
            const renderer = createRenderer({
                createElement: () => ({}),
                createText: () => ({}),
                createComment: () => ({}),
                setText: () => {},
                setElementText: () => {},
                insert: () => {},
                remove: () => {},
                parentNode: () => null,
                nextSibling: () => null,
                patchProp: () => {},
            });
            const app = renderer.createApp(defineComponent({
                setup() {
                    useQueuedPolling(prop, () => queued.value);

                    return () => h('div');
                },
            }));
            app.mount({});
            assert.deepEqual(events, ['stop']);

            queued.value = true;
            await nextTick();
            assert.deepEqual(events, ['stop', 'start']);

            queued.value = false;
            await nextTick();
            assert.deepEqual(events, ['stop', 'start', 'stop']);
            app.unmount();
            assert.deepEqual(events, ['stop', 'start', 'stop', 'destroy']);
        } finally {
            router.poll = originalPoll;
        }
    });
}
