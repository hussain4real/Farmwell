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
        start: ({ image: nextImage, env }) => {
            events.push(`start:${nextImage}`);
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
            getByName(name) {
                names.push(name);

                return { fetch: async () => 'ok' };
            },
        },
    };

    await worker.fetch({}, env);
    env.CF_VERSION_METADATA.id = 'version-two';
    await worker.fetch({}, env);

    assert.deepEqual(names, ['staging-web-version-one', 'staging-web-version-two']);
});
