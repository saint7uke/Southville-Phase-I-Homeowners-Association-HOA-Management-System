<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

$connection = (string) (getenv('DB_CONNECTION') ?: 'sqlite');
$database = (string) (getenv('DB_DATABASE') ?: ':memory:');

if ($connection === 'sqlite') {
    return;
}

$destructiveDatabaseOptIn = filter_var(
    getenv('HOA_ALLOW_DESTRUCTIVE_TEST_DATABASE') ?: false,
    FILTER_VALIDATE_BOOL,
);
$hasDisposableName = preg_match('/(?:^|[_-])tests?(?:$|[_-])/i', $database) === 1;

if (! $destructiveDatabaseOptIn || ! $hasDisposableName) {
    throw new RuntimeException(
        'Refusing non-SQLite tests. Set HOA_ALLOW_DESTRUCTIVE_TEST_DATABASE=true '
        .'and use a database name containing test or tests.',
    );
}
