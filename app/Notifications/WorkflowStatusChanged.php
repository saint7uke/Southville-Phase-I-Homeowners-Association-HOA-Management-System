<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Complaint;
use App\Models\ServiceRequest;
use App\Models\SystemSetting;
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

    public function enabled(): bool
    {
        $settings = SystemSetting::current();

        return $this->recordType === 'complaint' ? (bool) $settings->complaint_notifications : (bool) $settings->request_notifications;
    }

    public function recipientCanReceive(object $notifiable): bool
    {
        $model = $this->recordType === 'complaint' ? Complaint::class : ServiceRequest::class;

        return $model::query()->where('ticket_number', $this->reference)
            ->whereHas('homeowner', fn ($query) => $query->where('user_id', $notifiable->id))->exists();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject(__('HOA :type update', ['type' => __($this->recordType)]))
            ->greeting(__('Hello :name.', ['name' => $notifiable->full_name]))
            ->line(__('Your :type :reference is now :status.', ['type' => __($this->recordType), 'reference' => $this->reference, 'status' => __($this->status)]));

        $message->markdown('notifications::email', ['details' => array_filter([
            __('Reference') => $this->reference,
            __('Status') => __($this->status),
            __('HOA remarks:') => $this->remarks,
        ], fn ($value) => filled($value))]);

        return $message->action(__('Open homeowner panel'), url('/homeowner'));
    }
}
