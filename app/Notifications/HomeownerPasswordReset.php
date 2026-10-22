<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class HomeownerPasswordReset extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $token) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Reset your Southville HOA password'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->full_name]))
            ->line(__('Use this secure link to set or reset your HOA account password. Account approval is still required before sign-in.'))
            ->action(__('Reset password'), route('homeowner.password.reset', [
                'token' => $this->token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]))
            ->line(__('This link will expire in 60 minutes. If you did not request this, no action is required.'));
    }
}
