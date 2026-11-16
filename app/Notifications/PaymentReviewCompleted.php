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

    private readonly Payment $payment;

    public function __construct(Payment $payment)
    {
        // Do not serialize loaded payer/account relations (including authentication data).
        $this->payment = $payment->withoutRelations();
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function recipientCanReceive(object $notifiable): bool
    {
        $current = Payment::query()->with(['homeowner', 'payer'])->find($this->payment->id);

        return $current !== null && in_array($current->review_status, ['Approved', 'Rejected'], true)
            && in_array($notifiable->id, [$current->homeowner?->user_id, $current->payer?->user_id], true);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $approved = $this->payment->review_status === 'Approved';

        return (new MailMessage)
            ->subject(__($approved ? 'Payment proof approved' : 'Payment proof rejected'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->full_name]))
            ->line(__('Payment :reference was :status.', ['reference' => $this->payment->or_number, 'status' => __($this->payment->review_status)]))
            ->markdown('notifications::email', ['details' => array_filter([
                __('Reference') => $this->payment->or_number,
                __('Status') => __($this->payment->review_status),
                __('Reason:') => ! $approved ? $this->payment->review_notes : null,
            ], fn ($value) => filled($value))])
            ->action(__('View payments'), url('/homeowner/payments'));
    }
}
