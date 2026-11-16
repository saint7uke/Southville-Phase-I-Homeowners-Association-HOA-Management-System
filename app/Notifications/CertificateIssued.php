<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Filament\Homeowner\Resources\ServiceRequests\ServiceRequestResource;
use App\Models\Certificate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

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

        return $this->certificate !== null && $this->certificate->status === 'Issued'
            && $this->certificate->homeowner?->user_id === $notifiable->id;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $certificate = $this->certificate ?? Certificate::query()->findOrFail($this->certificateId);
        $url = ServiceRequestResource::getUrl('index', panel: 'homeowner');

        return (new MailMessage)
            ->subject(__('Your HOA certificate has been issued'))
            ->greeting(__('Hello :name.', ['name' => $notifiable->full_name]))
            ->line(__(':type :number has been issued.', ['type' => $certificate->type, 'number' => $certificate->certificate_number]))
            ->line(__('Certificates must be collected at the HOA office with the required signature and dry seal.'))
            ->line(__('Check your certificate request for pickup readiness before visiting the office.'))
            ->action(__('Track certificate request'), $url);
    }
}
