import { DurableObject } from 'cloudflare:workers';

function containerEnvironment(env) {
    const databaseUrl = new URL(env.DB_URL);
    databaseUrl.hostname = databaseUrl.hostname.replace('-pooler.', '.');

    return {
        APP_NAME: 'Farmwell Staging',
        APP_ENV: 'production',
        APP_DEBUG: 'false',
        APP_TRUSTED_PROXIES: '*',
        APP_KEY: env.APP_KEY,
        APP_URL: env.APP_URL,
        DB_CONNECTION: 'pgsql',
        DB_URL: databaseUrl.toString(),
        SESSION_DRIVER: 'database',
        SESSION_SECURE_COOKIE: 'true',
        CACHE_STORE: 'database',
        QUEUE_CONNECTION: 'database',
        FARMWELL_PRIVATE_DISK: 'r2',
        AWS_ACCESS_KEY_ID: env.AWS_ACCESS_KEY_ID,
        AWS_SECRET_ACCESS_KEY: env.AWS_SECRET_ACCESS_KEY,
        AWS_DEFAULT_REGION: 'auto',
        AWS_ENDPOINT: env.AWS_ENDPOINT,
        AWS_BUCKET: env.AWS_BUCKET,
        MAIL_MAILER: 'cloudflare',
        CLOUDFLARE_ACCOUNT_ID: env.CLOUDFLARE_ACCOUNT_ID,
        CLOUDFLARE_KEY: env.CLOUDFLARE_KEY,
        MAIL_FROM_ADDRESS: 'noreply@staging-mail.farmwell.app',
        MAIL_FROM_NAME: 'Farmwell Staging',
        OPENAI_API_KEY: env.OPENAI_API_KEY,
        AI_PROVIDER: 'openai',
        OPENAI_STORE: 'false',
        LOG_CHANNEL: 'stderr',
    };
}

export class FarmwellWeb extends DurableObject {
    starting;

    async ready() {
        this.starting ??= this.startAndWait().finally(() => {
            this.starting = undefined;
        });
        await this.starting;
    }

    async fetch(request) {
        const original = new URL(request.url);
        const target = new URL(request.url);
        target.protocol = 'http:';
        target.host = 'container';
        const forwarded = new Request(target, request);
        forwarded.headers.set('x-forwarded-host', original.host);
        forwarded.headers.set(
            'x-forwarded-proto',
            original.protocol.slice(0, -1),
        );
        forwarded.headers.delete('x-forwarded-port');

        return this.ctx.container.getTcpPort(80).fetch(forwarded);
    }

    async startAndWait() {
        const container = this.ctx.container;

        if (!container.running) {
            container.start({
                image: container.images.base,
                instance: 'standard-1',
                enableInternet: true,
                env: {
                    ...containerEnvironment(this.env),
                    PHONE_LOGIN_ENABLED: this.env.PHONE_LOGIN_ENABLED ?? 'false',
                    PHONE_OTP_ALLOWED_NUMBER: this.env.PHONE_OTP_ALLOWED_NUMBER ?? '',
                    INFOBIP_BASE_URL: this.env.INFOBIP_BASE_URL ?? '',
                    INFOBIP_API_KEY: this.env.INFOBIP_API_KEY ?? '',
                    INFOBIP_2FA_APP_ID: this.env.INFOBIP_2FA_APP_ID ?? '',
                    INFOBIP_2FA_MESSAGE_ID: this.env.INFOBIP_2FA_MESSAGE_ID ?? '',
                },
                entrypoint: ['sh', '-c', 'exec apache2-foreground >>/tmp/farmwell-apache.log 2>&1'],
            });
        }

        await container.setInactivityTimeout(300000);
        const port = container.getTcpPort(80);
        let startupFailure;
        let lastStatus;
        this.ctx.waitUntil(container.monitor().then(
            () => {
                startupFailure = 'Container exited during startup';
            },
            (error) => {
                startupFailure = `Container exited during startup: ${error.message}`;
            },
        ));

        for (let attempt = 0; attempt < 100; attempt++) {
            if (startupFailure) {
                throw new Error(startupFailure);
            }

            try {
                const response = await port.fetch('http://container/up', {
                    signal: AbortSignal.timeout(1000),
                });
                await response.body?.cancel();
                lastStatus = response.status;

                if (response.ok) {
                    return;
                }
            } catch {
                /* Wait for Apache to start. */
            }

            await scheduler.wait(200);
        }

        throw new Error(`Farmwell container did not become ready (last status: ${lastStatus ?? 'unreachable'})`);
    }
}

export class FarmwellQueue extends DurableObject {
    currentRun;

    async failedJobWatermark() {
        const connection = new URL(this.env.DB_URL);

        if (!['pgsql:', 'postgresql:', 'postgres:'].includes(connection.protocol) || !connection.hostname.endsWith('.neon.tech')) {
            console.error('Unsupported staging database host or scheme');

            throw new Error('Unsupported staging database host');
        }

        connection.protocol = 'postgresql:';
        connection.search = '';
        const apiHost = connection.hostname.replace(/^[^.]+\./, 'api.');
        const result = await fetch(`https://${apiHost}/sql`, {
            method: 'POST',
            redirect: 'manual',
            signal: AbortSignal.timeout(15000),
            headers: { 'Content-Type': 'application/json', 'Neon-Connection-String': connection.toString() },
            body: JSON.stringify({ query: 'SELECT COALESCE(MAX(id), 0)::text AS latest FROM failed_jobs', params: [] }),
        });

        if (!result.ok) {
            const problem = await result.json().catch(() => ({}));
            const code = /^[0-9A-Z]{5}$/.test(problem?.code) ? problem.code : 'unknown';
            console.error('Failed-job SQL endpoint returned', result.status, code);

            throw new Error(`Failed-job check unavailable HTTP ${result.status}`);
        }

        const latest = (await result.json()).rows?.[0]?.latest;

        if (!/^\d+$/.test(latest)) {
            console.error('Invalid failed-job watermark');

            throw new Error('Invalid failed-job watermark');
        }

        return String(latest);
    }

    run() {
        this.currentRun ??= this.runOnce().finally(() => {
            this.currentRun = undefined;
        });

        return this.currentRun;
    }

    async runOnce() {
        const container = this.ctx.container;

        if (!container.running) {
            container.start({
                image: container.images.base,
                instance: 'lite',
                enableInternet: true,
                env: containerEnvironment(this.env),
                entrypoint: [
                    'php',
                    'artisan',
                    'queue:work',
                    'database',
                    '--stop-when-empty',
                    '--timeout=110',
                    '--tries=3',
                    '--no-interaction',
                ],
            });
        }

        await container.monitor();
    }
}

export default {
    async fetch(request, env) {
        const id = env.FARMWELL_WEB.idFromName(`staging-web-${env.CF_VERSION_METADATA.id}`);
        const connect = () => env.FARMWELL_WEB.get(id, { locationHint: 'weur' });
        const safeRead = ['GET', 'HEAD'].includes(request.method);
        let web = connect();
        let forwarded = false;

        for (let attempt = 1; ; attempt++) {
            try {
                await web.ready();
                forwarded = true;

                return await web.fetch(request);
            } catch (error) {
                const transient = error?.retryable ||
                    /Container port connection closed unexpectedly|Container exited during startup|Farmwell container did not become ready/.test(error?.message ?? '');

                if (attempt === 2 || error?.overloaded || !transient || (forwarded && !safeRead)) {
                    console.error('Farmwell web request failed', {
                        stage: forwarded ? 'forward' : 'startup',
                        method: request.method,
                        attempt,
                    });

                    throw error;
                }

                await scheduler.wait(250);
                web = connect();
                forwarded = false;
            }
        }
    },
    async scheduled(_event, env) {
        await env.FARMWELL_QUEUE.getByName(
            `staging-queue-${env.CF_VERSION_METADATA.id}`,
            { locationHint: 'weur' },
        ).run();
    },
};
