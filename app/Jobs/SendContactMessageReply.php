<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\ContactMessageReplyMail;
use App\Models\ContactMessageActivity;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Throwable;

final class SendContactMessageReply implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public readonly int $activityId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('contact-reply:'.$this->activityId))->releaseAfter(30)->expireAfter(90)];
    }

    public function handle(): void
    {
        $activity = ContactMessageActivity::query()->with('contactMessage')->findOrFail($this->activityId);
        if ($activity->delivery_status !== 'Queued' || $activity->contactMessage?->trashed()) {
            return;
        }

        if (! filter_var($activity->recipient_email, FILTER_VALIDATE_EMAIL)) {
            $activity->update(['delivery_status' => 'Failed', 'failure_code' => 'invalid_recipient', 'failed_at' => now()]);

            return;
        }
        if (! ContactMessageActivity::whereKey($activity->id)->where('delivery_status', 'Queued')->update(['delivery_status' => 'Sending', 'updated_at' => now()])) {
            return;
        }
        try {
            Mail::to($activity->recipient_email)->send(new ContactMessageReplyMail($activity));
        } catch (Throwable $exception) {
            $status = $exception instanceof UnexpectedResponseException ? 'Failed' : 'Uncertain';
            $activity->update(['delivery_status' => $status, 'failed_at' => now(), 'failure_code' => $status === 'Failed' ? 'smtp_rejected' : 'delivery_result_uncertain']);
            Log::warning('Contact reply requires review.', ['activity_id' => $activity->id, 'failure_code' => $status === 'Failed' ? 'smtp_rejected' : 'delivery_result_uncertain']);

            return;
        }
        ContactMessageActivity::query()->whereKey($activity->id)->where('delivery_status', 'Sending')->update([
            'delivery_status' => 'Sent', 'sent_at' => now(), 'failed_at' => null, 'failure_code' => null, 'updated_at' => now(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        ContactMessageActivity::query()->whereKey($this->activityId)->where('delivery_status', 'Sending')->update([
            'delivery_status' => 'Uncertain', 'failed_at' => now(), 'failure_code' => 'transport_failure', 'updated_at' => now(),
        ]);
    }
}
