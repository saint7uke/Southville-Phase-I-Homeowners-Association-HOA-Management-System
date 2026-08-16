<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Support\Facades\Auth;

final class AuthenticatePanel extends Authenticate
{
    /** @param array<string> $guards */
    protected function authenticate($request, array $guards): void
    {
        if (! Filament::auth()->check()) {
            $currentGuard = Filament::getAuthGuard();

            foreach (['web', 'admin', 'staff', 'homeowner'] as $guard) {
                if ($guard !== $currentGuard && Auth::guard($guard)->check()) {
                    abort(403);
                }
            }
        }

        parent::authenticate($request, $guards);
    }
}
