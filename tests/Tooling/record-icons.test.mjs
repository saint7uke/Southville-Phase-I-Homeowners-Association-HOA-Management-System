import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';

test('record icon styling is scoped and never hides existing labels', () => {
    const css = readFileSync('resources/css/record-actions.css', 'utf8');
    assert.match(css, /min-width: 44px/);
    assert.match(css, /min-height: 44px/);
    assert.match(css, /:focus-visible/);
    assert.doesNotMatch(css, /display:\s*none|font-size:\s*0|\.fi-btn-label/);
});

test('shared native configuration decorates Filament actions system-wide', () => {
    const provider = readFileSync('app/Providers/AppServiceProvider.php', 'utf8');
    assert.match(provider, /modifyUngroupedRecordActionsUsing/);
    assert.match(provider, /Action::configureUsing\(function \(Action \$action\)/);
    assert.match(provider, /return RecordActionIcons::action\(\$action\)/);
    const helper = readFileSync('app/Support/RecordActionIcons.php', 'utf8');
    assert.match(helper, /hasTooltip/);
    assert.doesNotMatch(helper, /->label\(/);
    assert.match(helper, /->button\(\)/);
    assert.match(helper, /data-hoa-record-action/);
});

test('custom control retains visible translated labels and a non-submitting default', () => {
    const view = readFileSync('resources/views/components/record-icon.blade.php', 'utf8');
    assert.match(view, /'aria-label' => \$label/);
    assert.match(view, /'type' => \$href === null \? 'button'/);
    assert.match(view, /'aria-hidden' => 'true'/);
    assert.match(view, /<span .*data-i18n/);
    assert.match(view, /'title' => \$label/);
});
