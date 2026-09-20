<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class AnnouncementPublished extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $title)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New HOA announcement')
            ->greeting('Hello '.$notifiable->full_name.'.')
            ->line($this->title)
            ->action('Read announcements', url('/homeowner/announcements'));
    }
}
