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
            ->subject('HOA account delinquency notice')
            ->greeting('Hello '.$notifiable->full_name.',')
            ->line("Your HOA account has unpaid obligations for at least {$this->consecutiveMonths} consecutive months and is now marked delinquent.")
            ->line('Please review your outstanding dues and submit payment proof or contact the HOA office for assistance.')
            ->action('Review outstanding dues', url('/homeowner/dues/dues-obligations'));
    }
}
