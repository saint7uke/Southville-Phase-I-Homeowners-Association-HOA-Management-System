import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { checkFixtureAssets } from '../../scripts/static-browser-assets.mjs';

test('fixture preflight rejects obsolete hashes and missing fixtures, then accepts current assets', async t => {
    const root = await fs.mkdtemp(path.join(os.tmpdir(), 'hoa-static-assets-test-'));
    t.after(() => fs.rm(root, { recursive: true, force: true }));
    await fs.mkdir(path.join(root, 'public/build/assets'), { recursive: true });
    await fs.mkdir(path.join(root, 'storage/framework/testing/synthetic'), { recursive: true });
    const fixture = 'synthetic/page.html';
    const html = '<script type="module" src="http://certificate-guidance.test/build/assets/locale-current.js"></script>';
    await fs.writeFile(path.join(root, 'storage/framework/testing', fixture), html);
    assert.match((await checkFixtureAssets(root, [fixture]))[0], /stale or missing asset/);
    await fs.writeFile(path.join(root, 'public/build/assets/locale-current.js'), '// Synthetic bundle');
    assert.deepEqual(await checkFixtureAssets(root, [fixture]), []);
    assert.match((await checkFixtureAssets(root, ['synthetic/missing.html']))[0], /missing fixture/);
    await assert.rejects(() => checkFixtureAssets(root, ['../../../../outside.html']), /escaped/);
});

test('fixture preflight cannot accept a page without a compiled bundle', async t => {
    const root = await fs.mkdtemp(path.join(os.tmpdir(), 'hoa-static-no-assets-test-'));
    t.after(() => fs.rm(root, { recursive: true, force: true }));
    await fs.mkdir(path.join(root, 'storage/framework/testing/synthetic'), { recursive: true });
    await fs.writeFile(path.join(root, 'storage/framework/testing/synthetic/page.html'), '<h1>Synthetic fixture</h1>');
    assert.match((await checkFixtureAssets(root, ['synthetic/page.html']))[0], /no production-bundle/);
});
