<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class OverdueDuesNotice extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $scheduleName, private readonly string $dueDate) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('HOA dues payment is overdue'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->full_name]))
            ->line(__('Your :schedule obligation due :date is now overdue.', ['schedule' => $this->scheduleName, 'date' => $this->dueDate]))
            ->line(__('Please review the balance and contact the HOA office to pay in cash.'))
            ->action(__('Review outstanding dues'), url('/homeowner/dues/dues-obligations'));
    }
}
