import { DurableObject } from 'cloudflare:workers';

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
            from: this.env.MAIL_FROM ?? 'noreply@staging-mail.farmwell.app',
            to: this.env.ALERT_TO,
            subject: '[' + (this.env.ALERT_LABEL ?? 'Farmwell staging') + '] ' + subject,
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
                : 'Inspect ' + (this.env.ALERT_LABEL ?? 'Farmwell staging') + ' and Cloudflare Workers Logs.',
        );
        await storage.put(key, !healthy);
    }

    poll() {
        this.currentPoll ??= this.checkAndNotify().finally(() => {
            this.currentPoll = undefined;
        });

        return this.currentPoll;
    }

    async backupIsFresh() {
        if (!this.env.BACKUPS) {
            return true;
        }

        let cursor;
        let newest = 0;

        do {
            const page = await this.env.BACKUPS.list({ prefix: 'Farmwell/', cursor });

            for (const object of page.objects) {
                if (object.key.endsWith('.zip')) {
                    newest = Math.max(newest, new Date(object.uploaded).getTime());
                }
            }

            cursor = page.truncated ? page.cursor : undefined;
        } while (cursor);

        return newest > 0 && Date.now() - newest <= 48 * 60 * 60 * 1000;
    }

    async checkAndNotify() {
        const healthUrl = this.env.HEALTH_URL ?? 'https://staging.farmwell.app/up';
        const storage = this.ctx.storage;
        const [edge, app, queue, backup] = await Promise.allSettled([
            fetch(healthUrl, {
                redirect: 'manual',
                signal: AbortSignal.timeout(15000),
            }).then(async (r) => {
                if (this.env.GATEWAY_MODE === 'private') {
                    return r.status === 403 && (await r.text()) === 'Production rehearsal is private';
                }

                await r.body?.cancel();

                return (
                    r.status === 302 &&
                    new URL(r.headers.get('location')).hostname.endsWith(
                        '.cloudflareaccess.com',
                    )
                );
            }),
            this.env.APP.fetch(healthUrl, {
                headers: this.env.REHEARSAL_TOKEN ? { 'x-farmwell-rehearsal': this.env.REHEARSAL_TOKEN } : {},
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
            this.backupIsFresh(),
        ]);
        const edgesUp = edge.status === 'fulfilled' && edge.value;
        const appUp = app.status === 'fulfilled' && app.value;
        const queueUp = queue.status === 'fulfilled';
        const backupUp = backup.status === 'fulfilled' && backup.value;

        const checks = {
            gateway: edgesUp,
            application: appUp,
            failedJobs: queueUp,
            backup: backupUp,
        };

        if (this.env.ALERT_DELIVERY_ENABLED === 'false') {
            return checks;
        }

        await this.transition('gatewayDown', edgesUp, 'Public access gateway');
        await this.transition('appDown', appUp, 'Application uptime');
        await this.transition('backupDown', backupUp, 'Backup freshness');
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
                'Laravel recorded a new terminal queue failure. Inspect queue:failed. Latest failure ID ' +
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
                    ' failed invocation(s) or HTTP 5xx response(s) observed. Inspect the application Workers Logs.',
            );
            await storage.put('sentErrors', errors);
        }

        const testId = this.env.DELIVERY_TEST;

        if (
            testId &&
            edgesUp &&
            appUp &&
            queueUp &&
            backupUp &&
            (await storage.get('lastTest')) !== testId
        ) {
            await this.notify(
                'Delivery test ' + testId,
                'This is the requested test of the alert channel. The gateway, Laravel health endpoint and failed-job query passed.',
            );
            await storage.put('lastTest', testId);
        }

        return checks;
    }
}

export default {
    async scheduled(_event, env) {
        const checks = await env.STATE.getByName(env.MONITOR_NAME ?? 'staging').poll();
        console.info((env.ALERT_LABEL ?? 'Farmwell staging') + ' checks', checks);
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
            await env.STATE.getByName(env.MONITOR_NAME ?? 'staging').recordErrors(failures.length);
        }
    },
};
