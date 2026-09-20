<?php

declare(strict_types=1);

namespace App\Filament\Homeowner\Widgets;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

final class HomeownerWelcome extends Widget
{
    protected string $view = 'filament.homeowner.widgets.welcome';

    protected int|string|array $columnSpan = 'full';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $user = Filament::auth()->user();
        $homeowner = $user instanceof User ? $user->homeowner()->first() : null;

        return ['user' => $user, 'homeowner' => $homeowner];
    }
}
