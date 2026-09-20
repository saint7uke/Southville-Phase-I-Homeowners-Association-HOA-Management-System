<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Dues\FlagDelinquentHomeowners;
use App\Actions\Dues\GenerateMonthlyObligations;
use App\Actions\Dues\GenerateSettingObligations;
use App\Actions\Dues\MarkOverdueObligations;
use App\Actions\Payments\RecordPayment;
use App\Actions\Payments\ReviewPaymentProof;
use App\Actions\Payments\SubmitPaymentProof;
use App\Models\DuesObligation;
use App\Models\DuesSetting;
use App\Models\Homeowner;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\HomeownerDelinquencyNotice;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class DuesWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_monthly_generation_is_idempotent_and_snapshots_the_setting_amount(): void
    {
        $homeowner = $this->homeowner();
        DuesSetting::query()->create(['name' => 'Association Dues', 'amount' => '350.00', 'frequency' => 'Monthly', 'is_active' => true]);
        DuesSetting::query()->create(['name' => 'Inactive Fee', 'amount' => '100.00', 'frequency' => 'Monthly', 'is_active' => false]);

        $period = CarbonImmutable::create(2026, 9, 1);
        $this->assertSame(1, app(GenerateMonthlyObligations::class)->handle($period));
        $this->assertSame(0, app(GenerateMonthlyObligations::class)->handle($period));

        $obligation = DuesObligation::query()->sole();
        $this->assertSame($homeowner->id, $obligation->homeowner_id);
        $this->assertSame('350.00', $obligation->amount_due);
        $this->assertSame('2026-09-10', $obligation->due_date->toDateString());
        $this->assertSame('Pending', $obligation->status);
    }

    public function test_overdue_sweep_only_changes_open_past_due_obligations(): void
    {
        $obligation = $this->obligation(['due_date' => '2026-08-10']);
        $paid = $this->obligation(['due_date' => '2026-08-10', 'status' => 'Paid']);
        $future = $this->obligation(['due_date' => '2026-09-20']);

        $this->assertSame(1, app(MarkOverdueObligations::class)->handle(CarbonImmutable::create(2026, 9, 1)));
        $this->assertSame('Overdue', $obligation->refresh()->status);
        $this->assertSame('Paid', $paid->refresh()->status);
        $this->assertSame('Pending', $future->refresh()->status);
    }

    public function test_annual_and_special_schedules_only_generate_in_their_applicable_month(): void
    {
        $this->homeowner();
        $annual = DuesSetting::query()->create([
            'name' => 'Annual membership', 'amount' => '1200.00', 'frequency' => 'Annual',
            'starts_on' => '2026-03-01', 'due_day' => 15, 'is_active' => true,
        ]);
        $special = DuesSetting::query()->create([
            'name' => 'Gate repair', 'amount' => '500.00', 'frequency' => 'Special Assessment',
            'starts_on' => '2026-09-01', 'ends_on' => '2026-09-30', 'due_day' => 30, 'is_active' => true,
        ]);

        $generator = app(GenerateSettingObligations::class);
        $this->assertSame(0, $generator->handle($annual, CarbonImmutable::create(2026, 2, 1)));
        $this->assertSame(1, $generator->handle($annual, CarbonImmutable::create(2026, 3, 1)));
        $this->assertSame(0, $generator->handle($special, CarbonImmutable::create(2026, 8, 1)));
        $this->assertSame(1, $generator->handle($special, CarbonImmutable::create(2026, 9, 1)));
        $this->assertDatabaseHas('dues_obligations', ['dues_setting_id' => $special->id, 'due_date' => '2026-09-30']);
    }

    public function test_recording_payments_uses_exact_cents_and_cannot_overpay_an_obligation(): void
    {
        $admin = User::factory()->create(['account_status' => 'Active']);
        $admin->assignRole('hoa_admin');
        $obligation = $this->obligation(['amount_due' => '100.10', 'due_date' => now()->addDay()->toDateString()]);

        $payment = app(RecordPayment::class)->handle($this->paymentPayload($obligation, '40.05'), $admin);
        $this->assertSame('60.05', $payment->balance);
        $this->assertSame('40.05', $obligation->refresh()->amount_paid);
        $this->assertSame('Partial', $obligation->status);

        $payment = app(RecordPayment::class)->handle($this->paymentPayload($obligation, '60.05'), $admin);
        $this->assertSame('0.00', $payment->balance);
        $this->assertSame('Paid', $obligation->refresh()->status);

        $this->expectException(ValidationException::class);
        app(RecordPayment::class)->handle($this->paymentPayload($obligation, '0.01'), $admin);
    }

    public function test_homeowner_cannot_record_a_payment_through_the_domain_action(): void
    {
        $obligation = $this->obligation();
        $owner = $obligation->homeowner->user;

        try {
            app(RecordPayment::class)->handle($this->paymentPayload($obligation, '100.00'), $owner);
            $this->fail('A homeowner must not record an approved payment.');
        } catch (AuthorizationException) {
            $this->assertDatabaseMissing('payments', ['dues_obligation_id' => $obligation->id]);
            $this->assertSame('0.00', $obligation->fresh()->amount_paid);
        }
    }

    public function test_homeowner_payment_proof_is_private_pending_review_and_does_not_allocate_funds(): void
    {
        Storage::fake('local');
        $obligation = $this->obligation();
        $owner = $obligation->homeowner->user;

        $payment = app(SubmitPaymentProof::class)->handle($owner, $obligation, [
            'amount_paid' => '100.00',
            'payment_date' => now()->toDateString(),
            'covered_period' => 'September 2026',
            'payment_method' => 'GCash',
        ], UploadedFile::fake()->create('proof.pdf', 200, 'application/pdf'));

        $this->assertSame('Pending Review', $payment->status);
        $this->assertSame('Pending', $payment->review_status);
        $this->assertSame('0.00', $obligation->refresh()->amount_paid);
        Storage::disk('local')->assertExists($payment->proof_path);

        $otherOwner = $this->homeowner()->user;
        $this->expectException(AuthorizationException::class);
        app(SubmitPaymentProof::class)->handle($otherOwner, $obligation, [
            'amount_paid' => '100.00',
            'payment_date' => now()->toDateString(),
            'covered_period' => 'September 2026',
            'payment_method' => 'GCash',
        ], UploadedFile::fake()->create('other.pdf', 200, 'application/pdf'));
    }

    public function test_homeowner_cannot_submit_zero_or_excess_payment_proof_amount(): void
    {
        Storage::fake('local');
        $obligation = $this->obligation(['amount_due' => '100.00', 'amount_paid' => '25.00', 'status' => 'Partial']);
        $owner = $obligation->homeowner->user;

        foreach (['0.00', '75.01'] as $amount) {
            try {
                app(SubmitPaymentProof::class)->handle($owner, $obligation, [
                    'amount_paid' => $amount,
                    'payment_date' => now()->toDateString(),
                    'covered_period' => 'September 2026',
                    'payment_method' => 'GCash',
                ], UploadedFile::fake()->create('proof.pdf', 200, 'application/pdf'));
                $this->fail("Amount {$amount} should have been rejected.");
            } catch (ValidationException) {
                $this->assertDatabaseMissing('payments', ['dues_obligation_id' => $obligation->id, 'amount_paid' => $amount]);
            }
        }
    }

    public function test_payment_proof_action_rejects_disallowed_file_content(): void
    {
        Storage::fake('local');
        $obligation = $this->obligation();
        $owner = $obligation->homeowner->user;

        $this->expectException(ValidationException::class);
        app(SubmitPaymentProof::class)->handle($owner, $obligation, [
            'amount_paid' => '100.00',
            'payment_date' => now()->toDateString(),
            'covered_period' => 'September 2026',
            'payment_method' => 'GCash',
        ], UploadedFile::fake()->create('script.php', 10, 'text/x-php'));
    }

    public function test_staff_review_approves_a_payment_proof_once_and_then_allocates_it(): void
    {
        Storage::fake('local');
        $obligation = $this->obligation(['amount_due' => '100.10']);
        $owner = $obligation->homeowner->user;
        $admin = User::factory()->create(['account_status' => 'Active']);
        $admin->assignRole('hoa_admin');
        $payment = app(SubmitPaymentProof::class)->handle($owner, $obligation, [
            'amount_paid' => '100.10',
            'payment_date' => now()->toDateString(),
            'covered_period' => 'September 2026',
            'payment_method' => 'GCash',
        ], UploadedFile::fake()->create('proof.pdf', 200, 'application/pdf'));

        $approved = app(ReviewPaymentProof::class)->approve($payment, $admin);
        $this->assertSame('Approved', $approved->review_status);
        $this->assertSame('Paid', $approved->status);
        $this->assertSame('0.00', $approved->balance);
        $this->assertSame('100.10', $obligation->refresh()->amount_paid);

        $this->expectException(ValidationException::class);
        app(ReviewPaymentProof::class)->approve($approved, $admin);
    }

    public function test_approved_payment_receipt_is_a_private_pdf(): void
    {
        Storage::fake('local');
        $obligation = $this->obligation(['amount_due' => '50.00']);
        $owner = $obligation->homeowner->user;
        $admin = User::factory()->create(['account_status' => 'Active']);
        $admin->assignRole('hoa_admin');
        $payment = app(SubmitPaymentProof::class)->handle($owner, $obligation, [
            'amount_paid' => '50.00',
            'payment_date' => now()->toDateString(),
            'covered_period' => 'September 2026',
            'payment_method' => 'GCash',
        ], UploadedFile::fake()->create('proof.pdf', 200, 'application/pdf'));
        $payment = app(ReviewPaymentProof::class)->approve($payment, $admin);

        $this->actingAs($owner, 'web')
            ->get(route('payments.receipt.download', $payment))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->actingAs($this->homeowner()->user, 'web')
            ->get(route('payments.receipt.download', $payment))
            ->assertNotFound();
    }

    public function test_configured_consecutive_overdue_months_flag_and_notify_homeowner_once(): void
    {
        Notification::fake();
        SystemSetting::current()->update(['delinquency_months' => 3, 'delinquency_notifications' => true]);
        $homeowner = $this->homeowner();
        $setting = DuesSetting::query()->create(['name' => 'Consecutive dues', 'amount' => '100.00', 'frequency' => 'Monthly', 'is_active' => true]);
        foreach ([7, 8, 9] as $month) {
            DuesObligation::query()->create([
                'homeowner_id' => $homeowner->id,
                'dues_setting_id' => $setting->id,
                'billing_year' => 2026,
                'billing_month' => $month,
                'due_date' => "2026-{$month}-01",
                'amount_due' => '100.00',
                'penalty_amount' => '0.00',
                'amount_paid' => '0.00',
                'status' => 'Overdue',
            ]);
        }

        $this->assertSame(1, app(FlagDelinquentHomeowners::class)->handle(CarbonImmutable::create(2026, 9, 30)));
        $this->assertSame('Delinquent', $homeowner->refresh()->status);
        Notification::assertSentTo($homeowner->user, HomeownerDelinquencyNotice::class);
        $this->assertSame(0, app(FlagDelinquentHomeowners::class)->handle(CarbonImmutable::create(2026, 9, 30)));
    }

    /** @param array<string, mixed> $attributes */
    private function obligation(array $attributes = []): DuesObligation
    {
        $homeowner = $this->homeowner();
        $setting = DuesSetting::query()->create(['name' => 'Monthly Dues '.DuesSetting::query()->count(), 'amount' => '100.00', 'frequency' => 'Monthly', 'is_active' => true]);

        return DuesObligation::query()->create([
            'homeowner_id' => $homeowner->id,
            'dues_setting_id' => $setting->id,
            'billing_year' => 2026,
            'billing_month' => DuesObligation::query()->count() + 1,
            'due_date' => '2026-09-10',
            'amount_due' => '100.00',
            'penalty_amount' => '0.00',
            'amount_paid' => '0.00',
            'status' => 'Pending',
            ...$attributes,
        ]);
    }

    private function homeowner(): Homeowner
    {
        $index = User::query()->count() + 1;
        $user = User::factory()->create(['email' => "dues{$index}@example.test", 'account_status' => 'Active']);
        $user->assignRole('homeowner');

        return $user->homeowner()->create([
            'house_number' => (string) $index,
            'street' => 'Dues Street',
            'block' => "B {$index}",
            'lot' => "L {$index}",
            'phase' => 'Southville Phase I',
            'residency_date' => '2021-01-01',
            'ownership_type' => 'Owner',
            'emergency_contact_name' => 'Contact',
            'emergency_contact_number' => '09123456789',
            'status' => 'Active',
        ]);
    }

    /** @return array<string, mixed> */
    private function paymentPayload(DuesObligation $obligation, string $amount): array
    {
        return [
            'dues_obligation_id' => $obligation->id,
            'amount_paid' => $amount,
            'penalty' => '0.00',
            'payment_date' => now()->toDateString(),
            'covered_period' => 'September 2026',
            'payment_method' => 'Cash',
            'notes' => null,
        ];
    }
}
