<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class NewHomeownerRegistration extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly User $resident) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject(__('New resident application'))->greeting(__('Hello :name,', ['name' => $notifiable->first_name]))->line(__(':name submitted a Southville Phase I resident application.', ['name' => $this->resident->full_name]))->action(__('Review application'), url('/admin/users'))->line(__('Review the resident details before activating the account.'));
    }
}
