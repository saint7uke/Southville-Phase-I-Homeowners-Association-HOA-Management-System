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

    public function __construct(private readonly int $certificateId)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $certificate = Certificate::query()->findOrFail($this->certificateId);
        $url = URL::temporarySignedRoute('certificates.download', now()->addMinutes(30), $certificate);

        return (new MailMessage)
            ->subject('Your HOA certificate is ready')
            ->greeting('Hello '.$notifiable->full_name.'.')
            ->line("{$certificate->type} {$certificate->certificate_number} has been issued.")
            ->line('The secure download link expires in 30 minutes and requires your homeowner sign-in.')
            ->action('Download certificate', $url);
    }
}
