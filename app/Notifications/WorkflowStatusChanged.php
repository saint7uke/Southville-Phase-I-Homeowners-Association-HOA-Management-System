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
            ->subject("HOA {$this->recordType} update")
            ->greeting('Hello '.$notifiable->full_name.'.')
            ->line("Your {$this->recordType} {$this->reference} is now {$this->status}.");

        if (filled($this->remarks)) {
            $message->line('HOA remarks: '.$this->remarks);
        }

        return $message->action('Open homeowner panel', url('/homeowner'));
    }
}
