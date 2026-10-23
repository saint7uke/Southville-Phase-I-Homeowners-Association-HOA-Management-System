import fs from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

export const fixtureFiles = [
    ...['admin', 'staff', 'homeowner'].map(panel => `dashboard-navigation/${panel}.html`),
    ...['en', 'fil'].flatMap(locale => ['pending', 'approved', 'rejected', 'completed'].map(status => `cohabitation-request-guidance/${locale}-${status}.html`)),
];

// Source-only preflight. Never boots Laravel, opens a database, or fetches a URL.
export async function checkFixtureAssets(root, files = fixtureFiles) {
    const publicRoot = path.resolve(root, 'public');
    const fixtureRoot = path.resolve(root, 'storage/framework/testing');
    const errors = [];
    for (const file of files) {
        const fixture = path.resolve(fixtureRoot, file);
        if (!fixture.startsWith(fixtureRoot + path.sep)) throw new Error('Fixture path escaped the test directory.');
        let html;
        try { html = await fs.readFile(fixture, 'utf8'); }
        catch { errors.push(`${file}: missing fixture; export guarded tests after the build.`); continue; }
        let builtAssets = 0;
        for (const match of html.matchAll(/\b(?:src|href)\s*=\s*(["'])(.*?)\1/gi)) {
            let url;
            try { url = new URL(match[2].replaceAll('&amp;', '&'), 'http://dashboard-navigation.test'); }
            catch { continue; }
            if (!['dashboard-navigation.test', 'certificate-guidance.test', 'localhost', '127.0.0.1'].includes(url.hostname)) continue;
            if (!/^\/(build|css|js|images|fonts)\//.test(url.pathname)) continue;
            const asset = path.resolve(publicRoot, `.${decodeURIComponent(url.pathname)}`);
            if (!asset.startsWith(publicRoot + path.sep)) throw new Error('Asset path escaped the public directory.');
            if (url.pathname.startsWith('/build/assets/')) builtAssets++;
            try { await fs.access(asset); }
            catch { errors.push(`${file}: stale or missing asset ${url.pathname}; rebuild, then re-export fixtures.`); }
        }
        if (builtAssets === 0) errors.push(`${file}: no production-bundle references; a real built-page fixture is required.`);
    }
    return [...new Set(errors)];
}

if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
    const errors = await checkFixtureAssets(path.resolve(fileURLToPath(new URL('..', import.meta.url))));
    if (errors.length) {
        console.error(errors.join('\n'));
        process.exitCode = 1;
    } else console.log(`Asset preflight passed for ${fixtureFiles.length} synthetic fixtures.`);
}
