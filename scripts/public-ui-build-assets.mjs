import fs from 'node:fs/promises';
import path from 'node:path';

// Browser acceptance must not leave shared bundles pointing at its disposable server.
export async function assertNoTestBuildOrigin(root, origin) {
    const build = path.resolve(root, 'public/build');
    const manifest = JSON.parse(await fs.readFile(path.join(build, 'manifest.json'), 'utf8'));
    const files = new Set(Object.values(manifest).map(entry => entry.file).filter(file => file?.endsWith('.js') || file?.endsWith('.css')));
    for (const file of files) {
        const asset = path.resolve(build, file);
        if (!asset.startsWith(build + path.sep)) throw new Error('Invalid build asset path.');
        const source = await fs.readFile(asset, 'utf8');
        if (source.includes(origin)) throw new Error(`Build asset ${file} contains the temporary browser-server origin. Refusing to certify a nonportable build.`);
    }
}
