<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class WorkflowStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $recordType, private readonly string $reference, private readonly string $status, private readonly ?string $remarks = null)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(__('HOA :type update', ['type' => __($this->recordType)]))
            ->greeting(__('Hello :name.', ['name' => $notifiable->full_name]))
            ->line(__('Your :type :reference is now :status.', ['type' => __($this->recordType), 'reference' => $this->reference, 'status' => __($this->status)]));

        if (filled($this->remarks)) {
            $message->line(__('HOA remarks:').' '.$this->remarks);
        }

        return $message->action(__('Open homeowner panel'), url('/homeowner'));
    }
}
