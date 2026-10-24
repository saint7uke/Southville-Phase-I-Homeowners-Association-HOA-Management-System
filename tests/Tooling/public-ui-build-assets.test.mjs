import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import { assertNoTestBuildOrigin } from '../../scripts/public-ui-build-assets.mjs';

test('browser build guard rejects temporary origins and accepts portable imports', async () => {
    const root = await fs.mkdtemp(path.join(os.tmpdir(), 'hoa-public-ui-build-'));
    try {
        const build = path.join(root, 'public/build');
        await fs.mkdir(path.join(build, 'assets'), { recursive: true });
        await fs.writeFile(path.join(build, 'manifest.json'), JSON.stringify({ main: { file: 'assets/main.js' } }));
        await fs.writeFile(path.join(build, 'assets/main.js'), 'const base = "http://127.0.0.1:61301/build/";');
        await assert.rejects(assertNoTestBuildOrigin(root, 'http://127.0.0.1:61301'), /temporary browser-server origin/);
        await fs.writeFile(path.join(build, 'assets/main.js'), 'const base = "/build/";');
        await assertNoTestBuildOrigin(root, 'http://127.0.0.1:61301');
        await fs.writeFile(path.join(build, 'manifest.json'), JSON.stringify({ main: { file: '../outside.js' } }));
        await assert.rejects(assertNoTestBuildOrigin(root, 'http://127.0.0.1:61301'), /Invalid build asset path/);
    } finally {
        // Only this explicitly created OS-temp fixture is removed, never workspace data.
        if (!path.resolve(root).startsWith(path.resolve(os.tmpdir()) + path.sep) || !path.basename(root).startsWith('hoa-public-ui-build-')) throw new Error('Refusing unexpected fixture cleanup path.');
        await fs.rm(root, { recursive: true, force: true });
    }
});
