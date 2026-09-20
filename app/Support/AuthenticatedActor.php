<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;

final class AuthenticatedActor
{
    public function user(): ?User
    {
        $panelGuard = Filament::getCurrentPanel()?->getAuthGuard();
        $guard = $panelGuard ?? (string) config('auth.defaults.guard', 'web');
        $user = Auth::guard($guard)->user();

        return $user instanceof User ? $user : null;
    }

    public function id(): ?int
    {
        return $this->user()?->getKey();
    }

    public function panel(): ?string
    {
        $panel = Filament::getCurrentPanel()?->getId();

        if ($panel !== null) {
            return $panel;
        }

        if (app()->runningInConsole()) {
            return 'system';
        }

        $prefix = request()->segment(1);

        return in_array($prefix, ['admin', 'staff', 'homeowner', 'portal'], true) ? $prefix : 'public';
    }
}
