<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\AnnouncementEmailDelivery;
use App\Models\SystemSetting;
use App\Notifications\AnnouncementPublished;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;
use Throwable;

final class SendAnnouncementEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public readonly int $deliveryId) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('announcement-email:'.$this->deliveryId))->releaseAfter(30)->expireAfter(90)];
    }

    public function handle(): void
    {
        $delivery = AnnouncementEmailDelivery::query()
            ->with(['announcement.creator', 'recipient.homeowner'])
            ->findOrFail($this->deliveryId);

        if ($delivery->status !== 'Queued') {
            return;
        }

        $announcement = $delivery->announcement;
        $recipient = $delivery->recipient;
        $eligible = $recipient !== null
            && ! $recipient->trashed()
            && $recipient->account_status === 'Active'
            && $recipient->hasRole('homeowner')
            && $recipient->homeowner?->status === 'Active'
            && filter_var($recipient->email, FILTER_VALIDATE_EMAIL) !== false;

        if (! SystemSetting::current()->announcement_notifications || $announcement === null || ! $announcement->published()->whereKey($announcement)->exists()) {
            $this->markFailed('announcement_unavailable');

            return;
        }
        if (! $eligible) {
            $this->markFailed('recipient_ineligible');

            return;
        }

        if (! AnnouncementEmailDelivery::whereKey($delivery->id)->where('status', 'Queued')->update(['status' => 'Sending', 'updated_at' => now()])) {
            return;
        }
        try {
            $recipient->notifyNow(new AnnouncementPublished($announcement));
        } catch (Throwable $exception) {
            $status = $exception instanceof UnexpectedResponseException ? 'Failed' : 'Uncertain';
            AnnouncementEmailDelivery::whereKey($delivery->id)->update(['status' => $status, 'failed_at' => now(), 'failure_code' => $status === 'Failed' ? 'smtp_rejected' : 'delivery_result_uncertain']);
            Log::warning('Announcement email requires review.', ['delivery_id' => $delivery->id, 'failure_code' => $status === 'Failed' ? 'smtp_rejected' : 'delivery_result_uncertain']);

            return;
        }
        AnnouncementEmailDelivery::query()->whereKey($delivery->id)->where('status', 'Sending')->update([
            'status' => 'Sent',
            'sent_at' => now(),
            'failed_at' => null,
            'failure_code' => null,
            'updated_at' => now(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        AnnouncementEmailDelivery::whereKey($this->deliveryId)->where('status', 'Sending')->update(['status' => 'Uncertain', 'failure_code' => 'worker_interrupted', 'failed_at' => now()]);
    }

    private function markFailed(string $code): void
    {
        AnnouncementEmailDelivery::query()->whereKey($this->deliveryId)->where('status', 'Queued')->update([
            'status' => 'Failed',
            'failed_at' => now(),
            'failure_code' => $code,
            'updated_at' => now(),
        ]);
    }
}
