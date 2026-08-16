<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class MigrateLegacyPanelSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $panel = Filament::getCurrentPanel();

        if ($panel === null) {
            return $next($request);
        }

        if (! in_array($panel->getId(), ['admin', 'staff'], true)) {
            return $next($request);
        }

        $panelGuard = Auth::guard($panel->getAuthGuard());

        if ($panelGuard->check()) {
            return $next($request);
        }

        $legacyUser = Auth::guard('web')->user();

        if ($legacyUser === null) {
            return $next($request);
        }

        if (! $legacyUser instanceof FilamentUser || ! $legacyUser->canAccessPanel($panel)) {
            return $next($request);
        }

        if ($panelGuard instanceof StatefulGuard) {
            $panelGuard->login($legacyUser);
        } else {
            $panelGuard->setUser($legacyUser);
        }

        Auth::guard('web')->logout();
        $request->session()->regenerate();

        return $next($request);
    }
}
