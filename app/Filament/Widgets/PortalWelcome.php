<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Support\TimeGreeting;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

final class PortalWelcome extends Widget
{
    protected static ?int $sort = -10;

    protected string $view = 'filament.widgets.portal-welcome';

    protected int|string|array $columnSpan = 'full';

    /** @return array<string, string|null> */
    protected function getViewData(): array
    {
        $panel = Filament::getCurrentPanel()?->getId() ?? 'admin';
        $user = Filament::auth()->user();

        return [
            'firstName' => $user?->first_name ?? $user?->name ?? 'there',
            'greeting' => TimeGreeting::current(),
            'panel' => $panel,
        ];
    }
}
