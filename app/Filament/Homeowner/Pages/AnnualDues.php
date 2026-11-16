<?php

declare(strict_types=1);

namespace App\Filament\Homeowner\Pages;

use App\Models\Homeowner;
use Filament\Facades\Filament;

final class AnnualDues extends \App\Filament\Pages\AnnualDues
{
    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user !== null && ! $user->trashed() && $user->account_status === 'Active'
            && $user->hasRole('homeowner') && $user->hasVerifiedEmail() && $user->can('view_own_payments') && $user->homeowner()->exists();
    }

    protected function resident(): ?Homeowner
    {
        abort_unless(self::canAccess(), 403);

        return Filament::auth()->user()->homeowner;
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
