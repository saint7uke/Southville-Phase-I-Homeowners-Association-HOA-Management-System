<?php

declare(strict_types=1);

namespace App\Enums;

enum UserAccountStatus: string
{
    case Pending = 'Pending';
    case Active = 'Active';
    case Rejected = 'Rejected';
    case Inactive = 'Inactive';
    case Suspended = 'Suspended';

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $status): array => [$status->value => $status->value])
            ->all();
    }
}
