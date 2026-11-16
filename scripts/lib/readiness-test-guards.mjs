export function assertReadinessDatabaseTarget({ host, port, database, destructiveOptIn }) {
    if (host !== '127.0.0.1' || !Number.isInteger(port) || port < 1 || port > 65535
        || !/^hoa_certificate_dues_tests_gap_[a-f0-9]{12}$/.test(database) || destructiveOptIn !== true) {
        throw new Error('Refusing a non-loopback or non-disposable readiness database target.');
    }
}

export function assertReadinessArguments(args) {
    if (args.length > 1 || args.some(arg => !['--sqlite-only', '--mysql-only', '--payment-settlements'].includes(arg))) {
        throw new Error('Usage: node scripts/run-readiness-gap-checks.mjs [--sqlite-only|--mysql-only|--payment-settlements]');
    }
}
