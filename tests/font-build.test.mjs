import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import test from 'node:test';

test('the build emits all Instrument Sans weights as local WOFF2 assets', async () => {
    const manifest = JSON.parse(await readFile('public/build/fonts-manifest.json', 'utf8'));
    const family = manifest.families['instrument-sans'];

    assert.equal(family.family, 'Instrument Sans');

    for (const weight of [400, 500, 600]) {
        const files = family.variants[`${weight}:normal`].files;
        assert.equal(files.length, 1);
        assert.equal(files[0].format, 'woff2');
        const font = await readFile(`public/build/${files[0].file}`);
        assert.equal(font.subarray(0, 4).toString('ascii'), 'wOF2');
    }
});
