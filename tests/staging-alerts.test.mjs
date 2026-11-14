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

test('production monitoring keeps credentials on the private service and waits for delivery approval', async () => {
    const data = new Map();
    const messages = [];
    const observations = [];
    let backupDate = new Date();
    let gatewayStatus = 403;
    const { AlertState, worker } = vm.runInNewContext(source + '\n;({AlertState, worker})', {
        URL, AbortSignal, DurableObject: class {},
        console: { info: (label, checks) => observations.push({ label, checks: JSON.parse(JSON.stringify(checks)) }) },
        fetch: async (url, options) => {
            assert.equal(url, 'https://preview.test/up');
            assert.equal(options.headers, undefined);

            return new Response('Production rehearsal is private', { status: gatewayStatus });
        },
    });
    const state = new AlertState();
    state.ctx = { storage: { get: k => data.get(k), put: (k, v) => data.set(k, v) } };
    const env = state.env = {
        INITIAL_FAILED_JOB_ID: '0', HEALTH_URL: 'https://preview.test/up', GATEWAY_MODE: 'private',
        MONITOR_NAME: 'production', ALERT_LABEL: 'Farmwell production', MAIL_FROM: 'noreply@mail.example.test',
        ALERT_TO: 'responder@example.test', ALERT_DELIVERY_ENABLED: 'false', REHEARSAL_TOKEN: 'private-test', DELIVERY_TEST: 'production-smoke',
        APP: { fetch: async (_url, options) => {
            assert.equal(options.headers['x-farmwell-rehearsal'], 'private-test');

            return new Response('Application up');
        } },
        QUEUE: { getByName: () => ({ failedJobWatermark: async () => '0' }) },
        BACKUPS: { list: async ({cursor}) => cursor ? { objects: [{key: 'Farmwell/new.zip', uploaded: backupDate}], truncated: false } : { objects: [], truncated: true, cursor: 'page2' } },
        STATE: { getByName: name => {
            assert.equal(name, 'production');

            return state;
        } },
        MAIL: { send: async message => messages.push(message) },
    };
    await worker.scheduled({}, env);
    assert.equal(messages.length, 0);
    assert.equal(data.has('lastTest'), false);
    assert.deepEqual(observations[0], { label: 'Farmwell production checks', checks: {gateway: true, application: true, failedJobs: true, backup: true} });
    env.ALERT_DELIVERY_ENABLED = 'true';
    await worker.scheduled({}, env);
    assert.equal(messages[0].subject, '[Farmwell production] Delivery test production-smoke');
    assert.equal(messages[0].from, 'noreply@mail.example.test');
    backupDate = new Date(Date.now() - 49 * 60 * 60 * 1000);
    gatewayStatus = 200;
    await worker.scheduled({}, env);
    assert.deepEqual(messages.slice(1).map(m => m.subject), ['[Farmwell production] Public access gateway failed', '[Farmwell production] Backup freshness failed']);
    backupDate = new Date();
    gatewayStatus = 403;
    await worker.scheduled({}, env);
    assert.equal(messages.at(-1).subject, '[Farmwell production] Backup freshness recovered');
});


test('production cutover configuration isolates live storage and freezes the source farm', async () => {
    const parse = async name => JSON.parse((await readFile(new URL('../' + name, import.meta.url), 'utf8')).replace(/,\s*([}\]])/g, '$1'));
    const app = await parse('wrangler.production.jsonc');
    const alerts = await parse('wrangler.production-alerts.jsonc');
    assert.equal(app.name, 'farmwell-production');
    assert.equal(app.vars.REHEARSAL_MODE, 'false');
    assert.equal(app.vars.PHONE_PILOT_FULL_WORKSPACE, 'true');
    assert.equal(app.vars.AWS_BUCKET, 'farmwell-production-live-private');
    assert.equal(app.vars.PHONE_PILOT_HOST, 'app.farmwell.app');
    assert.equal(app.vars.REDIRECT_HOST, 'pilot.farmwell.app');
    assert.deepEqual(app.triggers.crons, ['* * * * *']);
    assert.deepEqual(app.routes.map(route => route.pattern), ['app.farmwell.app', 'pilot.farmwell.app']);
    assert.deepEqual(app.tail_consumers, [{service: alerts.name}]);
    assert.equal(alerts.vars.ALERT_DELIVERY_ENABLED, 'true');
    assert.equal(alerts.vars.GATEWAY_MODE, 'public');
    assert.equal(alerts.services[0].service, app.name);
    assert.equal(alerts.r2_buckets[0].bucket_name, 'farmwell-production-live-backups');
    const staging = await parse('wrangler.jsonc');
    assert.equal(staging.vars.FROZEN_TEAM_SLUG, 'mdsxclusive-farms');
    assert.equal(staging.vars.FROZEN_TEAM_ID, '4');
    assert.equal(staging.vars.MOVED_TEAM_ORIGIN, app.vars.APP_URL);
    assert.deepEqual(staging.routes.map(route => route.pattern), ['staging.farmwell.app']);
});

test('public production health requires the Laravel health response', async () => {
    let body = 'Application up';
    let status = 200;
    const State = vm.runInNewContext(source + '\nAlertState;', {
        URL, AbortSignal, DurableObject: class {}, fetch: async () => new Response(body, {status}),
    });
    const state = new State();
    state.ctx = {storage: {get: async () => null}};
    state.env = {GATEWAY_MODE: 'public', HEALTH_URL: 'https://app.test/up', ALERT_DELIVERY_ENABLED: 'false',
        APP: {fetch: async () => new Response('Application up')},
        QUEUE: {getByName: () => ({failedJobWatermark: async () => '0'})},
    };
    assert.equal((await state.poll()).gateway, true);
    body = 'Unexpected page';
    assert.equal((await state.poll()).gateway, false);
    body = 'Application up'; status = 503;
    assert.equal((await state.poll()).gateway, false);
});
