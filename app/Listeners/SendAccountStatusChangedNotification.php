<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserAccountStatusChanged;
use App\Notifications\HomeownerAccountStatusChanged;
use App\Services\WorkflowMail;
use Illuminate\Queue\InteractsWithQueue;

final class SendAccountStatusChangedNotification
{
    use InteractsWithQueue;

    public function handle(UserAccountStatusChanged $event): void
    {
        if (! $event->user->hasRole('homeowner')) {
            return;
        }

        // Existing CallQueuedListener payloads predate eventId. Their stable job UUID
        // identifies retries without creating a different delivery on each attempt.
        $eventId = $event->eventId ?? ($this->job?->uuid() ? 'legacy-job:'.$this->job->uuid() : null);
        if ($eventId === null) {
            throw new \RuntimeException('Account notification has no stable event identity.');
        }
        app(WorkflowMail::class)->queue($event->user, new HomeownerAccountStatusChanged(
            $event->to->value,
            $event->reason,
            $event->from->value,
        ), 'account-status:'.$eventId);
    }
}
