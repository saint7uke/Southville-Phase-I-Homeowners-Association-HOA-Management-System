<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Dues\MarkOverdueObligations;
use App\Actions\Payments\RecordPayment;
use App\Actions\Users\CreatePendingUser;
use App\Models\AuditLog;
use App\Models\DuesObligation;
use App\Models\DuesSetting;
use App\Models\User;
use App\Notifications\OverdueDuesNotice;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use LogicException;
use Tests\TestCase;

final class CompletionRequirementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_recorded_payment_receipt_and_soft_delete_restore_keep_ledger_consistent(): void
    {
        $staff = User::factory()->create(['account_status' => 'Active']);
        $staff->assignRole('hoa_staff');
        $admin = User::factory()->create(['account_status' => 'Active']);
        $admin->assignRole('hoa_admin');
        $obligation = $this->obligation('100.00');

        $payment = app(RecordPayment::class)->handle([
            'dues_obligation_id' => $obligation->id,
            'amount_paid' => '100.00',
            'penalty' => '0.00',
            'payment_date' => today()->toDateString(),
            'covered_period' => 'September 2026',
            'payment_method' => 'Cash',
        ], $staff);

        $this->assertTrue($staff->can('update', $payment));
        $this->assertTrue($admin->can('update', $payment));
        $this->actingAs($obligation->homeowner->user, 'web')->get(route('payments.receipt.download', $payment))->assertOk();

        $payment->delete();
        $this->assertSame('0.00', $obligation->refresh()->amount_paid);
        $this->assertSame('Pending', $obligation->status);

        $payment->restore();
        $this->assertSame('100.00', $obligation->refresh()->amount_paid);
        $this->assertSame('Paid', $obligation->status);
    }

    public function test_staff_cannot_edit_another_staff_members_payment(): void
    {
        $first = User::factory()->create(['account_status' => 'Active']);
        $first->assignRole('hoa_staff');
        $second = User::factory()->create(['account_status' => 'Active']);
        $second->assignRole('hoa_staff');
        $payment = app(RecordPayment::class)->handle([
            'dues_obligation_id' => $this->obligation('100.00')->id,
            'amount_paid' => '50.00',
            'payment_date' => today()->toDateString(),
            'covered_period' => 'September 2026',
            'payment_method' => 'Cash',
        ], $first);

        $this->assertTrue($first->can('update', $payment));
        $this->assertFalse($second->can('update', $payment));
    }

    public function test_overdue_transition_sends_one_queued_notice(): void
    {
        Notification::fake();
        $obligation = $this->obligation('100.00', '2026-08-01');

        $this->assertSame(1, app(MarkOverdueObligations::class)->handle(CarbonImmutable::parse('2026-09-01')));
        $this->assertSame(0, app(MarkOverdueObligations::class)->handle(CarbonImmutable::parse('2026-09-01')));
        Notification::assertSentToTimes($obligation->homeowner->user, OverdueDuesNotice::class, 1);
    }

    public function test_admin_can_create_a_pending_homeowner_account_with_linked_profile(): void
    {
        $admin = User::factory()->create(['account_status' => 'Active']);
        $admin->assignRole('hoa_admin');

        $user = app(CreatePendingUser::class)->handle([
            'first_name' => 'Maria',
            'last_name' => 'Santos',
            'sex' => 'Female',
            'contact_number' => '09171234567',
            'date_of_birth' => '1990-01-01',
            'email' => 'maria.homeowner@example.test',
            'password' => 'SecureResident!2026',
            'role' => 'homeowner',
            'house_number' => '12',
            'street' => 'Narra Street',
            'block' => '2',
            'lot' => '8',
            'residency_date' => '2020-01-01',
            'ownership_type' => 'Owner',
            'emergency_contact_name' => 'Jose Santos',
            'emergency_contact_number' => '09179876543',
        ], $admin);

        $this->assertTrue($user->hasRole('homeowner'));
        $this->assertSame('Pending', $user->account_status);
        $this->assertSame('Inactive', $user->homeowner->status);
        $this->assertSame('8', $user->homeowner->lot);
    }

    public function test_audit_log_cannot_be_changed_or_deleted_through_the_model(): void
    {
        $log = AuditLog::query()->create(['action' => 'Test.created']);

        try {
            $log->update(['action' => 'Test.changed']);
            $this->fail('Audit update should have been rejected.');
        } catch (LogicException) {
            $this->assertSame('Test.created', $log->refresh()->action);
        }

        $this->expectException(LogicException::class);
        $log->delete();
    }

    private function obligation(string $amount, ?string $dueDate = null): DuesObligation
    {
        $user = User::factory()->create(['account_status' => 'Active']);
        $user->assignRole('homeowner');
        $homeowner = $user->homeowner()->create([
            'house_number' => (string) $user->id,
            'street' => 'Test Street',
            'block' => (string) $user->id,
            'lot' => (string) $user->id,
            'phase' => 'Southville Phase I',
            'residency_date' => '2020-01-01',
            'ownership_type' => 'Owner',
            'emergency_contact_name' => 'Contact Person',
            'emergency_contact_number' => '09123456789',
            'status' => 'Active',
        ]);
        $setting = DuesSetting::query()->create(['name' => 'Test dues '.$user->id, 'amount' => $amount, 'frequency' => 'Monthly', 'is_active' => true]);

        return DuesObligation::query()->create([
            'homeowner_id' => $homeowner->id,
            'dues_setting_id' => $setting->id,
            'billing_year' => 2026,
            'billing_month' => 9,
            'due_date' => $dueDate ?? today()->addDay()->toDateString(),
            'amount_due' => $amount,
            'penalty_amount' => '0.00',
            'amount_paid' => '0.00',
            'status' => 'Pending',
        ]);
    }
}
