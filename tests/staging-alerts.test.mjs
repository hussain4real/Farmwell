import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import vm from 'node:vm';

const source = (
    await readFile(new URL('../cloudflare-alerts.js', import.meta.url), 'utf8')
)
    .replace("import { DurableObject } from 'cloudflare:workers';", '')
    .replace('export class', 'class')
    .replace('export default', 'const worker =');

test('staging alerts notify on failure, coalesce errors, recover and retry undelivered notifications', async () => {
    const data = new Map();
    const messages = [];
    let status = 200;
    let latest = '1';
    let mailFails = false;
    const { AlertState, worker } = vm.runInNewContext(
        source + '\n;({AlertState, worker})',
        {
            URL,
            AbortSignal,
            DurableObject: class {},
            fetch: async () =>
                Response.redirect(
                    'https://team.cloudflareaccess.com/login',
                    302,
                ),
        },
    );
    const state = new AlertState();
    state.ctx = {
        storage: { get: (k) => data.get(k), put: (k, v) => data.set(k, v) },
    };
    const env = (state.env = {
        INITIAL_FAILED_JOB_ID: '1',
        ALERT_TO: 'responder@example.test',
        DELIVERY_TEST: 'smoke',
        APP: { fetch: async () => new Response('Application up', { status }) },
        QUEUE: {
            getByName: () => ({ failedJobWatermark: async () => latest }),
        },
        STATE: { getByName: () => state },
        MAIL: {
            send: async (m) => {
                if (mailFails) {
                    throw new Error('delivery failed');
                }

                messages.push(m);
            },
        },
    });
    await Promise.all([worker.scheduled({}, env), worker.scheduled({}, env)]);
    status = 503;
    latest = '3';
    await worker.tail(
        [
            { outcome: 'ok', event: { response: { status: 500 } } },
            { outcome: 'exception' },
            { outcome: 'ok', event: { response: { status: 404 } } },
        ],
        env,
    );
    mailFails = true;
    await assert.rejects(worker.scheduled({}, env), /delivery failed/);
    mailFails = false;
    await Promise.all([worker.scheduled({}, env), worker.scheduled({}, env)]);
    await worker.scheduled({}, env);
    status = 200;
    await worker.scheduled({}, env);
    assert.deepEqual(
        messages.map((m) => m.subject.replace('[Farmwell staging] ', '')),
        [
            'Delivery test smoke',
            'Application uptime failed',
            'Failed queue job',
            'Application error',
            'Application uptime recovered',
        ],
    );
    assert.match(messages[3].text, /2 failed invocation/);
    assert.ok(messages.every((m) => m.to === env.ALERT_TO));
});
