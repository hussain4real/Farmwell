import { DurableObject } from 'cloudflare:workers';

const healthUrl = 'https://staging.farmwell.app/up';

export class AlertState extends DurableObject {
    currentPoll;

    async recordErrors(count) {
        const storage = this.ctx.storage;
        await storage.put(
            'errors',
            ((await storage.get('errors')) ?? 0) + count,
        );
    }

    async notify(subject, detail) {
        await this.env.MAIL.send({
            from: 'noreply@staging-mail.farmwell.app',
            to: this.env.ALERT_TO,
            subject: '[Farmwell staging] ' + subject,
            text: detail + '\nObserved at ' + new Date().toISOString() + '.',
        });
    }

    async transition(key, healthy, label) {
        const storage = this.ctx.storage;
        const wasHealthy = (await storage.get(key)) !== true;

        if (healthy === wasHealthy) {
            return;
        }

        await this.notify(
            label + (healthy ? ' recovered' : ' failed'),
            healthy
                ? 'The check is passing again.'
                : 'Inspect Farmwell staging and Cloudflare Workers Logs.',
        );
        await storage.put(key, !healthy);
    }

    poll() {
        this.currentPoll ??= this.checkAndNotify().finally(() => {
            this.currentPoll = undefined;
        });

        return this.currentPoll;
    }

    async checkAndNotify() {
        const storage = this.ctx.storage;
        const [edge, app, queue] = await Promise.allSettled([
            fetch(healthUrl, {
                redirect: 'manual',
                signal: AbortSignal.timeout(15000),
            }).then(async (r) => {
                await r.body?.cancel();

                return (
                    r.status === 302 &&
                    new URL(r.headers.get('location')).hostname.endsWith(
                        '.cloudflareaccess.com',
                    )
                );
            }),
            this.env.APP.fetch(healthUrl, {
                redirect: 'manual',
                signal: AbortSignal.timeout(45000),
            }).then(
                async (r) =>
                    r.status === 200 &&
                    (await r.text()).includes('Application up'),
            ),
            this.env.QUEUE.getByName('failed-job-monitor', {
                locationHint: 'weur',
            }).failedJobWatermark(),
        ]);
        const edgesUp = edge.status === 'fulfilled' && edge.value;
        const appUp = app.status === 'fulfilled' && app.value;
        const queueUp = queue.status === 'fulfilled';

        console.info('Staging checks', {
            gateway: edgesUp,
            application: appUp,
            failedJobs: queueUp,
        });

        await this.transition('gatewayDown', edgesUp, 'Public access gateway');
        await this.transition('appDown', appUp, 'Application uptime');
        await this.transition(
            'queueCheckDown',
            queueUp,
            'Failed-job monitoring',
        );

        if (
            queueUp &&
            BigInt(queue.value) >
                BigInt(
                    (await storage.get('lastJob')) ??
                        this.env.INITIAL_FAILED_JOB_ID,
                )
        ) {
            await this.notify(
                'Failed queue job',
                'Laravel recorded a new terminal queue failure. Inspect queue:failed on staging. Latest failure ID ' +
                    queue.value +
                    '.',
            );
            await storage.put('lastJob', queue.value);
        }

        const errors = (await storage.get('errors')) ?? 0;
        const sent = (await storage.get('sentErrors')) ?? 0;

        if (errors > sent) {
            await this.notify(
                'Application error',
                errors -
                    sent +
                    ' failed invocation(s) or HTTP 5xx response(s) observed. Inspect Farmwell staging Workers Logs.',
            );
            await storage.put('sentErrors', errors);
        }

        const testId = this.env.DELIVERY_TEST;

        if (
            testId &&
            edgesUp &&
            appUp &&
            queueUp &&
            (await storage.get('lastTest')) !== testId
        ) {
            await this.notify(
                'Delivery test ' + testId,
                'This is the requested test of the staging alert channel. The gateway, Laravel health endpoint and failed-job query passed.',
            );
            await storage.put('lastTest', testId);
        }
    }
}

export default {
    async scheduled(_event, env) {
        await env.STATE.getByName('staging').poll();
    },
    async tail(events, env) {
        const failures = events.filter(
            (e) =>
                e.outcome === 'exception' ||
                e.outcome === 'exceededCpu' ||
                e.outcome === 'exceededMemory' ||
                e.exceptions?.length ||
                e.event?.response?.status >= 500,
        );

        if (failures.length) {
            await env.STATE.getByName('staging').recordErrors(failures.length);
        }
    },
};
