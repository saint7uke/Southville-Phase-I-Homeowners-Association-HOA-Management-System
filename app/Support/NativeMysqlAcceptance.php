<?php

declare(strict_types=1);

namespace App\Support;

use DomainException;

final class NativeMysqlAcceptance
{
    public static function assertDisposable(string $connection, string $database, bool $optIn): void
    {
        if ($connection !== 'mysql' || ! $optIn || preg_match('/(?:^|[_-])tests?(?:$|[_-])/i', $database) !== 1) {
            throw new DomainException('Native acceptance requires the mysql driver, a separately named test database and HOA_ALLOW_DESTRUCTIVE_TEST_DATABASE=true.');
        }
    }

    public static function assertVersion(string $version): void
    {
        if (stripos($version, 'mariadb') !== false || preg_match('/^8\.4\.\d+(?:\D|$)/', $version) !== 1) {
            throw new DomainException('Native MySQL 8.4 is required. MariaDB and other MySQL versions do not satisfy this acceptance gate.');
        }
    }
}
