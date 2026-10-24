<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\ContactMessageReplyMail;
use App\Models\ContactMessageActivity;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Mail;
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

        Mail::to($activity->recipient_email)->send(new ContactMessageReplyMail($activity));
        ContactMessageActivity::query()->whereKey($activity->id)->where('delivery_status', 'Queued')->update([
            'delivery_status' => 'Sent', 'sent_at' => now(), 'failed_at' => null, 'failure_code' => null, 'updated_at' => now(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        ContactMessageActivity::query()->whereKey($this->activityId)->where('delivery_status', 'Queued')->update([
            'delivery_status' => 'Failed', 'failed_at' => now(), 'failure_code' => 'transport_failure', 'updated_at' => now(),
        ]);
    }
}
