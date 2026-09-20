<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateProfilePhoto
{
    public function handle(Request $request, Closure $next): Response
    {
        foreach (['admin', 'staff', 'homeowner', 'web'] as $guard) {
            if (Auth::guard($guard)->check()) {
                Auth::shouldUse($guard);

                return $next($request);
            }
        }

        return redirect()->guest(route('filament.homeowner.auth.login'));
    }
}
