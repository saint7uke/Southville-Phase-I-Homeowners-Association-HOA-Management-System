<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class OperationalAccess
{
    public static function allows(?User $actor, string $permission, bool $adminOnly = false): bool
    {
        return $actor !== null && ! $actor->trashed() && $actor->account_status === 'Active'
            && ($adminOnly ? $actor->hasRole('hoa_admin') : $actor->hasAnyRole(['hoa_admin', 'hoa_staff']))
            && $actor->can($permission);
    }

    public static function authorize(?User $actor, string $permission, bool $adminOnly = false): void
    {
        if (! self::allows($actor?->fresh(), $permission, $adminOnly)) {
            throw new AuthorizationException;
        }
    }
}
