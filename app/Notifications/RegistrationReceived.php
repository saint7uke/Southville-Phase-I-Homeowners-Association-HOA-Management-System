<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class RegistrationReceived extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject(__('Your HOA application was received'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->full_name]))
            ->line(__('We received your Southville Phase I HOA application. Its submission status is Pending HOA review.'))
            ->line(__('The HOA will email you when it approves or rejects your application. You do not need to register again.'))
            ->action(__('Open HOA website'), url('/login'));
    }
}
