<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\DeliverWorkflowMail;
use App\Models\EmailDelivery;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications as Notices;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class WorkflowMail
{
    public const TYPES = [Notices\RegistrationReceived::class, Notices\NewHomeownerRegistration::class,
        Notices\HomeownerAccountStatusChanged::class, Notices\WorkflowStatusChanged::class,
        Notices\OperationalAlert::class, Notices\CertificateIssued::class, Notices\PaymentReviewCompleted::class,
        Notices\OverdueDuesNotice::class, Notices\HomeownerDelinquencyNotice::class];

    public function queue(User $recipient, Notification $notification, string $event): EmailDelivery
    {
        if (! in_array($notification::class, self::TYPES, true)) {
            throw new \InvalidArgumentException('Unsupported tracked notification.');
        }
        $delivery = EmailDelivery::query()->firstOrCreate([
            'event_key' => hash('sha256', $event.'|'.$notification::class),
            'user_id' => $recipient->id, 'channel' => 'mail',
        ], ['type' => class_basename($notification), 'payload' => serialize($notification)]);

        if ($delivery->wasRecentlyCreated) {
            DB::afterCommit(fn () => $this->dispatch($delivery->id));
        }

        return $delivery;
    }

    public function dispatch(int $id): void
    {
        try {
            DeliverWorkflowMail::dispatch($id);
        } catch (\Throwable) {
            // The committed ledger row remains recoverable even if the queue is unavailable.
            Log::warning('Workflow email could not be queued.', ['delivery_id' => $id, 'failure_code' => 'queue_unavailable']);
        }
    }

    public function eligible(User $recipient, Notification $notification): bool
    {
        if ($recipient->trashed() || ! filter_var($recipient->email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        if ($notification instanceof Notices\NewHomeownerRegistration) {
            return $recipient->account_status === 'Active' && $recipient->hasRole('hoa_admin') && $recipient->can('approve_homeowner_registrations');
        }
        if ($notification instanceof Notices\RegistrationReceived || $notification instanceof Notices\HomeownerAccountStatusChanged) {
            return $recipient->hasRole('homeowner');
        }
        if ($notification instanceof Notices\OperationalAlert) {
            return $notification->recipientCanReceive($recipient);
        }
        if ($recipient->account_status !== 'Active' || ! $recipient->hasVerifiedEmail() || ! $recipient->hasRole('homeowner')
            || ! in_array($recipient->homeowner?->status, ['Active', 'Delinquent'], true)) {
            return false;
        }
        $settings = SystemSetting::current();

        return match (true) {
            $notification instanceof Notices\WorkflowStatusChanged => $notification->enabled() && $notification->recipientCanReceive($recipient),
            $notification instanceof Notices\CertificateIssued => (bool) $settings->certificate_notifications && $notification->shouldSend($recipient, 'mail'),
            $notification instanceof Notices\PaymentReviewCompleted => (bool) $settings->payment_notifications && $notification->recipientCanReceive($recipient),
            $notification instanceof Notices\OverdueDuesNotice => (bool) $settings->delinquency_notifications && $notification->recipientCanReceive($recipient),
            $notification instanceof Notices\HomeownerDelinquencyNotice => (bool) $settings->delinquency_notifications && $recipient->homeowner?->status === 'Delinquent',
            default => false,
        };
    }
}
