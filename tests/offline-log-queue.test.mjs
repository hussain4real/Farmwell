import assert from 'node:assert/strict';
import test from 'node:test';
import {
    OFFLINE_LOG_QUEUE_KEY,
    clearOfflineLogs,
    enqueueOfflineLog,
    getOfflineLogs,
    readXsrfToken,
    removeOfflineLog,
    syncOfflineLogs,
} from '../resources/js/lib/offlineLogQueue.ts';

function mockBrowserEnvironment() {
    const store = new Map();
    const events = [];
    const target = new EventTarget();
    target.localStorage = {
        getItem: (key) => store.get(key) ?? null,
        setItem: (key, val) => store.set(key, String(val)),
        removeItem: (key) => store.delete(key),
    };
    const originalDispatch = target.dispatchEvent.bind(target);
    target.dispatchEvent = (event) => {
        events.push(event.type);

        return originalDispatch(event);
    };
    globalThis.window = target;
    globalThis.document = {
        cookie: 'other=1; XSRF-TOKEN=abc%3D123; theme=system',
    };

    return { store, events };
}

test('offline log queue safely returns empty during SSR', () => {
    delete globalThis.window;
    delete globalThis.document;
    assert.deepEqual(getOfflineLogs(), []);
    assert.equal(readXsrfToken(), '');
});

test('reads and decodes XSRF token from cookies', () => {
    assert.equal(readXsrfToken('foo=bar; XSRF-TOKEN=hello%2Bworld%3D1'), 'hello+world=1');
    assert.equal(readXsrfToken('foo=bar'), '');
});

test('enqueues, removes, and clears offline logs in localStorage', () => {
    const { store, events } = mockBrowserEnvironment();

    const first = enqueueOfflineLog({
        url: '/team/farms/1/production-cycles/1/production-logs',
        domain: 'poultry',
        label: 'Poultry daily log',
        payload: { domain: 'poultry', mortality_count: '2' },
    });
    const second = enqueueOfflineLog({
        url: '/team/farms/2/production-cycles/2/production-logs',
        domain: 'crop',
        label: 'Crop daily log',
        payload: { domain: 'crop', rainfall_mm: '14' },
    });

    assert.equal(getOfflineLogs().length, 2);
    assert.ok(store.get(OFFLINE_LOG_QUEUE_KEY));
    assert.deepEqual(events, ['farmwell:offline-queue-changed', 'farmwell:offline-queue-changed']);

    removeOfflineLog(first.id);
    assert.deepEqual(
        getOfflineLogs().map((item) => item.id),
        [second.id],
    );

    clearOfflineLogs();
    assert.deepEqual(getOfflineLogs(), []);
});

test('syncOfflineLogs posts queued entries, preserves validation errors, and halts on network failure', async () => {
    mockBrowserEnvironment();

    enqueueOfflineLog({
        url: '/team/farms/1/production-cycles/1/production-logs',
        domain: 'poultry',
        label: 'Valid log',
        payload: { domain: 'poultry', mortality_count: '1' },
    });
    enqueueOfflineLog({
        url: '/team/farms/1/production-cycles/1/production-logs',
        domain: 'poultry',
        label: 'Invalid log',
        payload: { domain: 'poultry', log_date: '' },
    });
    enqueueOfflineLog({
        url: '/team/farms/2/production-cycles/2/production-logs',
        domain: 'crop',
        label: 'After network drop',
        payload: { domain: 'crop', rainfall_mm: '5' },
    });

    let call = 0;
    const result = await syncOfflineLogs(async (_url, options) => {
        call++;
        assert.equal(options.headers['X-XSRF-TOKEN'], 'abc=123');

        if (call === 1) {
            return new Response(JSON.stringify({ id: 10 }), { status: 201 });
        }

        if (call === 2) {
            return new Response(
                JSON.stringify({ errors: { log_date: ['The log date field is required.'] } }),
                { status: 422 },
            );
        }

        throw new Error('offline');
    });

    assert.deepEqual(result, { synced: 1, failed: 1, remaining: 2 });
    const remaining = getOfflineLogs();
    assert.equal(remaining[0].label, 'Invalid log');
    assert.equal(remaining[0].lastError, 'The log date field is required.');
    assert.equal(remaining[1].label, 'After network drop');
});
