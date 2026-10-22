<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Certificate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

final class CertificateIssued extends Notification implements ShouldQueue
{
    use Queueable;

    private ?Certificate $certificate = null;

    public function __construct(private readonly int $certificateId)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        if ($channel !== 'mail') {
            return true;
        }

        $this->certificate = Certificate::query()->find($this->certificateId);

        return $this->certificate !== null;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $certificate = $this->certificate ?? Certificate::query()->findOrFail($this->certificateId);
        $url = URL::temporarySignedRoute('certificates.download', now()->addMinutes(30), $certificate);

        return (new MailMessage)
            ->subject(__('Your HOA certificate is ready'))
            ->greeting(__('Hello :name.', ['name' => $notifiable->full_name]))
            ->line(__(':type :number has been issued.', ['type' => $certificate->type, 'number' => $certificate->certificate_number]))
            ->line(__('The secure download link expires in 30 minutes and requires your homeowner sign-in.'))
            ->action(__('Download certificate'), $url);
    }
}
