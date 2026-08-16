<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class HomeownerAccountStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $status, private readonly ?string $reason = null) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)->subject('Southville resident account update')->greeting('Hello '.$notifiable->first_name.'.');
        if ($this->status === 'Active') {
            return $message->line('Your resident application has been approved.')->action('Open resident portal', route('portal.login'));
        }
        if ($this->status === 'Rejected') {
            return $message->line('Your resident application was not approved.')->line($this->reason ? 'Reason: '.$this->reason : 'Contact the HOA office if you need assistance.');
        }

        return $message->line('Your resident account status is now '.$this->status.'.');
    }
}
