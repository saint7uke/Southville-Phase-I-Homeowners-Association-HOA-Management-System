<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class PaymentReviewCompleted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly Payment $payment)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $approved = $this->payment->review_status === 'Approved';

        return (new MailMessage)
            ->subject($approved ? 'Payment proof approved' : 'Payment proof rejected')
            ->line("Payment {$this->payment->or_number} was {$this->payment->review_status}.")
            ->when(! $approved && filled($this->payment->review_notes), fn (MailMessage $mail) => $mail->line('Reason: '.$this->payment->review_notes))
            ->action('View payments', url('/homeowner/payments'));
    }
}
