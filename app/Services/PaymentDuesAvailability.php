<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DuesObligation;

/** Read-only explanation of the existing payer policy, not a new payment policy. */
final class PaymentDuesAvailability
{
    public const ARCHIVED = 'Archived homeowner—HOA review required';

    public const NO_PAYER = 'No eligible payer—HOA review required';

    public const PORTAL_NOTICE = 'An Active portal account does not override an archived homeowner record. HOA review is required before any restoration.';

    public function reason(?DuesObligation $obligation): ?string
    {
        $liable = $obligation?->homeowner;
        if ($liable && app(PropertyDuesAccounts::class)->eligiblePayers($liable)->exists()) {
            return null;
        }

        return $liable?->trashed() ? self::ARCHIVED : self::NO_PAYER;
    }
}
