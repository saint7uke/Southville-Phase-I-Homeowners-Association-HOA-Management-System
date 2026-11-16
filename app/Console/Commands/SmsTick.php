<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SmsBatch;
use App\Models\SmsDelivery;
use App\Models\SmsVerification;
use App\Models\SystemSetting;
use App\Services\Sms\SmsBudget;
use App\Services\Sms\SmsEligibility;
use App\Services\Sms\SmsReminders;
use App\Services\Sms\SmsSynchronizer;
use App\Services\Sms\WorkflowSms;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class SmsTick extends Command
{
    protected $signature = 'hoa:sms-tick {--dry-run : Inspect due reminder counts without changing data or contacting TextBee}';

    protected $description = 'SMS-only maintenance; never consumes default/email queues or invokes the global scheduler';

    public function handle(): int
    {
        if ($this->option('dry-run')) {
            $this->line(json_encode(app(SmsReminders::class)->generate(true), JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        return Cache::lock('hoa:sms-tick', 120)->get(function (): int {
            // Content expiry/pruning applies even while sending is disabled.
            SmsVerification::where('expires_at', '<', now())->update(['code_hash' => null, 'phone' => null]);
            SmsDelivery::where('type', 'otp')->where('created_at', '<', now()->subMinutes(5))->update(['body' => null]);
            SmsDelivery::where('created_at', '<', now()->subDays(config('textbee.retention_days')))->update(['body' => null, 'phone' => null, 'context' => null, 'user_ids' => '[]']);
            SmsVerification::where('created_at', '<', now()->subDays(config('textbee.retention_days')))->update(['binding_hash' => str_repeat('0', 64)]);
            SmsBatch::where('status', 'Queued')->where('expires_at', '<', now())->orderBy('id')->each(function ($batch): void {
                DB::transaction(function () use ($batch): void {
                    $locked = SmsBatch::lockForUpdate()->find($batch->id);
                    if ($locked?->status !== 'Queued') {
                        return;
                    }
                    $rows = SmsDelivery::where('batch_id', $locked->id)->where('status', 'Queued')->orderBy('id')->lockForUpdate()->get();
                    foreach ($rows as $row) {
                        app(SmsBudget::class)->release($row);
                        $row->update(['status' => 'Expired', 'body' => null, 'failure_code' => 'expired_locally']);
                    }
                    $locked->update(['status' => 'Expired', 'failure_code' => 'expired_locally']);
                });
            });
            SmsBatch::where('status', 'Sending')->where('started_at', '<', now()->subMinutes(2))->orderBy('id')->each(function ($batch): void {
                DB::transaction(function () use ($batch): void {
                    $locked = SmsBatch::lockForUpdate()->find($batch->id);
                    if ($locked?->status === 'Sending') {
                        $locked->update(['status' => 'Uncertain', 'failure_code' => 'stale_submission']);
                        SmsDelivery::where('batch_id', $batch->id)->where('status', 'Sending')->update(['status' => 'Uncertain', 'failure_code' => 'stale_submission']);
                    }
                });
            });
            if (! app(SmsEligibility::class)->enabled()) {
                $this->info('SMS sending disabled. Content retention only; no provider requests.');

                return self::SUCCESS;
            }
            $ok = true;
            // Process opt-outs before collecting or releasing outgoing notifications.
            try {
                $sync = app(SmsSynchronizer::class);
                $sync->incoming();
                $day = now('Asia/Manila')->toDateString();
                if (DB::table('sms_sync_states')->where('key', 'incoming-overlap-day')->value('value') !== $day) {
                    $sync->incoming(true);
                    DB::table('sms_sync_states')->updateOrInsert(['key' => 'incoming-overlap-day'], ['value' => $day, 'updated_at' => now(), 'created_at' => now()]);
                }
                $sync->outgoing();
            } catch (\Throwable) {
                $ok = false;
                Log::warning('SMS synchronization unavailable.', ['failure_code' => 'sms_sync_unavailable']);
                $this->warn('SMS synchronization unavailable; message content and provider errors are not logged.');
            }
            // Fail closed for new notifications when enabled incoming opt-outs cannot be read.
            if ($ok || ! SystemSetting::current()->sms_incoming_enabled) {
                $this->line(json_encode(app(SmsReminders::class)->generate(), JSON_THROW_ON_ERROR));
                SmsBatch::where('status', 'Queued')->where(fn ($query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
                    ->orderBy('id')->limit(200)->get()->each(fn ($batch) => app(WorkflowSms::class)->dispatch($batch->id));
            }

            return $ok ? self::SUCCESS : self::FAILURE;
        }) ?? self::SUCCESS;
    }
}
