<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class HomeownerDelinquencyNotice extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly int $consecutiveMonths) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('HOA account delinquency notice'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->full_name]))
            ->line(__('Your HOA account has unpaid obligations for at least :months consecutive months and is now marked delinquent.', ['months' => $this->consecutiveMonths]))
            ->line(__('Please review your outstanding dues and contact the HOA office to pay in cash.'))
            ->action(__('Review outstanding dues'), url('/homeowner/dues/dues-obligations'));
    }
}
