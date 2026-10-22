<?php

declare(strict_types=1);

namespace App\Filament\Auth\Pages;

use App\Services\UnifiedAuthentication;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Actions\Action;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    public function mount(): void
    {
        $this->form->fill();
        $this->redirect(route('login'), navigate: false);
    }

    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }
        try {
            $url = app(UnifiedAuthentication::class)->authenticate($this->form->getState());
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['data.email' => $exception->errors()['email']]);
        }
        $this->redirect($url, navigate: false);

        return null;
    }

    protected string $view = 'filament.auth.login';

    protected static string $portalTitle = 'Admin Login';

    protected static string $portalSubtitle = 'Sign in to manage your HOA administration.';

    protected static string $portalLabel = 'Administrator portal';

    protected static string $portalAction = 'Admin Login';

    protected static string $portalEyebrow = 'Southville Phase I HOA';

    protected static string $portalWelcome = 'A clear, secure way to manage community services.';

    protected static string $portalSupport = 'Use your HOA credentials to continue.';

    public function getTitle(): string|Htmlable
    {
        return static::$portalTitle;
    }

    public function getHeading(): string|Htmlable|null
    {
        return static::$portalTitle;
    }

    public function getSubheading(): string|Htmlable|null
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
