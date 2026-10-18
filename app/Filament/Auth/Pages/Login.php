<?php

declare(strict_types=1);

namespace App\Filament\Auth\Pages;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Actions\Action;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class Login extends BaseLogin
{
    protected string $view = 'filament.auth.login';

    protected static string $portalTitle = 'Admin Login';

    protected static string $portalSubtitle = 'Sign in to manage your HOA administration.';

    protected static string $portalLabel = 'Administrator portal';

    protected static string $portalAction = 'Admin Login';

    protected static string $portalEyebrow = 'Southville Phase I HOA';

    protected static string $portalWelcome = 'A clear, secure way to manage community services.';

    protected static string $portalSupport = 'Use your HOA credentials to continue.';

    public function getTitle(): string | Htmlable
    {
        return static::$portalTitle;
    }

    public function getHeading(): string | Htmlable | null
    {
        return static::$portalTitle;
    }

    public function getSubheading(): string | Htmlable | null
    {
        return new HtmlString(static::$portalSubtitle);
    }

    protected function getAuthenticateFormAction(): Action
    {
        return Action::make('authenticate')
            ->label(static::$portalAction)
            ->submit('authenticate');
    }

    public function getPortalLabel(): string
    {
        return static::$portalLabel;
    }

    public function getPortalSubtitle(): string
    {
        return static::$portalSubtitle;
    }

    public function getPortalEyebrow(): string
    {
        return static::$portalEyebrow;
    }

    public function getPortalWelcome(): string
    {
        return static::$portalWelcome;
    }

    public function getPortalSupport(): string
    {
        return static::$portalSupport;
    }

    public function getPortalAccent(): string
    {
        return match (static::$portalLabel) {
            'Staff portal' => 'staff',
            'Homeowner portal' => 'homeowner',
            default => 'admin',
        };
    }

    /** @return array<int, array{label: string, url: string}> */
    public function getPortalLinks(): array
    {
        return [
            ['label' => 'Homeowner Login', 'url' => route('homeowner.login')],
            ['label' => 'Staff Login', 'url' => route('filament.staff.auth.login')],
        ];
    }
}
