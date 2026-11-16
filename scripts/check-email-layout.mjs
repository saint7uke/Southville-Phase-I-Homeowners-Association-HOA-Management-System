import { chromium } from '@playwright/test';
import { readFile, mkdir } from 'node:fs/promises';
import path from 'node:path';
import assert from 'node:assert/strict';

// Synthetic local previews only. All external requests are blocked.
const directory = path.resolve('storage/framework/testing/email-design');
await mkdir(directory, { recursive: true });
const browser = await chromium.launch({ headless: true });
let checks = 0;
try {
    for (const locale of ['en', 'fil']) {
        for (const width of [375, 768, 1280]) {
            for (const blocked of [false, true]) {
                const page = await browser.newPage({ viewport: { width, height: 1100 } });
                await page.route('**/*', async route => {
                    if (!blocked && route.request().url().endsWith('/images/hoa-email-logo.png')) {
                        await route.fulfill({ path: path.resolve('public/images/hoa-email-logo.png'), contentType: 'image/png' });
                    } else {
                        await route.abort();
                    }
                });
                await page.setContent(await readFile(path.join(directory, `${locale}.html`), 'utf8'), { waitUntil: 'networkidle' });
                assert.equal(await page.locator('h1').count(), 1);
                assert.equal(await page.locator('.email-details').count(), 1);
                assert.equal(await page.locator('a[href="https://invalid.example"]').count(), 0);
                assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), `${locale}/${width}: horizontal overflow`);
                assert.ok(await page.locator('.button').isVisible());
                await page.screenshot({ path: path.join(directory, `${locale}-${width}${blocked ? '-blocked' : ''}.png`), fullPage: true });
                await page.close();
                checks++;
            }
        }
    }
} finally {
    await browser.close();
}
console.log(`${checks} synthetic email layout checks passed; no emails sent. Gmail/Outlook remain unverified.`);
