<?php

declare(strict_types=1);

namespace App\Filament\Homeowner\Pages;

use App\Services\OwnHouseholdSummary;
use Filament\Facades\Filament;
use Filament\Pages\Page;

final class MyHousehold extends Page
{
    protected string $view = 'filament.homeowner.pages.my-household';

    protected static ?string $navigationLabel = 'My Household';

    protected static string|\UnitEnum|null $navigationGroup = 'My Home';

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user !== null && ! $user->trashed() && $user->account_status === 'Active' && $user->hasRole('homeowner') && $user->homeowner()->exists();
    }

    protected function getViewData(): array
    {
        return ['summary' => app(OwnHouseholdSummary::class)->forUser(Filament::auth()->user())];
    }
}
