<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\EmailDelivery;
use App\Models\User;
use App\Services\WorkflowMail;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\UnexpectedResponseException;

final class DeliverWorkflowMail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public readonly int $deliveryId) {}

    public function handle(WorkflowMail $mail): void
    {
        $delivery = DB::transaction(function (): ?EmailDelivery {
            $row = EmailDelivery::query()->lockForUpdate()->find($this->deliveryId);
            if (! $row || $row->status !== 'Queued') {
                return null;
            }
            $row->update(['status' => 'Sending', 'started_at' => now(), 'attempts' => $row->attempts + 1]);

            return $row;
        });
        if (! $delivery) {
            return;
        }
        try {
            $notification = unserialize($delivery->payload);
            if (! is_object($notification) || ! in_array($notification::class, WorkflowMail::TYPES, true)) {
                throw new \RuntimeException('Unsupported notification payload.');
            }
            $user = User::query()->find($delivery->user_id);
            if (! $user || ! $mail->eligible($user, $notification)) {
                $delivery->update(['status' => 'Skipped', 'failure_code' => 'recipient_or_event_ineligible']);

                return;
            }
            $user->notifyNow($notification);
            $delivery->update(['status' => 'Accepted', 'accepted_at' => now(), 'failure_code' => null]);
        } catch (UnexpectedResponseException) {
            $delivery->update(['status' => 'Failed', 'failed_at' => now(), 'failure_code' => 'smtp_rejected']);
            Log::warning('Workflow email rejected.', ['delivery_id' => $delivery->id, 'failure_code' => 'smtp_rejected']);
        } catch (\Throwable) {
            // SMTP may have accepted a message before the connection or database failed.
            // Never automatically repeat an ambiguous mutation.
            EmailDelivery::query()->whereKey($delivery->id)->update(['status' => 'Uncertain', 'failure_code' => 'delivery_result_uncertain']);
            Log::warning('Workflow email requires delivery review.', ['delivery_id' => $delivery->id, 'failure_code' => 'delivery_result_uncertain']);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        EmailDelivery::query()->whereKey($this->deliveryId)->where('status', 'Sending')
            ->update(['status' => 'Uncertain', 'failure_code' => 'worker_interrupted']);
    }
}
