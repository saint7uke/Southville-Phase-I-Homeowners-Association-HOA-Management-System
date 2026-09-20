<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserAccountStatusChanged;
use App\Notifications\HomeownerAccountStatusChanged;

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
            $event->user->sendEmailVerificationNotification();
        }
    }
}
