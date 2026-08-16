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
}
