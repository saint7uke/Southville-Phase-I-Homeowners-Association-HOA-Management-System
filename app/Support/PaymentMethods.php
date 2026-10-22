<?php

declare(strict_types=1);

namespace App\Support;

final class PaymentMethods
{
    public const CASH = 'Cash';

    public const CASH_ONLY_MESSAGE = 'The HOA accepts cash payments at the office only. Online payment proof submissions are no longer accepted.';

    /** @return list<string> */
    public static function accepted(): array
    {
        return [self::CASH];
    }

    public static function acceptsNewProofs(): bool
    {
        return false;
    }
}
