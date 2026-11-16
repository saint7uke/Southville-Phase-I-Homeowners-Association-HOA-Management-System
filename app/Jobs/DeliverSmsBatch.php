<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\SmsBatch;
use App\Models\SmsDelivery;
use App\Models\SystemSetting;
use App\Services\Sms\SmsBudget;
use App\Services\Sms\SmsEligibility;
use App\Services\Sms\SmsGatewayException;
use App\Services\Sms\TextBeeClient;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class DeliverSmsBatch implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public readonly int $batchId) {}

    public function handle(TextBeeClient $client, SmsEligibility $eligibility, SmsBudget $budget): void
    {
        $deliveries = DB::transaction(function () use ($eligibility, $budget) {
            $batch = SmsBatch::query()->lockForUpdate()->find($this->batchId);
            if (! $batch || $batch->status !== 'Queued' || $batch->next_attempt_at?->isFuture()) {
                return collect();
            }
            $rows = SmsDelivery::where('batch_id', $batch->id)->where('status', 'Queued')->orderBy('id')->lockForUpdate()->get();
            if ($batch->expires_at->isPast() || ! $eligibility->enabled($batch->type)) {
                foreach ($rows as $row) {
                    $budget->release($row);
                    $row->update(['status' => $batch->expires_at->isPast() ? 'Expired' : 'Skipped', 'failure_code' => 'expired_or_disabled', 'body' => null]);
                }
                $batch->update(['status' => 'Skipped']);

                return collect();
            }
            $manila = now('Asia/Manila');
            if (SystemSetting::current()->sms_incoming_enabled) {
                $healthy = DB::table('sms_sync_states')->where('key', 'incoming-healthy')->value('updated_at');
                if (! $healthy || CarbonImmutable::parse($healthy)->lt(now()->subMinutes(2))) {
                    $batch->update(['next_attempt_at' => now()->addMinute(), 'failure_code' => 'opt_out_sync_required']);

                    return collect();
                }
            }
            if (! in_array($batch->type, ['otp', 'test'], true) && ($manila->hour < 9 || $manila->hour >= 18)) {
                $batch->update(['next_attempt_at' => $manila->hour < 9 ? $manila->copy()->setTime(9, 0) : $manila->copy()->addDay()->setTime(9, 0)]);

                return collect();
            }
            $eligible = $rows->filter(function ($row) use ($eligibility, $budget): bool {
                if ($eligibility->current($row)) {
                    return true;
                }
                $budget->release($row);
                $row->update(['status' => 'Skipped', 'failure_code' => 'recipient_or_event_changed', 'body' => null]);

                return false;
            });
            if ($eligible->isEmpty()) {
                $batch->update(['status' => 'Skipped']);

                return collect();
            }
            if (! $budget->reserve($eligible)) {
                $batch->update(['next_attempt_at' => $manila->copy()->addDay()->setTime(9, 0), 'failure_code' => 'local_budget_limit']);

                return collect();
            }
            $batch->update(['status' => 'Sending', 'started_at' => now(), 'attempts' => $batch->attempts + 1]);
            foreach ($eligible as $row) {
                $row->update(['status' => 'Sending', 'attempts' => $row->attempts + 1]);
            }

            return $eligible;
        }, 3);
        if ($deliveries->isEmpty()) {
            return;
        }
        try {
            $id = $client->submit($deliveries->map(fn ($row): array => ['phone' => $row->phone, 'body' => $row->body])->values()->all());
            DB::transaction(function () use ($id, $deliveries): void {
                SmsBatch::whereKey($this->batchId)->update(['status' => 'Accepted', 'provider_batch_id' => $id, 'accepted_at' => now(), 'failure_code' => null]);
                foreach ($deliveries as $row) {
                    $row->update(['status' => 'Accepted', 'accepted_at' => now(), 'failure_code' => null, ...($row->type === 'otp' ? ['body' => null] : [])]);
                }
            });
        } catch (SmsGatewayException $exception) {
            DB::transaction(function () use ($exception, $deliveries, $budget): void {
                $batch = SmsBatch::lockForUpdate()->findOrFail($this->batchId);
                $retry = $exception->definitive && $exception->retryable && $batch->attempts < 3 && $batch->expires_at->isFuture();
                $status = ! $exception->definitive ? 'Uncertain' : ($retry ? 'Queued' : 'Failed');
                $batch->update(['status' => $status, 'failure_code' => $exception->failure,
                    'next_attempt_at' => $retry ? now()->addSeconds([60, 300, 1800][$batch->attempts - 1]) : null]);
                foreach ($deliveries as $row) {
                    if ($exception->definitive) {
                        $budget->release($row);
                    }
                    $row->update(['status' => $status, 'failure_code' => $exception->failure]);
                }
            });
            Log::warning('SMS gateway submission requires attention.', ['sms_batch_id' => $this->batchId, 'failure_code' => $exception->failure]);
        } catch (\Throwable) {
            $this->uncertain('submission_interrupted');
        }
    }

    public function failed(?\Throwable $exception): void
    {
        $this->uncertain('worker_interrupted');
    }

    private function uncertain(string $code): void
    {
        SmsBatch::whereKey($this->batchId)->where('status', 'Sending')->update(['status' => 'Uncertain', 'failure_code' => $code]);
        SmsDelivery::where('batch_id', $this->batchId)->where('status', 'Sending')->update(['status' => 'Uncertain', 'failure_code' => $code]);
        Log::warning('SMS result uncertain.', ['sms_batch_id' => $this->batchId, 'failure_code' => $code]);
    }
}
