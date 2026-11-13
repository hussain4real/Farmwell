import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';
import vm from 'node:vm';

const source = (await readFile(new URL('../cloudflare-worker.js', import.meta.url), 'utf8'))
    .replace("import { DurableObject } from 'cloudflare:workers';", '')
    .replaceAll('export class ', 'class ')
    .replace('export default {', 'const worker = {');

const FarmwellWeb = vm.runInNewContext(`${source}\nFarmwellWeb;`, {
    AbortSignal,
    URL,
    DurableObject: class {
        constructor(ctx, env) {
            this.ctx = ctx;
            this.env = env;
        }
    },
    scheduler: { wait: async () => {} },
});

async function startWeb(image, running = true) {
    const events = [];
    const container = {
        images: { base: 'current-image' },
        running,
        inspect: async () => ({ image }),
        destroy: async () => {
            events.push('destroy');
            container.running = false;
        },
        start: ({ image: nextImage, instance, env }) => {
            events.push(`start:${nextImage}`);
            events.push(`instance:${instance}`);
            events.push(`database:${env.DB_URL}`);
            events.push(`mail:${env.MAIL_MAILER}:${env.CLOUDFLARE_ACCOUNT_ID}`);
            container.running = true;
        },
        setInactivityTimeout: async () => {},
        monitor: () => new Promise(() => {}),
        getTcpPort: () => ({
            fetch: async () => ({ ok: true, status: 200 }),
        }),
    };
    const instance = new FarmwellWeb({ container, waitUntil() {} }, { DB_URL: 'postgres://db-pooler.example/test', CLOUDFLARE_ACCOUNT_ID: 'test-account' });

    await instance.startAndWait();

    return events;
}

test('a running web container is not destroyed while requests may be in flight', async () => {
    assert.deepEqual(await startWeb('previous-image'), []);
});

test('web requests keep the current container image running', async () => {
    assert.deepEqual(await startWeb('current-image'), []);
});

test('a stopped web container starts with the current image and staging configuration', async () => {
    assert.deepEqual(await startWeb('', false), [
        'start:current-image',
        'instance:standard-1',
        'database:postgres://db.example/test',
        'mail:cloudflare:test-account',
    ]);
});

test('deployments route web requests to separate containers', async () => {
    const worker = vm.runInNewContext(`${source}\nworker;`, {
        AbortSignal,
        URL,
        DurableObject: class {},
    });
    const names = [];
    const env = {
        CF_VERSION_METADATA: { id: 'version-one' },
        FARMWELL_WEB: {
            idFromName(name) {
                names.push(name);

                return name;
            },
            get(_id, options) {
                assert.equal(options.locationHint, 'weur');

                return { ready: async () => {}, fetch: async () => 'ok' };
            },
        },
    };

    await worker.fetch({}, env);
    env.CF_VERSION_METADATA.id = 'version-two';
    await worker.fetch({}, env);

    assert.deepEqual(names, ['staging-web-version-one', 'staging-web-version-two']);
});

test('retries a safe request once when the container connection closes', async () => {
    const worker = vm.runInNewContext(`${source}\nworker;`, {
        AbortSignal,
        Error,
        URL,
        DurableObject: class {},
        scheduler: { wait: async () => {} },
    });
    let attempts = 0;
    const env = {
        CF_VERSION_METADATA: { id: 'version-one' },
        FARMWELL_WEB: { idFromName: (name) => name, get: () => ({ ready: async () => {}, fetch: async () => {
            attempts++;

            if (attempts === 1) {
                throw new Error('Container port connection closed unexpectedly.');
            }

            return 'ok';
        } }) },
    };

    assert.equal(await worker.fetch({ method: 'GET' }, env), 'ok');
    assert.equal(attempts, 2);
});

test('does not retry mutations when the container connection closes', async () => {
    const worker = vm.runInNewContext(`${source}\nworker;`, {
        AbortSignal,
        Error,
        URL,
        DurableObject: class {},
        scheduler: { wait: async () => {} },
    });
    let attempts = 0;
    const env = {
        CF_VERSION_METADATA: { id: 'version-one' },
        FARMWELL_WEB: { idFromName: (name) => name, get: () => ({ ready: async () => {}, fetch: async () => {
            attempts++;

            throw new Error('Container port connection closed unexpectedly.');
        } }) },
    };

    await assert.rejects(worker.fetch({ method: 'POST' }, env), /connection closed/);
    assert.equal(attempts, 1);
});

test('a cold POST retries readiness on a fresh stub before forwarding its body once', async () => {
    const worker = vm.runInNewContext(`${source}\nworker;`, {
        DurableObject: class {},
        scheduler: { wait: async () => {} },
    });
    let connections = 0;
    let submissions = 0;
    const env = {
        CF_VERSION_METADATA: { id: 'cold' },
        FARMWELL_WEB: {
            idFromName: (name) => name,
            get() {
                connections++;
                const cold = connections === 1;

                return {
                    async ready() {
                        if (cold) {
                            throw Object.assign(new Error('placement temporarily unavailable'), { retryable: true });
                        }
                    },
                    async fetch(request) {
                        submissions++;

                        return request.text();
                    },
                };
            },
        },
    };
    const request = new Request('https://staging.example/', { method: 'POST', body: 'synthetic draft' });

    assert.equal(await worker.fetch(request, env), 'synthetic draft');
    assert.equal(connections, 2);
    assert.equal(submissions, 1);
});

test('each deployed version processes reports with its own queue container', async () => {
    const worker = vm.runInNewContext(`${source}\nworker;`, { DurableObject: class {} });
    const runs = [];
    const env = {
        CF_VERSION_METADATA: { id: 'first' },
        FARMWELL_QUEUE: {
            getByName(name, options) {
                assert.equal(options.locationHint, 'weur');

                return { run: async () => runs.push(name) };
            },
        },
    };

    await worker.scheduled({}, env);
    env.CF_VERSION_METADATA.id = 'second';
    await worker.scheduled({}, env);

    assert.deepEqual(runs, ['staging-queue-first', 'staging-queue-second']);
});

test('failed-job probe returns only the newest ID and refuses unexpected database hosts', async () => {
    let calls = 0;
    const Queue = vm.runInNewContext(source + '\nFarmwellQueue;', {
        URL, AbortSignal, DurableObject: class {},
        fetch: async (url, options) => {
            calls++;
            assert.equal(url, 'https://api.us-east-1.aws.neon.tech/sql');
            assert.equal(options.redirect, 'manual');
            assert.equal(options.headers['Neon-Connection-String'], 'postgresql://ep-staging.us-east-1.aws.neon.tech/staging');
            assert.match(JSON.parse(options.body).query, /MAX\(id\).*FROM failed_jobs/);

            return Response.json({rows: [{latest: '42'}]});
        },
    });
    const queue = new Queue();
    queue.env = {DB_URL: 'pgsql://ep-staging.us-east-1.aws.neon.tech/staging?sslmode=require'};
    assert.equal(await queue.failedJobWatermark(), '42');
    queue.env.DB_URL = 'postgresql://untrusted.example/staging';
    await assert.rejects(queue.failedJobWatermark(), /Unsupported staging database host/);
    assert.equal(calls, 1);
});

test('only the web container receives phone verification settings and credentials', async () => {
    const webStarts = [];
    const phoneBindings = {
        PHONE_LOGIN_ENABLED: 'true',
        PHONE_PILOT_SMS_DAILY_LIMIT: '10',
        INFOBIP_API_KEY: 'example-key',
        PHONE_PILOT_NIGERIA_OTP_PROVIDER: 'kudisms',
        KUDISMS_API_KEY: 'test-token',
        KUDISMS_SENDER_ID: 'Farmwell',
        KUDISMS_OTP_APP_CODE: 'test-app',
        KUDISMS_OTP_TEMPLATE_CODE: 'test-template',
    };
    const env = { DB_URL: 'postgres://staging.example/test', ...phoneBindings };
    const container = {
        images: { base: 'test' }, running: false,
        start: ({ env }) => webStarts.push(env),
        setInactivityTimeout: async () => {}, monitor: () => new Promise(() => {}),
        getTcpPort: () => ({ fetch: async () => ({ ok: true }) }),
    };
    await new FarmwellWeb({ container, waitUntil() {} }, env).startAndWait();
    const sharedEnvironment = vm.runInNewContext(source + '\ncontainerEnvironment;', { DurableObject: class {}, URL });

    for (const [key, value] of Object.entries(phoneBindings)) {
        assert.equal(webStarts[0][key], value);
        assert.equal(sharedEnvironment(env)[key], undefined);
    }

    await new FarmwellWeb({ container, waitUntil() {} }, { ...env, REHEARSAL_MODE: 'true' }).startAndWait();
    assert.equal(webStarts[1].PHONE_LOGIN_ENABLED, 'false');
    assert.equal(webStarts[1].INFOBIP_API_KEY, '');
    assert.equal(webStarts[1].KUDISMS_API_KEY, '');
});

test('the pilot hostname only forwards SMS, assets and farm paths', async () => {
    const worker = vm.runInNewContext(source + '\nworker;', { DurableObject: class {}, URL, Response });
    const env = {
        APP_URL: 'https://staging.example', PHONE_PILOT_HOST: 'pilot.example',
        CF_VERSION_METADATA: { id: 'test' },
        FARMWELL_WEB: { idFromName: name => name, get: () => ({ ready: async () => {}, fetch: async () => new Response('forwarded') }) },
    };

    for (const path of ['/login/phone', '/login/phone/code', '/login/phone/verify', '/manifest.webmanifest', '/farmwell-192.png', '/farmwell-512.png', '/build/assets/login-A123.js', '/my-team/field-diary']) {
        assert.equal(await (await worker.fetch(new Request('https://pilot.example' + path), env)).text(), 'forwarded');
    }

    for (const path of ['/register', '/forgot-password', '/server-status', '/up', '/index.php/register', '/farmwell-128.png', '/build/assets/other/file.php']) {
        assert.equal((await worker.fetch(new Request('https://pilot.example' + path), env)).status, 404);
    }

    assert.equal((await worker.fetch(new Request('https://other.example/login/phone'), env)).status, 404);
    assert.equal(await (await worker.fetch(new Request('https://staging.example/register'), env)).text(), 'forwarded');
});

test('production rehearsal denies public access and mutations and strips its private credential', async () => {
    const worker = vm.runInNewContext(`${source}\nworker;`, { URL, Request, Response, DurableObject: class {} });
    let forwarded = 0;
    const env = {
        REHEARSAL_MODE: 'true', REHEARSAL_TOKEN: 'private-test-token',
        CF_VERSION_METADATA: { id: 'rehearsal' },
        FARMWELL_WEB: {
            idFromName: (name) => name,
            get: () => ({ ready: async () => {}, fetch: async (request) => {
                assert.equal(request.headers.has('x-farmwell-rehearsal'), false);
                forwarded++;

                return new Response('private app');
            } }),
        },
    };

    for (const request of [
        new Request('https://preview.test/login'),
        new Request('https://preview.test/login', { headers: { 'x-farmwell-rehearsal': 'wrong' } }),
        new Request('https://preview.test/login', { method: 'POST', headers: { 'x-farmwell-rehearsal': 'private-test-token' } }),
    ]) {
        assert.equal((await worker.fetch(request, env)).status, 403);
    }

    assert.equal(forwarded, 0);
    assert.equal((await worker.fetch(new Request('https://preview.test/login', { headers: { 'x-farmwell-rehearsal': 'private-test-token' } }), env)).status, 200);
    assert.equal(forwarded, 1);
    assert.equal((await worker.fetch(new Request('https://preview.test/login'), { REHEARSAL_MODE: 'true' })).status, 403);
    await worker.scheduled({}, { REHEARSAL_MODE: 'true' });
});

test('rehearsal containers suppress mail and AI credentials without changing staging cookies', () => {
    const environment = vm.runInNewContext(`${source}\ncontainerEnvironment;`, { URL, DurableObject: class {} });
    const settings = { DB_URL: 'postgres://db.example/test', OPENAI_API_KEY: 'do-not-use', CLOUDFLARE_KEY: 'do-not-use' };
    assert.equal('SESSION_COOKIE' in environment(settings), false);
    const result = environment({ ...settings, REHEARSAL_MODE: 'true', SESSION_COOKIE: 'production-cookie' });
    assert.equal(result.MAIL_MAILER, 'array');
    assert.equal(result.OPENAI_API_KEY, '');
    assert.equal(result.CLOUDFLARE_KEY, '');
    assert.equal(result.SESSION_COOKIE, 'production-cookie');
});
