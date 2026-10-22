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
        $message = (new MailMessage)->subject(__('Southville resident account update'))->greeting(__('Hello :name.', ['name' => $notifiable->first_name]));
        if ($this->status === 'Active') {
            return $message->line(__('Your resident application has been approved.'))->action(__('Open homeowner panel'), url('/homeowner/login'));
        }
        if ($this->status === 'Rejected') {
            return $message->line(__('Your resident application was not approved.'))->line($this->reason ? __('Reason:').' '.$this->reason : __('Contact the HOA office if you need assistance.'));
        }

        return $message->line(__('Your resident account status is now :status.', ['status' => __($this->status)]));
    }
}
