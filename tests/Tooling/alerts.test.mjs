import test from 'node:test';
import assert from 'node:assert/strict';
import { createFeedbackQueue, requestFeedback } from '../../resources/js/alert-policy.js';

test('request errors do not reveal exception bodies or claim success', () => {
    for (const status of [400, 401, 403, 404, 419, 422, 429, 500, 503, 0]) {
        const result = requestFeedback({ response: { status, data: { message: 'secret stack trace' } } });
        assert.equal(result.level, 'error');
        assert.ok(!result.message.includes('secret'));
        assert.ok(result.message.length > 10);
    }
    assert.match(requestFeedback().message, /Check whether the action completed/);
});

test('validation preserves safe field messages but ignores non-validation response bodies', () => {
    const response = { status: 422, data: { errors: { name: ['Enter a name.'], other: ['Enter a name.'] } } };
    assert.equal(requestFeedback({ response }).message, 'Enter a name.');
    response.status = 500;
    assert.ok(!requestFeedback({ response }).message.includes('Enter a name.'));
});

test('feedback is serialized, repeated IDs deduplicate, separate actions remain visible', async () => {
    const calls = [];
    let running = 0;
    const queue = createFeedbackQueue(async item => {
        running++;
        assert.equal(running, 1);
        await Promise.resolve();
        calls.push(item.id);
        running--;
    });
    await Promise.all([queue({ id: 'one' }), queue({ id: 'one' }), queue({ id: 'two' })]);
    assert.deepEqual(calls, ['one', 'two']);
});

test('a failed presentation does not poison the queue or permanently consume its identifier', async () => {
    let fail = true;
    const queue = createFeedbackQueue(async () => { if (fail) { fail = false; throw new Error('fixture'); } return true; });
    assert.equal(await queue({ id: 'one' }), false);
    assert.equal(await queue({ id: 'one' }), true);
});


test('shared renderer and session adapter have no generic success heading', async () => {
    const { readFile } = await import('node:fs/promises');
    for (const file of ['resources/js/alerts.js', 'resources/views/components/alert-feedback.blade.php']) {
        const source = await readFile(new URL('../../' + file, import.meta.url), 'utf8');
        assert.doesNotMatch(source, /Action completed|Successfully Saved|Operation Successful|Changes Saved/);
    }
});
