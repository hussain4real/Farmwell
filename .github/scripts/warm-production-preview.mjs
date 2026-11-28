import { readFile } from 'node:fs/promises';
import { pathToFileURL } from 'node:url';

export async function warmProductionPreview({
    token,
    version,
    fetcher = fetch,
}) {
    if (!token || !/^[a-f0-9-]{36}$/i.test(version)) {
        throw new Error(
            'A private credential and deployment version are required.',
        );
    }

    const observations = [];

    for (const path of ['/up', '/login']) {
        const started = performance.now();
        const response = await fetcher(
            `https://production-preview.farmwell.app${path}`,
            {
                headers: { 'x-farmwell-rehearsal': token },
                redirect: 'manual',
                signal: AbortSignal.timeout(150000),
            },
        );

        if (
            response.status !== 200 ||
            response.headers.get('x-farmwell-deployment') !== version
        ) {
            await response.body?.cancel();

            throw new Error('The expected private deployment is not ready.');
        }

        const body = await response.text();
        const cold = response.headers.get('x-farmwell-cold-start');

        if (
            !body.includes(path === '/up' ? 'Application up' : 'Farmwell') ||
            !['0', '1'].includes(cold) ||
            (path === '/login' && cold !== '0')
        ) {
            throw new Error(
                'Warm-up did not produce a healthy, warm login page.',
            );
        }

        observations.push({
            path,
            milliseconds: Math.round(performance.now() - started),
            cold: cold === '1',
            timing: response.headers.get('server-timing'),
        });
    }

    return { version, observations };
}

if (
    process.argv[1] &&
    import.meta.url === pathToFileURL(process.argv[1]).href
) {
    try {
        const args = process.argv.slice(2);

        if (
            args.length !== 4 ||
            args[0] !== '--version' ||
            args[2] !== '--secrets-file'
        ) {
            throw new Error(
                'Usage: --version UUID --secrets-file PRIVATE_JSON_FILE',
            );
        }

        const { REHEARSAL_TOKEN: token } = JSON.parse(
            await readFile(args[3], 'utf8'),
        );
        console.log(
            JSON.stringify(
                await warmProductionPreview({ token, version: args[1] }),
            ),
        );
    } catch {
        console.error(
            'Production preview warm-up failed. Check the private credential, deployment version and health.',
        );
        process.exitCode = 1;
    }
}
