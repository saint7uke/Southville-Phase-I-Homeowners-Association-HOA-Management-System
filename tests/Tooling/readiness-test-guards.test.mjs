import { test } from 'node:test';
import assert from 'node:assert/strict';
import { assertReadinessArguments, assertReadinessDatabaseTarget } from '../../scripts/lib/readiness-test-guards.mjs';

const safe = { host: '127.0.0.1', port: 33379, database: 'hoa_certificate_dues_tests_gap_012345abcdef', destructiveOptIn: true };
test('readiness runner accepts only a dedicated loopback test database with explicit destructive opt-in', () => {
    assert.doesNotThrow(() => assertReadinessDatabaseTarget(safe));
    for (const change of [{ host: 'example.com' }, { host: 'localhost' }, { host: '0.0.0.0' },
        { database: 'production' }, { database: 'hoa_tests' }, { database: `${safe.database};DROP DATABASE production` },
        { destructiveOptIn: false }, { destructiveOptIn: 'true' }, { port: 0 }, { port: '33379' }]) {
        assert.throws(() => assertReadinessDatabaseTarget({ ...safe, ...change }));
    }
});
test('readiness runner rejects conflicting modes and arbitrary commands', () => {
    for (const args of [[], ['--sqlite-only'], ['--mysql-only'], ['--payment-settlements']]) assert.doesNotThrow(() => assertReadinessArguments(args));
    for (const args of [['--sqlite-only', '--mysql-only'], ['--production'], ['--host=example.com']]) {
        assert.throws(() => assertReadinessArguments(args));
    }
});
