import assert from 'node:assert/strict';
import test from 'node:test';
import { warmProductionPreview } from '../.github/scripts/warm-production-preview.mjs';

const version = '11111111-1111-4111-8111-111111111111';

test('warm-up stays on the private preview and verifies the expected version before testing login', async () => {
    const paths = [];
    const result = await warmProductionPreview({
        token: 'test-token',
        version,
        fetcher: async (url, options) => {
            assert.equal(
                new URL(url).origin,
                'https://production-preview.farmwell.app',
            );
            assert.equal(options.redirect, 'manual');
            assert.equal(options.headers['x-farmwell-rehearsal'], 'test-token');
            paths.push(new URL(url).pathname);

            return new Response(
                paths.length === 1 ? 'Application up' : 'Farmwell',
                {
                    headers: {
                        'x-farmwell-deployment': version,
                        'x-farmwell-cold-start': paths.length === 1 ? '1' : '0',
                        'server-timing':
                            'http_ready;dur=800, laravel_ready;dur=120',
                    },
                },
            );
        },
    });
    assert.deepEqual(paths, ['/up', '/login']);
    assert.deepEqual(
        result.observations.map((row) => row.cold),
        [true, false],
    );
    assert.equal(JSON.stringify(result).includes('test-token'), false);
});

for (const failure of [
    'redirect',
    'wrong-version',
    'unhealthy',
    'cold-login',
]) {
    test(`warm-up rejects ${failure}`, async () => {
        let requests = 0;
        await assert.rejects(
            warmProductionPreview({
                token: 'test-token',
                version,
                fetcher: async () => {
                    requests++;

                    return new Response(
                        failure === 'unhealthy'
                            ? 'unavailable'
                            : requests === 1
                              ? 'Application up'
                              : 'Farmwell',
                        {
                            status: failure === 'redirect' ? 302 : 200,
                            headers: {
                                'x-farmwell-deployment':
                                    failure === 'wrong-version'
                                        ? 'old-version'
                                        : version,
                                'x-farmwell-cold-start':
                                    failure === 'cold-login' ? '1' : '0',
                            },
                        },
                    );
                },
            }),
        );
        assert.equal(requests, failure === 'cold-login' ? 2 : 1);
    });
}
