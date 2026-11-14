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

    await new FarmwellWeb({ container, waitUntil() {} }, {
        ...env, REHEARSAL_MODE: 'true', APP_URL: 'https://production-preview.farmwell.app',
        PRODUCTION_VERIFICATION_STARTED_AT: String(Date.now() - 1000),
        PRODUCTION_VERIFICATION_UNTIL: String(Date.now() + 60000),
    }).startAndWait();
    assert.equal(webStarts[2].PHONE_LOGIN_ENABLED, phoneBindings.PHONE_LOGIN_ENABLED);
    assert.equal(webStarts[2].KUDISMS_API_KEY, phoneBindings.KUDISMS_API_KEY);
    assert.equal(webStarts[2].INFOBIP_API_KEY, '');
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
    const result = environment({ ...settings, REHEARSAL_MODE: 'true', SESSION_COOKIE: 'production-cookie', MAIL_FROM_ADDRESS: 'noreply@mail.example.test' });
    assert.equal(result.MAIL_MAILER, 'array');
    assert.equal(result.OPENAI_API_KEY, '');
    assert.equal(result.CLOUDFLARE_KEY, '');
    assert.equal(result.SESSION_COOKIE, 'production-cookie');
    assert.equal(result.MAIL_FROM_ADDRESS, 'noreply@mail.example.test');
});

test('cold readiness measures static Apache readiness before Laravel and coalesces callers', async () => {
    let clock = 0;
    const calls = [];
    const logs = [];
    const Web = vm.runInNewContext(`${source}\nFarmwellWeb;`, {
        AbortSignal,
        URL,
        Date: { now: () => clock },
        console: { info: (_label, value) => logs.push(value) },
        DurableObject: class {
            constructor(ctx, env) {
                this.ctx = ctx;
                this.env = env;
            }
        },
        scheduler: {
            wait: async () => {
                clock += 200;
            },
        },
    });
    const container = {
        running: false,
        images: { base: 'image' },
        start() {
            this.running = true;
        },
        setInactivityTimeout: async () => {},
        monitor: () => new Promise(() => {}),
        getTcpPort: () => ({
            fetch: async (url) => {
                calls.push(url);
                clock += url.endsWith('favicon.svg') ? 800 : 120;

                return new Response('ready');
            },
        }),
    };
    const web = new Web(
        { container, waitUntil() {} },
        {
            DB_URL: 'postgres://db.example/test',
            STARTUP_TIMING_ENABLED: 'true',
        },
    );
    const [first, second] = await Promise.all([web.ready(), web.ready()]);
    assert.deepEqual(JSON.parse(JSON.stringify(first)), {
        cold: true,
        http_ready_ms: 800,
        laravel_ready_ms: 120,
    });
    assert.equal(first, second);
    assert.deepEqual(calls, [
        'http://container/favicon.svg',
        'http://container/up',
    ]);
    assert.equal(logs.length, 1);
    const warm = await web.ready();
    assert.equal(warm.cold, false);
    assert.equal(warm.http_ready_ms, 0);
    assert.equal(warm.laravel_ready_ms, 120);
});

test('Apache readiness alone cannot pass a failed Laravel health check', async () => {
    let attempts = 0;
    const container = {
        running: false,
        images: { base: 'image' },
        start() {},
        setInactivityTimeout: async () => {},
        monitor: () => new Promise(() => {}),
        getTcpPort: () => ({
            fetch: async (url) => {
                attempts++;

                return new Response('', {
                    status: url.endsWith('favicon.svg') ? 200 : 503,
                });
            },
        }),
    };
    const web = new FarmwellWeb(
        { container, waitUntil() {} },
        { DB_URL: 'postgres://db.example/test' },
    );
    await assert.rejects(web.ready(), /last status: 503/);
    assert.equal(attempts, 100);
});

test('startup timings and deployment identity are only exposed to authenticated rehearsal reads', async () => {
    const worker = vm.runInNewContext(`${source}\nworker;`, {
        URL,
        Request,
        Response,
        DurableObject: class {},
    });
    const env = {
        REHEARSAL_MODE: 'true',
        REHEARSAL_TOKEN: 'test-token',
        STARTUP_TIMING_ENABLED: 'true',
        CF_VERSION_METADATA: { id: 'measured-deployment' },
        FARMWELL_WEB: {
            idFromName: (name) => name,
            get: () => ({
                ready: async () => ({
                    cold: true,
                    http_ready_ms: 800,
                    laravel_ready_ms: 120,
                }),
                fetch: async () => new Response('Farmwell'),
            }),
        },
    };
    const blocked = await worker.fetch(
        new Request('https://preview.test/up'),
        env,
    );
    assert.equal(blocked.status, 403);
    assert.equal(blocked.headers.has('Server-Timing'), false);
    const response = await worker.fetch(
        new Request('https://preview.test/up', {
            headers: { 'x-farmwell-rehearsal': 'test-token' },
        }),
        env,
    );
    assert.match(
        response.headers.get('Server-Timing'),
        /http_ready;dur=800, laravel_ready;dur=120/,
    );
    assert.equal(response.headers.get('X-Farmwell-Cold-Start'), '1');
    assert.equal(
        response.headers.get('X-Farmwell-Deployment'),
        'measured-deployment',
    );
    const ordinary = await worker.fetch(
        new Request('https://preview.test/up'),
        { ...env, REHEARSAL_MODE: 'false' },
    );
    assert.equal(ordinary.headers.has('Server-Timing'), false);
});

test('production verification stays private, restricts SMS recipients and expires automatically', async () => {
    const now = Date.now();
    let time = now;
    let forwarded = 0;
    let runs = 0;
    const worker = vm.runInNewContext(`${source}\nworker;`, {
        URL, Request, Response, Date: { now: () => time }, DurableObject: class {},
    });
    const env = {
        REHEARSAL_MODE: 'true', REHEARSAL_TOKEN: 'private-token',
        APP_URL: 'https://production-preview.farmwell.app',
        PRODUCTION_VERIFICATION_STARTED_AT: String(now),
        PRODUCTION_VERIFICATION_UNTIL: String(now + 60000),
        PHONE_OTP_ALLOWED_NUMBER: '+2348000000000',
        CF_VERSION_METADATA: { id: 'verification' },
        FARMWELL_WEB: { idFromName: name => name, get: () => ({
            ready: async () => {},
            fetch: async request => {
                assert.equal(request.headers.has('x-farmwell-rehearsal'), false);
                forwarded++;

                return new Response('Farmwell');
            },
        }) },
        FARMWELL_QUEUE: { getByName: () => ({ run: async () => runs++ }) },
    };
    const request = (path = '/login/phone/code', phone = env.PHONE_OTP_ALLOWED_NUMBER, token = 'private-token') => new Request(env.APP_URL + path, {
        method: 'POST', headers: { 'x-farmwell-rehearsal': token, 'content-type': 'application/json' }, body: JSON.stringify({ phone }),
    });
    assert.equal((await worker.fetch(request(undefined, undefined, 'wrong'), env)).status, 403);
    assert.equal((await worker.fetch(request(undefined, '+2348111111111'), env)).status, 403);
    assert.equal((await worker.fetch(request('/login/phone/%63ode/', '+2348111111111'), env)).status, 403);
    assert.equal((await worker.fetch(request(), { ...env, PHONE_OTP_ALLOWED_NUMBER: '' })).status, 403);
    assert.equal(forwarded, 0);
    assert.equal((await worker.fetch(request(), env)).status, 200);
    assert.equal((await worker.fetch(new Request(env.APP_URL + '/login/phone/code', {
        method: 'POST', headers: { 'x-farmwell-rehearsal': 'private-token' },
        body: new URLSearchParams({ phone: env.PHONE_OTP_ALLOWED_NUMBER }),
    }), env)).status, 200);
    await assert.rejects(worker.fetch(request('/__verification/error'), env), /Controlled production verification/);
    await worker.scheduled({}, env);
    assert.equal(runs, 1);

    for (const settings of [
        { ...env, PRODUCTION_VERIFICATION_UNTIL: String(now + 7200001) },
        { ...env, PRODUCTION_VERIFICATION_STARTED_AT: String(now + 1) },
        { ...env, PRODUCTION_VERIFICATION_UNTIL: 'invalid' },
        { ...env, APP_URL: 'https://staging.farmwell.app' },
    ]) {
        assert.equal((await worker.fetch(request(), settings)).status, 403);
        await worker.scheduled({}, settings);
    }

    time = now + 60000;
    assert.equal((await worker.fetch(request(), env)).status, 403);
    assert.equal((await worker.fetch(new Request(env.APP_URL + '/__verification/error', { headers: { 'x-farmwell-rehearsal': 'private-token' } }), env)).status, 404);
    await worker.scheduled({}, env);
    assert.equal(runs, 1);
    assert.equal(forwarded, 2);
});

test('verification enables mail only during its bounded window and keeps AI disabled', () => {
    const now = Date.now();
    let time = now;
    const environment = vm.runInNewContext(`${source}\ncontainerEnvironment;`, { URL, Date: { now: () => time }, DurableObject: class {} });
    const env = {
        DB_URL: 'postgres://db.example/test', APP_URL: 'https://production-preview.farmwell.app',
        REHEARSAL_MODE: 'true', PRODUCTION_VERIFICATION_STARTED_AT: String(now),
        PRODUCTION_VERIFICATION_UNTIL: String(now + 60000), CLOUDFLARE_KEY: 'test-key', OPENAI_API_KEY: 'never-use',
    };
    assert.equal(environment(env).MAIL_MAILER, 'cloudflare');
    assert.equal(environment(env).CLOUDFLARE_KEY, 'test-key');
    assert.equal(environment(env).OPENAI_API_KEY, '');
    time += 60000;
    assert.equal(environment(env).MAIL_MAILER, 'array');
    assert.equal(environment(env).CLOUDFLARE_KEY, '');
});

test('cutover freezes only the selected farm and never replays writes on the destination', async () => {
    let forwarded = 0;
    const worker = vm.runInNewContext(`${source}\nworker;`, {URL, Request, Response, DurableObject: class {}});
    const env = {
        FROZEN_TEAM_SLUG: 'moved-farm', FROZEN_TEAM_ID: '4',
        CF_VERSION_METADATA: {id: 'cutover'},
        FARMWELL_WEB: {idFromName: name => name, get: () => ({ready: async () => {}, fetch: async () => {
forwarded++;

 return new Response('Unchanged farm');
}})},
    };

    for (const path of ['/moved-farm/field-diary', '/moved-farm/farm-activities', '/%6doved-farm//finance', '/settings/teams/4', '/settings/teams/moved-farm/members/13']) {
        for (const method of ['GET', 'POST', 'PATCH', 'DELETE']) {
            assert.equal((await worker.fetch(new Request('https://staging.test' + path, {method}), env)).status, 503);
        }
    }

    assert.equal(forwarded, 0);
    assert.equal((await worker.fetch(new Request('https://staging.test/other-farm/dashboard'), env)).status, 200);
    assert.equal((await worker.fetch(new Request('https://staging.test/moved-farm-2/dashboard'), env)).status, 200);
    env.MOVED_TEAM_ORIGIN = 'https://production.test';
    const moved = await worker.fetch(new Request('https://staging.test/moved-farm/field-diary?page=2'), env);
    assert.equal(moved.status, 302);
    assert.equal(moved.headers.get('location'), 'https://production.test/moved-farm/field-diary?page=2');
    assert.equal(moved.headers.get('cache-control'), 'no-store');
    const inertia = await worker.fetch(new Request('https://staging.test/moved-farm/field-diary', {headers: {'x-inertia': 'true'}}), env);
    assert.equal(inertia.status, 409);
    assert.equal(inertia.headers.get('x-inertia-location'), 'https://production.test/moved-farm/field-diary');
    assert.equal((await worker.fetch(new Request('https://staging.test/moved-farm/farm-activities', {method: 'POST'}), env)).status, 409);
    assert.equal(forwarded, 2);
});

test('the old pilot address forwards reads to production without replaying submissions', async () => {
    const worker = vm.runInNewContext(`${source}\nworker;`, {URL, Request, Response, DurableObject: class {}});
    const env = {REDIRECT_HOST: 'pilot.test', APP_URL: 'https://app.test'};
    const result = await worker.fetch(new Request('https://pilot.test/farm/field-diary?page=2'), env);
    assert.equal(result.status, 302);
    assert.equal(result.headers.get('location'), 'https://app.test/farm/field-diary?page=2');
    const inertia = await worker.fetch(new Request('https://pilot.test/farm/dashboard', {headers: {'x-inertia': 'true'}}), env);
    assert.equal(inertia.status, 409);
    assert.equal(inertia.headers.get('x-inertia-location'), 'https://app.test/farm/dashboard');
    const post = await worker.fetch(new Request('https://pilot.test/login/phone/code', {method: 'POST'}), env);
    assert.equal(post.status, 409);
    assert.equal(post.headers.has('location'), false);
});
