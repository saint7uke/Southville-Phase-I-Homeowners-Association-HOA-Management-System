<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\DeliverSmsBatch;
use App\Models\SmsBatch;
use App\Models\SystemSetting;
use App\Services\Sms\WorkflowSms;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class SmsCommitBoundaryTest extends TestCase
{
    use DatabaseMigrations;

    public function test_dispatch_occurs_only_after_commit_and_uses_dedicated_queue(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        config(['textbee.enabled' => true]);
        SystemSetting::current()->update(['sms_enabled' => true]);
        DB::beginTransaction();
        $batch = app(WorkflowSms::class)->raw('otp', 'commit-event', '+639171234567', 'Synthetic OTP', [], now()->addMinutes(5));
        Queue::assertNothingPushed();
        DB::commit();
        Queue::assertPushed(DeliverSmsBatch::class, fn ($job): bool => $job->batchId === $batch->id && $job->queue === 'sms-otp' && $job->connection === 'database');
        Http::assertNothingSent();
    }

    public function test_rollback_discards_ledger_and_after_commit_callbacks(): void
    {
        Queue::fake();
        config(['textbee.enabled' => true]);
        SystemSetting::current()->update(['sms_enabled' => true]);
        DB::beginTransaction();
        app(WorkflowSms::class)->raw('otp', 'rollback-event', '+639171234567', 'Synthetic OTP', [], now()->addMinutes(5));
        DB::rollBack();
        $this->assertSame(0, SmsBatch::count());
        Queue::assertNothingPushed();
    }
}
