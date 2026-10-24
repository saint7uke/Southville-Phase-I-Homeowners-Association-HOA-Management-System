import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import { services } from '../../resources/js/react/landing/content.js';

test('public service icons use the six distinct installed outlined Heroicon paths', () => {
    assert.deepEqual(services.map(service => service.icon), ['banknotes', 'megaphone', 'clipboard-document-list', 'exclamation-triangle', 'identification', 'chat-bubble-left-right']);
    const component = fs.readFileSync('resources/js/react/landing/ServiceIcon.jsx', 'utf8');
    for (const service of services) {
        const svg = fs.readFileSync(`vendor/blade-ui-kit/blade-heroicons/resources/svg/o-${service.icon}.svg`, 'utf8');
        const originalPath = svg.match(/\bd="([^"]+)"/)[1];
        assert.ok(component.includes(originalPath), `${service.icon} must match the installed icon`);
    }
});

test('existing service titles and descriptions remain available in both languages', () => {
    for (const locale of ['en', 'fil']) {
        const dictionary = JSON.parse(fs.readFileSync(`lang/${locale}.json`, 'utf8'));
        for (const service of services) {
            assert.ok(dictionary[service.title]);
            assert.ok(dictionary[service.description]);
        }
    }
});
