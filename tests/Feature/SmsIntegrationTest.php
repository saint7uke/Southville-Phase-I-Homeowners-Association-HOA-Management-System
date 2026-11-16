<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Announcements\SendImportantSms;
use App\Actions\Payments\RecordPayment;
use App\Actions\Payments\ReviewPaymentProof;
use App\Actions\Users\ChangeAccountStatus;
use App\Enums\UserAccountStatus;
use App\Filament\Pages\SmsNotifications;
use App\Filament\Resources\Announcements\Pages\ListAnnouncements;
use App\Jobs\DeliverSmsBatch;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\Complaint;
use App\Models\DuesObligation;
use App\Models\DuesSetting;
use App\Models\EmailDelivery;
use App\Models\Payment;
use App\Models\SmsBatch;
use App\Models\SmsContactPreference;
use App\Models\SmsDelivery;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Sms\SmsEligibility;
use App\Services\Sms\SmsEnrollment;
use App\Services\Sms\SmsEvents;
use App\Services\Sms\SmsGatewayException;
use App\Services\Sms\SmsManagement;
use App\Services\Sms\SmsReminders;
use App\Services\Sms\SmsSynchronizer;
use App\Services\Sms\TextBeeClient;
use App\Services\Sms\WorkflowSms;
use App\Support\PhilippineMobile;
use App\Support\SmsText;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

final class SmsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-11-09 10:00:00', 'Asia/Manila'));
        $this->seed(RolesAndPermissionsSeeder::class);
        Queue::fake();
        Notification::fake();
        Http::preventStrayRequests();
        config(['textbee.enabled' => true, 'textbee.api_key' => 'synthetic-test-key', 'textbee.test_mode' => true,
            'textbee.test_numbers' => ['+639171234567', '+639181234567']]);
        SystemSetting::current()->update(['sms_enabled' => true]);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('hoa_admin');
        Filament::setCurrentPanel('admin');
        $this->actingAs($this->admin, 'admin');
    }

    private function resident(string $phone = '09171234567', bool $consent = true): User
    {
        $user = User::factory()->create(['contact_number' => $phone, 'account_status' => 'Active']);
        $user->assignRole('homeowner');
        $user->homeowner()->create(['house_number' => null, 'street' => null, 'block' => '7', 'lot' => (string) $user->id,
            'phase' => 'Southville Phase I', 'phase_suffix' => 'A', 'ownership_type' => 'Owner', 'residency_date' => '2020-01-01', 'status' => 'Active']);
        if ($consent) {
            SmsContactPreference::create(['user_id' => $user->id, 'verified_phone_hash' => PhilippineMobile::fingerprint(PhilippineMobile::normalize($phone)),
                'verified_at' => now()->subMinute(), 'transactional_opt_in' => true, 'announcement_opt_in' => true, 'consented_at' => now(), 'consent_version' => 'sms-2026-11-v1']);
        }

        return $user->fresh();
    }

    private function queue(User $user, string $event = 'approval-event'): SmsBatch
    {
        $ids = app(WorkflowSms::class)->queue('approval', $event, [$user], ['user_id' => $user->id]);

        return SmsBatch::findOrFail($ids[0]);
    }

    private function accepted(int $recipients = 1, string $id = 'synthetic-batch'): void
    {
        $sequence = 0;
        Http::fake(['api.textbee.dev/*' => function () use (&$sequence, $recipients, $id) {
            $batchId = $sequence++ === 0 ? $id : $id.'-'.$sequence;

            return Http::response(['data' => ['success' => true, 'smsBatchId' => $batchId, 'recipientCount' => $recipients]], 200);
        }]);
    }

    private function send(SmsBatch $batch): void
    {
        app()->call([new DeliverSmsBatch($batch->id), 'handle']);
    }

    private function obligation(User $user, string $due, string $paid = '0.00', string $status = 'Pending'): DuesObligation
    {
        $setting = DuesSetting::create(['name' => 'SMS schedule '.(DuesSetting::count() + 1), 'amount' => '100.00', 'frequency' => 'Monthly', 'due_day' => 10, 'starts_on' => '2026-01-01', 'is_active' => true]);

        return DuesObligation::create(['homeowner_id' => $user->homeowner->id, 'dues_setting_id' => $setting->id, 'billing_year' => 2026,
            'billing_month' => 11, 'due_date' => $due, 'amount_due' => '100.00', 'penalty_amount' => '0.00', 'amount_paid' => $paid, 'status' => $status]);
    }

    #[DataProvider('phones')]
    public function test_phone_normalization_is_strict(mixed $input, ?string $expected): void
    {
        $this->assertSame($expected, PhilippineMobile::normalize($input));
    }

    public static function phones(): array
    {
        return [['09171234567', '+639171234567'], ['+639171234567', '+639171234567'], ['639171234567', '+639171234567'],
            ['0917 123-4567', '+639171234567'], ['+14155550101', null], ['0917123456', null], ['09abc1234567', null], [null, null], [['09171234567'], null]];
    }

    public function test_segment_counts_include_extended_gsm_and_utf16_units(): void
    {
        $this->assertSame(1, SmsText::segments(str_repeat('A', 160)));
        $this->assertSame(2, SmsText::segments(str_repeat('A', 161)));
        $this->assertSame(1, SmsText::segments(str_repeat('^', 80)));
        $this->assertSame(2, SmsText::segments(str_repeat('^', 81)));
        $this->assertSame(1, SmsText::segments(str_repeat('🙂', 35)));
        $this->assertSame(2, SmsText::segments(str_repeat('🙂', 36)));
    }

    public function test_kill_switch_and_consent_gate_preserve_email_and_prevent_sends(): void
    {
        $user = $this->resident();
        config(['textbee.enabled' => false]);
        $this->assertSame([], app(WorkflowSms::class)->queue('approval', 'off', [$user]));
        $this->assertDatabaseCount('sms_deliveries', 0);
        config(['textbee.enabled' => true]);
        SmsContactPreference::where('user_id', $user->id)->update(['transactional_opt_in' => false]);
        $this->assertSame([], app(WorkflowSms::class)->queue('approval', 'consent-off', [$user]));
        $this->assertDatabaseHas('sms_deliveries', ['status' => 'Skipped']);
        Http::assertNothingSent();
    }

    public function test_shared_phone_and_repeated_job_submit_once_with_encrypted_content(): void
    {
        $one = $this->resident();
        $two = $this->resident();
        $ids = app(WorkflowSms::class)->queue('approval', 'shared-event', [$one, $two], ['user_id' => $one->id]);
        app(WorkflowSms::class)->queue('approval', 'shared-event', [$one, $two], ['user_id' => $one->id]);
        $delivery = SmsDelivery::sole();
        $this->assertEqualsCanonicalizing([$one->id, $two->id], $delivery->user_ids);
        $this->assertStringNotContainsString('Southville', DB::table('sms_deliveries')->value('body'));
        $this->assertStringNotContainsString('63917', DB::table('sms_deliveries')->value('phone'));
        $this->accepted();
        $batch = SmsBatch::findOrFail($ids[0]);
        $this->send($batch);
        $this->send($batch);
        Http::assertSentCount(1);
        $this->assertSame('Accepted', $delivery->fresh()->status);
        $this->assertNull($delivery->fresh()->delivered_at);
        $this->assertSame(1, DB::table('sms_daily_usage')->value('segments'));
    }

    public function test_bulk_uses_provider_bulk_endpoint_and_does_not_claim_delivery(): void
    {
        $one = $this->resident();
        $two = $this->resident('09181234567');
        $ids = app(WorkflowSms::class)->queue('approval', 'bulk-event', [$one, $two], ['user_id' => $one->id]);
        $this->accepted(2);
        $this->send(SmsBatch::findOrFail($ids[0]));
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/gateway/send-bulk-sms') && count($request['messages']) === 2);
        $this->assertSame(['Accepted'], SmsDelivery::pluck('status')->unique()->all());
    }

    #[DataProvider('uncertainResponses')]
    public function test_uncertain_results_are_not_automatically_retried(int $status, array $body): void
    {
        $batch = $this->queue($this->resident());
        Http::fake(['*' => Http::response($body, $status)]);
        $this->send($batch);
        $this->send($batch);
        $this->assertSame('Uncertain', $batch->fresh()->status);
        $this->assertSame(1, DB::table('sms_daily_usage')->value('segments'));
        Http::assertSentCount(1);
    }

    public static function uncertainResponses(): array
    {
        return [[500, []], [200, ['data' => ['success' => true]]], [200, ['data' => ['success' => true, 'smsBatchId' => 'fake', 'recipientCount' => 9]]]];
    }

    public function test_connection_loss_is_uncertain_and_interrupted_worker_never_reposts(): void
    {
        $batch = $this->queue($this->resident());
        Http::fake(fn () => throw new ConnectionException('synthetic private detail'));
        $this->send($batch);
        $this->assertSame('Uncertain', $batch->fresh()->status);
        $other = $this->queue($this->resident('09181234567'), 'interrupted');
        $other->update(['status' => 'Sending', 'started_at' => now()->subMinutes(3)]);
        SmsDelivery::where('batch_id', $other->id)->update(['status' => 'Sending']);
        (new DeliverSmsBatch($other->id))->failed(new \RuntimeException('private detail'));
        $this->assertSame('Uncertain', $other->fresh()->status);
    }

    public function test_definite_rejection_releases_budget_and_rate_limit_has_bounded_retries(): void
    {
        $batch = $this->queue($this->resident());
        Http::fake(['*' => Http::response([], 429)]);
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $batch->refresh()->update(['next_attempt_at' => null]);
            $this->send($batch);
        }
        $this->assertSame('Failed', $batch->fresh()->status);
        $this->assertSame(3, $batch->fresh()->attempts);
        $this->assertSame(0, DB::table('sms_daily_usage')->value('segments'));
        Http::assertSentCount(3);
    }

    public function test_test_allowlist_blocks_unapproved_numbers_before_http(): void
    {
        $user = $this->resident('09991234567');
        $batch = $this->queue($user);
        $this->send($batch);
        $this->assertSame('Failed', $batch->fresh()->status);
        $this->assertSame('test_recipient_not_allowlisted', $batch->fresh()->failure_code);
        Http::assertNothingSent();
    }

    public function test_budget_is_all_or_nothing_and_limits_notifications_per_phone(): void
    {
        $user = $this->resident();
        $this->accepted();
        for ($index = 0; $index < 4; $index++) {
            $batch = $this->queue($user, 'budget-'.$index);
            $this->send($batch);
        }
        $this->assertSame(3, SmsDelivery::where('status', 'Accepted')->count());
        $this->assertSame(1, SmsBatch::where('failure_code', 'local_budget_limit')->count());
        $this->assertSame(3, DB::table('sms_daily_usage')->value('segments'));
        Http::assertSentCount(3);
    }

    public function test_quiet_hours_defers_and_expiry_prevents_late_send(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-09 20:00:00', 'Asia/Manila'));
        $batch = $this->queue($this->resident());
        $this->send($batch);
        $this->assertSame('Queued', $batch->fresh()->status);
        $this->assertSame('2026-11-10 09:00', $batch->fresh()->next_attempt_at->timezone('Asia/Manila')->format('Y-m-d H:i'));
        $batch->refresh()->update(['expires_at' => now()->subSecond(), 'next_attempt_at' => null]);
        $this->send($batch);
        $this->assertSame('Expired', SmsDelivery::sole()->status);
        Http::assertNothingSent();
    }

    public function test_current_recipient_and_event_are_rechecked_before_sending(): void
    {
        $user = $this->resident();
        $batch = $this->queue($user);
        SmsContactPreference::where('user_id', $user->id)->update(['transactional_opt_in' => false]);
        $this->send($batch);
        $this->assertSame('Skipped', SmsDelivery::sole()->status);
        Http::assertNothingSent();
    }

    private function challenge(string $binding = 'synthetic-binding'): array
    {
        $verification = app(SmsEnrollment::class)->request('09171234567', $binding, '192.0.2.1');
        preg_match('/code is (\d{6})/', SmsDelivery::where('type', 'otp')->latest('id')->first()->body, $matches);

        return [$verification, $matches[1]];
    }

    public function test_otp_binding_single_use_and_registration_proof_consumption(): void
    {
        [$verification, $code] = $this->challenge();
        $enrollment = app(SmsEnrollment::class);
        $enrollment->confirm($verification->id, $code, 'synthetic-binding');
        $this->assertNull(SmsDelivery::sole()->body);
        $user = $this->resident(consent: false);
        $enrollment->apply($user, ['sms_transactional_opt_in' => true, 'sms_verification_id' => $verification->id], 'synthetic-binding');
        $this->assertNotNull($verification->fresh()->consumed_at);
        $this->assertNotNull(app(SmsEligibility::class)->recipient($user, 'approval'));
        $this->expectException(ValidationException::class);
        $enrollment->apply($this->resident(consent: false), ['sms_transactional_opt_in' => true, 'sms_verification_id' => $verification->id], 'synthetic-binding');
    }

    public function test_wrong_codes_are_counted_even_when_validation_fails(): void
    {
        [$verification, $code] = $this->challenge();
        for ($index = 0; $index < 5; $index++) {
            try {
                app(SmsEnrollment::class)->confirm($verification->id, $code === '100000' ? '100001' : '100000', 'synthetic-binding');
                $this->fail('Invalid code accepted.');
            } catch (ValidationException) {
            }
        }
        $this->assertSame(5, $verification->fresh()->attempts);
        $this->expectException(ValidationException::class);
        app(SmsEnrollment::class)->confirm($verification->id, $code, 'synthetic-binding');
    }

    public function test_expired_wrong_binding_and_resent_codes_cannot_be_used(): void
    {
        [$verification, $code] = $this->challenge();
        try {
            app(SmsEnrollment::class)->confirm($verification->id, $code, 'other-binding');
            $this->fail('Wrong binding accepted.');
        } catch (ValidationException) {
        }
        $this->travel(61)->seconds();
        $this->challenge();
        $this->assertNotNull($verification->fresh()->invalidated_at);
        $this->expectException(ValidationException::class);
        app(SmsEnrollment::class)->confirm($verification->id, $code, 'synthetic-binding');
    }

    public function test_otp_cooldown_and_no_forged_verification_fields(): void
    {
        $this->challenge();
        try {
            $this->challenge();
            $this->fail('Cooldown bypassed.');
        } catch (ValidationException) {
        }
        $this->assertDatabaseCount('sms_verifications', 1);
        $this->expectException(ValidationException::class);
        app(SmsEnrollment::class)->apply($this->resident(consent: false), ['sms_transactional_opt_in' => true, 'sms_verified_at' => now()], 'forged');
    }

    public function test_phone_change_invalidates_verification_but_equivalent_format_does_not(): void
    {
        $user = $this->resident();
        $user->update(['contact_number' => '+639171234567']);
        $this->assertNotNull(SmsContactPreference::sole()->verified_at);
        $user->update(['contact_number' => '09181234567']);
        $this->assertNull(SmsContactPreference::sole()->verified_at);
        $this->assertFalse(SmsContactPreference::sole()->transactional_opt_in);
    }

    public function test_approval_only_initial_transition_and_channel_toggles_are_independent(): void
    {
        $user = $this->resident();
        $user->forceFill(['account_status' => 'Pending', 'approved_at' => null])->save();
        app(ChangeAccountStatus::class)->handle($user, UserAccountStatus::Active, $this->admin);
        $this->assertSame(1, SmsDelivery::where('type', 'approval')->count());
        app(ChangeAccountStatus::class)->handle($user->fresh(), UserAccountStatus::Inactive, $this->admin);
        app(ChangeAccountStatus::class)->handle($user->fresh(), UserAccountStatus::Active, $this->admin);
        $this->assertSame(1, SmsDelivery::where('type', 'approval')->count());
        $this->assertGreaterThan(0, EmailDelivery::count());
    }

    public function test_complaint_real_transitions_each_get_identity_and_old_updates_expire(): void
    {
        SystemSetting::current()->update(['complaint_notifications' => false]);
        $user = $this->resident();
        $complaint = Complaint::create(['homeowner_id' => $user->homeowner->id, 'subject' => 'Synthetic complaint', 'description' => 'Isolated fixture', 'category' => 'Others', 'priority' => 'Medium', 'status' => 'Pending']);
        $complaint->update(['status' => 'Under Review']);
        $complaint->update(['admin_remarks' => 'No status change']);
        $this->assertSame(1, SmsDelivery::where('type', 'complaint')->count());
        $complaint->update(['status' => 'Resolved']);
        $complaint->reopen();
        $this->assertSame(3, SmsDelivery::where('type', 'complaint')->count());
        $old = SmsBatch::oldest('id')->first();
        $this->send($old);
        $this->assertSame('Skipped', $old->fresh()->status);
        $this->accepted();
        $this->send(SmsBatch::latest('id')->first());
        Http::assertSentCount(1);
    }

    public function test_direct_recorded_payment_and_approved_proof_use_same_canonical_event(): void
    {
        $user = $this->resident();
        $due = $this->obligation($user, '2026-11-10');
        SystemSetting::current()->update(['payment_notifications' => false]);
        $payment = app(RecordPayment::class)->handle(['dues_obligation_id' => $due->id, 'payer_homeowner_id' => $user->homeowner->id,
            'payment_date' => '2026-11-09', 'payment_method' => 'Cash', 'amount_paid' => '20.00'], $this->admin);
        app(SmsEvents::class)->payment($payment);
        $this->assertSame(1, SmsDelivery::where('type', 'payment')->count());
        $proof = Payment::create(['homeowner_id' => $user->homeowner->id, 'payer_homeowner_id' => $user->homeowner->id, 'dues_setting_id' => $due->dues_setting_id,
            'dues_obligation_id' => $due->id, 'amount_paid' => '10.00', 'penalty' => '0.00', 'balance' => '0.00', 'covered_period' => 'November 2026', 'payment_date' => today(), 'payment_method' => 'GCash', 'review_status' => 'Pending', 'status' => 'Pending', 'proof_path' => 'synthetic-proof.png']);
        $this->assertSame(1, SmsDelivery::where('type', 'payment')->count());
        app(ReviewPaymentProof::class)->approve($proof, $this->admin);
        $this->assertSame(2, SmsDelivery::where('type', 'payment')->count());
        $this->assertSame('30.00', $due->fresh()->amount_paid);
    }

    public function test_reminders_use_due_snapshot_and_stop_when_paid_without_replaying_dates(): void
    {
        $user = $this->resident();
        $due = $this->obligation($user, '2026-11-12');
        $due->duesSetting->update(['due_day' => 25]);
        $result = app(SmsReminders::class)->generate();
        app(SmsReminders::class)->generate();
        $this->assertSame(['due' => 1, 'overdue' => 0], $result);
        $this->assertSame(1, SmsDelivery::where('type', 'due')->count());
        $due->update(['amount_paid' => '100.00', 'status' => 'Paid']);
        $this->send(SmsBatch::sole());
        Http::assertNothingSent();
        $this->assertSame('Skipped', SmsDelivery::sole()->status);
    }

    public function test_overdue_weekly_schedule_consolidates_multiple_periods_per_phone(): void
    {
        $user = $this->resident();
        $this->obligation($user, '2026-11-08');
        $this->obligation($user, '2026-11-01', '25.00', 'Partial');
        $this->obligation($user, '2026-11-08', '100.00', 'Paid');
        $this->assertSame(2, app(SmsReminders::class)->generate()['overdue']);
        $this->assertSame(1, SmsDelivery::where('type', 'overdue')->count());
        $this->travel(1)->days();
        $this->assertSame(0, app(SmsReminders::class)->generate()['overdue']);
    }

    private function important(): Announcement
    {
        return Announcement::create(['title' => 'Synthetic Important notice', 'content' => 'Controlled SMS test record.', 'category' => 'General',
            'audience' => 'Residents', 'status' => 'Published', 'published_at' => now()->subMinute(), 'created_by' => $this->admin->id, 'is_important' => true]);
    }

    public function test_announcements_require_confirmation_version_and_complete_budget(): void
    {
        $this->resident();
        $this->resident('09181234567');
        $announcement = $this->important();
        $this->assertDatabaseCount('sms_deliveries', 0);
        $action = app(SendImportantSms::class);
        $review = $action->review($announcement, $this->admin);
        SystemSetting::current()->update(['sms_daily_segments' => 1]);
        try {
            $action->handle($announcement, $this->admin, $review['review_hash']);
            $this->fail('Partial budget should reject the entire blast.');
        } catch (ValidationException) {
        }
        $this->assertDatabaseCount('sms_deliveries', 0);
        $this->assertDatabaseCount('announcement_sms_blasts', 0);
        SystemSetting::current()->update(['sms_daily_segments' => 50]);
        $this->assertSame(2, $action->handle($announcement, $this->admin, $review['review_hash']));
        $this->assertSame(2, DB::table('sms_daily_usage')->value('segments'));
        $this->expectException(ValidationException::class);
        $action->handle($announcement, $this->admin, $review['review_hash']);
    }

    public function test_stale_announcement_or_recipient_review_is_rejected(): void
    {
        $this->resident();
        $announcement = $this->important();
        $action = app(SendImportantSms::class);
        $review = $action->review($announcement, $this->admin);
        $announcement->update(['content' => 'Changed after review']);
        $this->expectException(ValidationException::class);
        $action->handle($announcement, $this->admin, $review['review_hash']);
    }

    public function test_provider_status_updates_are_scoped_and_late_reports_can_resolve_unknown(): void
    {
        $batch = $this->queue($this->resident());
        $this->accepted();
        $this->send($batch);
        $message = ['_id' => 'synthetic-message', 'device' => ['_id' => config('textbee.device_id')], 'smsBatch' => 'synthetic-batch',
            'recipient' => '+639171234567', 'status' => 'unknown', 'updatedAt' => now()->toIso8601String()];
        $sync = app(SmsSynchronizer::class);
        $sync->outgoingMessage($batch->fresh(), $message);
        $this->assertSame('Unknown', SmsDelivery::sole()->status);
        $message['status'] = 'delivered';
        $message['updatedAt'] = now()->addMinute()->toIso8601String();
        $message['deliveredAt'] = $message['updatedAt'];
        $sync->outgoingMessage($batch->fresh(), $message);
        $this->assertSame('Delivered', SmsDelivery::sole()->status);
        $message['status'] = 'pending';
        $message['updatedAt'] = now()->addMinutes(2)->toIso8601String();
        $sync->outgoingMessage($batch->fresh(), $message);
        $this->assertSame('Delivered', SmsDelivery::sole()->status);
    }

    public function test_incoming_optout_is_deduplicated_and_does_not_change_business_records(): void
    {
        $user = $this->resident();
        $batch = $this->queue($user);
        $message = ['_id' => 'incoming-stop', 'device' => ['_id' => config('textbee.device_id')], 'status' => 'received', 'sender' => '+639171234567', 'message' => 'STOP'];
        $sync = app(SmsSynchronizer::class);
        $sync->incomingMessage($message);
        $this->assertDatabaseCount('sms_phone_states', 0);
        SystemSetting::current()->update(['sms_incoming_enabled' => true]);
        $sync->incomingMessage($message);
        $sync->incomingMessage($message);
        DB::table('sms_sync_states')->insert(['key' => 'incoming-healthy', 'value' => now()->toIso8601String(), 'updated_at' => now()]);
        $this->assertSame(1, SmsDelivery::where('direction', 'incoming')->count());
        $this->assertNotNull(DB::table('sms_phone_states')->value('suppressed_at'));
        $this->send($batch);
        $this->assertSame('Skipped', SmsDelivery::where('direction', 'outgoing')->sole()->status);
        $this->assertDatabaseCount('payments', 0);
        Http::assertNothingSent();
    }

    public function test_retention_purges_content_but_keeps_duplicate_prevention_metadata(): void
    {
        $batch = $this->queue($this->resident());
        $delivery = SmsDelivery::sole();
        $delivery->update(['created_at' => now()->subDays(91)]);
        config(['textbee.enabled' => false]);
        $this->artisan('hoa:sms-tick')->assertSuccessful();
        $delivery->refresh();
        $this->assertNull($delivery->body);
        $this->assertNull($delivery->phone);
        $this->assertNotNull($delivery->event_key);
        $this->assertDatabaseCount('sms_deliveries', 1);
        Http::assertNothingSent();
    }

    public function test_staff_and_homeowners_cannot_manage_sms_or_forge_profile_scope(): void
    {
        $this->get('/admin/sms-notifications')->assertOk();
        $staff = User::factory()->create();
        $staff->assignRole('hoa_staff');
        $this->actingAs($staff, 'admin')->get('/admin/sms-notifications')->assertForbidden();
        $this->postJson('/sms/verification/request', ['scope' => 'profile', 'contact_number' => '09171234567'])->assertForbidden();
        $this->expectException(HttpException::class);
        app(SmsManagement::class)->test($staff, '09171234567');
    }

    public function test_settings_and_test_action_report_actual_acceptance_not_delivery(): void
    {
        $this->accepted();
        Livewire::test(SmsNotifications::class)->callAction('testSms', ['number' => '09171234567'])
            ->assertHasNoActionErrors()->assertNotified('SMS Submitted to Gateway Successfully!');
        $this->assertSame('Accepted', SmsBatch::sole()->status);
        $this->assertNull(SmsDelivery::sole()->delivered_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'Sms.test_requested']);
        $log = AuditLog::where('action', 'Sms.test_requested')->sole();
        $this->assertStringNotContainsString('0917', json_encode($log->new_values));
    }

    public function test_ledger_changes_roll_back_with_the_business_transaction(): void
    {
        $user = $this->resident();
        try {
            DB::transaction(function () use ($user): void {
                $this->queue($user);
                throw new \RuntimeException('synthetic rollback');
            });
        } catch (\RuntimeException) {
        }
        $this->assertDatabaseCount('sms_deliveries', 0);
        $this->assertDatabaseCount('sms_batches', 0);
        Http::assertNothingSent();
    }

    public function test_malformed_provider_reads_and_missing_status_timestamps_are_not_delivery_evidence(): void
    {
        $batch = $this->queue($this->resident());
        $this->accepted();
        $this->send($batch);
        app(SmsSynchronizer::class)->outgoingMessage($batch->fresh(), ['_id' => 'no-time', 'device' => config('textbee.device_id'),
            'smsBatch' => 'synthetic-batch', 'recipient' => '+639171234567', 'status' => 'delivered']);
        $this->assertSame('Accepted', SmsDelivery::sole()->status);
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['api.textbee.dev/*' => Http::response(['data' => 'malformed'])]);
        $this->expectException(SmsGatewayException::class);
        app(TextBeeClient::class)->device();
    }

    public function test_disabled_maintenance_expires_unsent_reservations_without_provider_requests(): void
    {
        $this->resident();
        $announcement = $this->important();
        $action = app(SendImportantSms::class);
        $action->handle($announcement, $this->admin, $action->review($announcement, $this->admin)['review_hash']);
        $this->assertSame(1, (int) DB::table('sms_daily_usage')->value('segments'));
        SmsBatch::query()->update(['expires_at' => now()->subSecond()]);
        config(['textbee.enabled' => false]);
        $this->artisan('hoa:sms-tick')->assertSuccessful();
        $this->assertSame('Expired', SmsDelivery::sole()->status);
        $this->assertSame(0, (int) DB::table('sms_daily_usage')->value('segments'));
        Http::assertNothingSent();
    }

    public function test_worker_pauses_when_enabled_optout_sync_is_stale(): void
    {
        $batch = $this->queue($this->resident());
        SystemSetting::current()->update(['sms_incoming_enabled' => true]);
        $this->send($batch);
        $this->assertSame('Queued', $batch->fresh()->status);
        $this->assertSame('opt_out_sync_required', $batch->fresh()->failure_code);
        Http::assertNothingSent();
    }

    public function test_reminder_rechecks_changed_due_date_at_submission(): void
    {
        $due = $this->obligation($this->resident(), '2026-11-12');
        app(SmsReminders::class)->generate();
        $due->update(['due_date' => '2026-11-13']);
        $this->send(SmsBatch::sole());
        $this->assertSame('Skipped', SmsDelivery::sole()->status);
        Http::assertNothingSent();
    }

    public function test_incoming_pagination_stays_device_scoped_and_deduplicates_overlap(): void
    {
        SystemSetting::current()->update(['sms_incoming_enabled' => true]);
        $message = ['_id' => 'history-stop', 'device' => config('textbee.device_id'), 'status' => 'received', 'sender' => '+639171234567', 'message' => 'STOP'];
        Http::fake(['api.textbee.dev/*' => Http::sequence()
            ->push(['data' => [$message, [...$message, '_id' => 'other-device', 'device' => 'unrelated']], 'meta' => ['nextCursor' => 'cursor1', 'hasMore' => true]])
            ->push(['data' => [$message], 'meta' => ['nextCursor' => 'cursor2', 'hasMore' => false]])]);
        app(SmsSynchronizer::class)->incoming();
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request['deviceIds'] === config('textbee.device_id') && ($request['cursor'] ?? null) === 'cursor1');
        $this->assertSame(1, SmsDelivery::where('direction', 'incoming')->count());
        $this->assertSame('cursor2', DB::table('sms_sync_states')->where('key', 'incoming:'.config('textbee.device_id'))->value('value'));
    }

    public function test_month_end_and_leap_day_reminders_use_manila_calendar(): void
    {
        $this->travelTo(CarbonImmutable::parse('2028-02-26 10:00:00', 'Asia/Manila'));
        $this->obligation($this->resident(), '2028-02-29');
        $this->assertSame(1, app(SmsReminders::class)->generate()['due']);
        $this->travelTo(CarbonImmutable::parse('2028-03-01 10:00:00', 'Asia/Manila'));
        $this->assertSame(1, app(SmsReminders::class)->generate()['overdue']);
    }

    public function test_important_sms_dialog_renders_distinct_readonly_count_fields_without_sending(): void
    {
        $this->resident();
        $announcement = $this->important();
        $page = Livewire::test(ListAnnouncements::class)
            ->mountTableAction('importantSms', $announcement)
            ->assertSet('mountedActions.0.name', 'importantSms');
        $this->assertSame(1, (int) $page->get('mountedActions')[0]['data']['recipients']);
        $this->assertDatabaseCount('announcement_sms_blasts', 0);
        Http::assertNothingSent();
    }

    public function test_payment_notifies_distinct_consented_payer_and_liable_numbers(): void
    {
        $liable = $this->resident();
        $payer = $this->resident('09181234567');
        $due = $this->obligation($liable, '2026-11-10');
        $payment = Payment::create(['homeowner_id' => $liable->homeowner->id, 'payer_homeowner_id' => $payer->homeowner->id,
            'dues_setting_id' => $due->dues_setting_id, 'dues_obligation_id' => $due->id, 'amount_paid' => '10.00', 'penalty' => '0.00',
            'balance' => '90.00', 'covered_period' => 'November 2026', 'payment_date' => today(), 'payment_method' => 'Cash', 'review_status' => 'Recorded', 'status' => 'Partial']);
        app(SmsEvents::class)->payment($payment);
        app(SmsEvents::class)->payment($payment);
        $this->assertSame(2, SmsDelivery::where('type', 'payment')->count());
        $this->assertEqualsCanonicalizing([[$liable->id], [$payer->id]], SmsDelivery::where('type', 'payment')->get()->pluck('user_ids')->all());
    }
}
