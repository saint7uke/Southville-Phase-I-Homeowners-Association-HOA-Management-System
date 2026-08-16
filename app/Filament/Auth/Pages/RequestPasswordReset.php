<?php

declare(strict_types=1);

namespace App\Filament\Auth\Pages;

use Filament\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Auth\Events\PasswordResetLinkSent;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use LogicException;
use SensitiveParameter;

final class RequestPasswordReset extends BaseRequestPasswordReset
{
    public function request(): void
    {
        $data = $this->form->getState();
        $key = $this->rateLimitKey();

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $this->form->fill();
            $this->rateLimitedNotification()?->send();

            return;
        }

        RateLimiter::hit($key, 60);

        Password::broker(Filament::getAuthPasswordBroker())->sendResetLink(
            $this->getCredentialsFromFormData($data),
            function (CanResetPassword $user, #[SensitiveParameter] string $token): void {
                if (
                    method_exists($user, 'canAccessPanel')
                    && ! $user->canAccessPanel(Filament::getCurrentOrDefaultPanel())
                ) {
                    return;
                }

                if (! method_exists($user, 'notify')) {
                    throw new LogicException('The password-reset recipient must support notifications.');
                }

                $notification = app(ResetPasswordNotification::class, ['token' => $token]);
                $notification->url = Filament::getResetPasswordUrl($token, $user);
                $user->notify($notification);
                event(new PasswordResetLinkSent($user));
            },
        );

        $this->form->fill();
        $this->genericNotification()->send();
    }

    protected function getFailureNotification(string $status): ?Notification
    {
        return $this->genericNotification();
    }

    protected function getSentNotification(string $status): ?Notification
    {
        return $this->genericNotification();
    }

    private function genericNotification(): Notification
    {
        return Notification::make()
            ->title('Check your email')
            ->body('If an eligible account matches that address, a password reset link will be sent shortly.')
            ->success();
    }

    private function rateLimitedNotification(): Notification
    {
        return Notification::make()
            ->title('Too many requests')
            ->body('Please wait before requesting another password reset link.')
            ->danger();
    }

    private function rateLimitKey(): string
    {
        $panel = Filament::getCurrentOrDefaultPanel()->getId();

        return 'password-reset-request:'.$panel.':'.request()->ip();
    }
}
