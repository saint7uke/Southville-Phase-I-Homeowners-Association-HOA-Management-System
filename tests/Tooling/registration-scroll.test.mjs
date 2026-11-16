import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

test('scrolling rules are registration-scoped and have an explicit compact fallback', () => {
    const css = readFileSync('resources/css/registration-scroll.css', 'utf8');
    assert.match(css, /min-width: 721px.*min-height: 641px/);
    assert.match(css, /max-width: 720px.*max-height: 640px/);
    assert.match(css, /overflow-y: auto/);
    assert.match(css, /overflow: visible/);
    assert.match(css, /min-height: 0/);
    assert.doesNotMatch(css, /(?:^|\n)(?:body|html|\.hoa-auth-shell|\.hoa-portal-auth-form)\s*\{/);
});

test('scroll resets do not remove or reset registration fields', () => {
    const view = readFileSync('resources/views/portal/auth/register.blade.php', 'utf8');
    assert.match(view, /hoa-landing hoa-registration-page/);
    assert.match(view, /hoa-portal-auth hoa-registration-shell/);
    assert.match(view, /panel.scrollTop = 0/);
    assert.equal((view.match(/resetRegistrationScroll\(\);/g) || []).length, 2);
    const helper = view.split('function resetRegistrationScroll()')[1].split('function toggleDynamicFields')[0];
    assert.doesNotMatch(helper, /\.reset\(|\.value\s*=/);
});
