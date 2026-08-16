<?php

declare(strict_types=1);

namespace App\Enums;

enum PanelId: string
{
    case Admin = 'admin';
    case Staff = 'staff';
    case Homeowner = 'homeowner';
    case Portal = 'portal';

    public static function fromGuard(string $guard): ?self
    {
        return match ($guard) {
            'admin' => self::Admin,
            'staff' => self::Staff,
            'homeowner' => self::Homeowner,
            'web' => self::Portal,
            default => null,
        };
    }
}
