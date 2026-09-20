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
            ->subject('HOA dues payment is overdue')
            ->greeting('Hello '.$notifiable->full_name.',')
            ->line("Your {$this->scheduleName} obligation due {$this->dueDate} is now overdue.")
            ->line('Please review the balance and submit payment proof or contact the HOA office for assistance.')
            ->action('Review outstanding dues', url('/homeowner/dues/dues-obligations'));
    }
}
