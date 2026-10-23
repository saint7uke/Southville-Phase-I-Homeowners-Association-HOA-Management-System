<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class OperationalAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $subject, private readonly string $message, private readonly string $url, private readonly array $parameters = [], private readonly ?string $permission = null)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return $this->recipientCanReceive($notifiable) ? ['mail'] : [];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return $channel === 'mail' && $this->recipientCanReceive($notifiable);
    }

    public function recipientCanReceive(object $notifiable): bool
    {
        // Derive older queued payloads only from known module destinations. Unknown
        // payloads fail closed; never trust a stale serialized role/permission relation.
        $permission = $this->permission ?? match (parse_url($this->url, PHP_URL_PATH)) {
            '/staff/complaints' => 'manage_complaints',
            '/staff/service-requests' => 'manage_requests',
            '/staff/contact-messages' => 'manage_contact_messages',
            default => null,
        };
        if (! in_array($permission, ['manage_complaints', 'manage_requests', 'manage_contact_messages'], true) || ! $notifiable instanceof User) {
            return false;
        }
        $recipient = User::query()->find($notifiable->getKey());

        return $recipient !== null && $recipient->account_status === 'Active'
            && $recipient->hasRole('hoa_staff') && $recipient->can($permission);
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject(__($this->subject))->line(__($this->message, $this->parameters ?? []))->action(__('Open staff panel'), $this->url);
    }
}
