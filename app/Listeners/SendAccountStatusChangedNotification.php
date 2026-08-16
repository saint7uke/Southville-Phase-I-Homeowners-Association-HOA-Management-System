<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserAccountStatusChanged;
use App\Notifications\HomeownerAccountStatusChanged;
use Filament\Auth\Notifications\VerifyEmail;
use Filament\Facades\Filament;

final class SendAccountStatusChangedNotification
{
    public function handle(UserAccountStatusChanged $event): void
    {
        if (! $event->user->hasRole('homeowner')) {
            return;
        }

        $event->user->notify(new HomeownerAccountStatusChanged(
            $event->to->value,
            $event->reason,
        ));

        if ($event->to->value === 'Active' && ! $event->user->hasVerifiedEmail()) {
            $notification = app(VerifyEmail::class);
            $notification->url = Filament::getPanel('homeowner')->getVerifyEmailUrl($event->user);
            $event->user->notify($notification);
        }
    }
}
