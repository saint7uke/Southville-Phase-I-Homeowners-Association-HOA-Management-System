<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Filament\Homeowner\Resources\Dues\DuesObligationResource;
use App\Models\DuesObligation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class OverdueDuesNotice extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $scheduleName, private readonly string $dueDate, private readonly ?int $obligationId = null) {}

    public function recipientCanReceive(object $notifiable): bool
    {
        return $this->obligationId !== null && DuesObligation::query()->whereKey($this->obligationId)
            ->where('status', 'Overdue')->whereHas('homeowner', fn ($query) => $query->where('user_id', $notifiable->id))->exists();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('HOA dues payment is overdue'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->full_name]))
            ->line(__('Your :schedule obligation due :date is now overdue.', ['schedule' => $this->scheduleName, 'date' => $this->dueDate]))
            ->line(__('Please review the balance and contact the HOA office to pay in cash.'))
            ->action(__('Review outstanding dues'), DuesObligationResource::getUrl('index', panel: 'homeowner'));
    }
}
