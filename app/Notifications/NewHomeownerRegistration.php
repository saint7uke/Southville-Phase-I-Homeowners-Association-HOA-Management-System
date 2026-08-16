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
        return (new MailMessage)->subject('New resident application')->greeting('Hello '.$notifiable->first_name.',')->line($this->resident->full_name.' submitted a Southville Phase I resident application.')->action('Review application', url('/admin/users'))->line('Review the resident details before activating the account.');
    }
}
