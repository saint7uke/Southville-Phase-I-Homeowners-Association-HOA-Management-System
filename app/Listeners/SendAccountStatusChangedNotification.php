<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserAccountStatusChanged;
use App\Notifications\HomeownerAccountStatusChanged;
use Illuminate\Contracts\Queue\ShouldQueue;

final class SendAccountStatusChangedNotification implements ShouldQueue
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
    }
}
