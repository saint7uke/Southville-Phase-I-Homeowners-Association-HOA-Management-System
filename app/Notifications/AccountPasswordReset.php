<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class AccountPasswordReset extends Notification
{
    public function __construct(
        private readonly string $resetUrl,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Reset your Southville HOA account password'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->full_name]))
            ->line(__('Use this secure link to reset your HOA account password.'))
            ->action(__('Reset password'), $this->resetUrl)
            ->line(__('This link will expire in :minutes minutes.', ['minutes' => config('auth.passwords.users.expire', 60)]))
            ->line(__('If you did not request this reset, no action is required.'));
    }
}
