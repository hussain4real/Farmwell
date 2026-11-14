import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { once } from 'node:events';
import { access, readFile, writeFile, rm, stat } from 'node:fs/promises';
import { request } from 'node:http';
import { setTimeout as delay } from 'node:timers/promises';

// Run only inside the disposable application image, with --network none.
// Mount the test runner separately; build tools must not ship in the image.
for (const path of [
    '/app/node_modules',
    '/var/www/html/node_modules',
    '/usr/local/bin/node',
    '/usr/local/bin/composer',
]) {
    await assert.rejects(access(path), { code: 'ENOENT' });
}

const manifest = JSON.parse(
    await readFile('/var/www/html/public/build/manifest.json', 'utf8'),
);
assert.equal(
    (await stat('/var/www/html/app/Providers/AppServiceProvider.php')).uid,
    0,
);
assert.equal((await stat('/var/www/html/bootstrap/cache')).uid, 33);
assert.equal((await stat('/var/www/html/storage')).uid, 33);
assert.ok(Object.values(manifest).some((entry) => entry.isEntry));
const fonts = JSON.parse(
    await readFile('/var/www/html/public/build/fonts-manifest.json', 'utf8'),
);

for (const weight of [400, 500, 600]) {
    for (const file of fonts.families['instrument-sans'].variants[
        `${weight}:normal`
    ].files) {
        assert.equal(
            (await readFile(`/var/www/html/public/build/${file.file}`))
                .subarray(0, 4)
                .toString(),
            'wOF2',
        );
    }
}

const probe = '/var/www/html/public/__upload_limits_test.php';
await writeFile(
    probe,
    `<?php
header('Content-Type: application/json');
echo json_encode([
    'marker' => $_POST['marker'] ?? null,
    'errors' => $_FILES['evidence']['error'] ?? [],
    'sizes' => $_FILES['evidence']['size'] ?? [],
]);
`,
);
const server = spawn('apache2-foreground', [], {
    stdio: 'ignore',
    env: {
        ...process.env,
        APP_KEY: `base64:${Buffer.alloc(32).toString('base64')}`,
        APP_ENV: 'testing',
        APP_DEBUG: 'false',
        SESSION_DRIVER: 'array',
        CACHE_STORE: 'array',
        PHONE_LOGIN_ENABLED: 'false',
    },
});
const stopped = once(server, 'exit');
const boundary = 'farmwell-upload-test';
const megabyte = 1024 * 1024;

async function upload(sizes) {
    const chunks = [
        Buffer.from(
            `--${boundary}\r\nContent-Disposition: form-data; name="marker"\r\n\r\ndiary\r\n`,
        ),
    ];

    for (const [index, size] of sizes.entries()) {
        chunks.push(
            Buffer.from(
                `--${boundary}\r\nContent-Disposition: form-data; name="evidence[]"; filename="photo-${index}.jpg"\r\nContent-Type: image/jpeg\r\n\r\n`,
            ),
        );
        chunks.push(Buffer.alloc(size, 1));
        chunks.push(Buffer.from('\r\n'));
    }

    chunks.push(Buffer.from(`--${boundary}--\r\n`));

    return new Promise((resolve, reject) => {
        const req = request(
            {
                hostname: '127.0.0.1',
                port: 80,
                path: '/__upload_limits_test.php',
                method: 'POST',
                headers: {
                    'Content-Type': `multipart/form-data; boundary=${boundary}`,
                    'Content-Length': chunks.reduce(
                        (total, chunk) => total + chunk.length,
                        0,
                    ),
                },
            },
            (res) => {
                let body = '';
                res.setEncoding('utf8');
                res.on('data', (chunk) => {
                    body += chunk;
                });
                res.on('end', () => {
                    try {
                        assert.equal(res.statusCode, 200);
                        resolve(JSON.parse(body));
                    } catch (error) {
                        reject(
                            new Error(
                                `Upload sizes ${sizes.join(', ')} returned invalid JSON: ${body.slice(0, 500)}`,
                                { cause: error },
                            ),
                        );
                    }
                });
                res.on('error', reject);
            },
        );
        req.setTimeout(30000, () =>
            req.destroy(new Error('Upload test timed out')),
        );
        req.on('error', reject);

        for (const chunk of chunks) {
            req.write(chunk);
        }

        req.end();
    });
}

try {
    for (let attempt = 0; ; attempt++) {
        try {
            await upload([]);
            break;
        } catch (error) {
            if (attempt >= 49 || server.exitCode !== null) {
                throw error;
            }

            await delay(100);
        }
    }

    for (const path of ['/up', '/login']) {
        const response = await fetch(`http://127.0.0.1${path}`, {
            redirect: 'manual',
        });
        assert.equal(
            response.status,
            200,
            `${path} must work in the trimmed runtime`,
        );
        assert.ok(
            (await response.text()).includes(
                path === '/up' ? 'Application up' : 'Farmwell',
            ),
        );
    }

    for (const sizes of [
        [6 * megabyte],
        [10 * megabyte],
        Array(5).fill(10 * megabyte),
    ]) {
        const result = await upload(sizes);
        assert.equal(result.marker, 'diary');
        assert.deepEqual(
            result.errors,
            sizes.map(() => 0),
            'PHP must accept every permitted file',
        );
        assert.deepEqual(result.sizes, sizes);
    }

    assert.deepEqual(
        (await upload([10 * megabyte + 1])).errors,
        [1],
        'Oversized files must remain blocked',
    );
    const oversizedPost = await upload(Array(7).fill(10 * megabyte));
    assert.equal(
        oversizedPost.marker,
        null,
        'The total request size must remain bounded',
    );
    assert.deepEqual(oversizedPost.errors, []);
    console.log(
        'Apache accepted a 6 MB photo and five 10 MB files; oversized uploads stayed blocked.',
    );
} finally {
    server.kill('SIGTERM');
    await stopped;
    await rm(probe, { force: true });
}
