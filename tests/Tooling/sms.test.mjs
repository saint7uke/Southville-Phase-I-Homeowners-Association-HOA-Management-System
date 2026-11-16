import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { localMobile, validLocalMobile, validOtp, editableDigits, bindDigits } from '../../resources/js/sms-policy.js';

test('Philippine primary numbers share a normalized identity', () => {
    for (const value of ['09171234567', '+639171234567', '639171234567', '+63 (917) 123-4567']) {
        assert.equal(localMobile(value), '09171234567');
    }
    for (const value of ['0917123', '+14155550101', '', null]) assert.doesNotMatch(localMobile(value), /^09\d{9}$/);
});
test('enrollment does not retry mutations or retain a consumed proof', () => {
    const source = readFileSync('resources/js/sms-enrollment.js', 'utf8');
    assert.match(source, /hoaSmsEnrollmentInitialized/);
    assert.match(source, /if \(button.hasAttribute\('data-sms-save'\)\) proof.value = ''/);
    assert.match(source, /if \(!response.ok \|\| body\?\.errors\)/);
    assert.doesNotMatch(source, /setInterval|api\.textbee|x-api-key|\.innerHTML/);
});

test('new input is strict ASCII while internal legacy identities remain compatible', () => {
    assert.equal(validLocalMobile('09171234567'), true);
    for (const value of ['+639171234567', '639171234567', '0917-1234567', '09171234567\n', ' 09171234567', '091712345678', '08171234567', '０９１７１２３４５６７', ['09171234567'], null]) assert.equal(validLocalMobile(value), false);
    assert.equal(validOtp('012345'), true);
    for (const value of ['12345', '1234567', '１２３４５６', '12345a', '123456\n']) assert.equal(validOtp(value), false);
    assert.equal(editableDigits('', 11), true);
    assert.equal(editableDigits('09', 11), true);
    assert.equal(editableDigits('09e1', 11), false);
    assert.equal(editableDigits('091712345678', 11), false);
});

test('numeric editing rejects mixed paste and overlength edits without stripping characters', () => {
    const listeners = {};
    const input = { value: '0917', selectionStart: 4, selectionEnd: 4,
        addEventListener: (name, listener) => { listeners[name] = listener; },
        setCustomValidity(message) { this.error = message; }, reportValidity() {},
    };
    bindDigits(input, 11, () => 'Digits only');
    let prevented = false;
    listeners.beforeinput({ data: 'a', inputType: 'insertText', preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    assert.equal(input.value, '0917');
    prevented = false;
    listeners.paste({ clipboardData: { getData: () => '+639171234567' }, preventDefault() { prevented = true; } });
    assert.equal(prevented, true);
    input.value = '09171234567';
    listeners.input();
    assert.equal(input.error, '');
    input.value = '091712345678';
    listeners.input();
    assert.equal(input.value, '09171234567');
    assert.equal(input.error, '');
    input.value = '';
    listeners.input();
    assert.equal(input.value, '');
    assert.equal(input.error, '');
});
