<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Dues\GenerateSettingObligations;
use App\Actions\Homeowners\RegisterHomeowner;
use App\Actions\Payments\ChangePaymentDeletionState;
use App\Actions\Payments\RecordPayment;
use App\Actions\Payments\ReviewPaymentProof;
use App\Actions\Payments\SubmitPaymentProof;
use App\Actions\Payments\UpdatePaymentMetadata;
use App\Exports\HoaReportExport;
use App\Filament\Homeowner\Widgets\HomeownerOverview;
use App\Filament\Pages\Reports;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Widgets\HoaOverview;
use App\Models\Announcement;
use App\Models\Certificate;
use App\Models\Complaint;
use App\Models\DuesObligation;
use App\Models\DuesSetting;
use App\Models\Homeowner;
use App\Models\Payment;
use App\Models\ServiceRequest;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\LandlordMatcher;
use App\Services\PropertyDuesAccounts;
use App\Services\Reporting\HoaReportCatalog;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Mockery;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\Support\LegacyPaymentProof;
use Tests\Support\UsesUploadedResidency;
use Tests\TestCase;

final class SystemAuditRegressionTest extends TestCase
{
    use RefreshDatabase;
    use UsesUploadedResidency;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        SystemSetting::current()->forceFill(['certificate_signatory_name' => 'Test Authorized Signatory', 'certificate_signatory_title' => 'President', 'signatory_confirmed_at' => now()])->save();
    }

    public function test_invalid_legacy_payment_does_not_leave_an_obligation(): void
    {
        [$owner, $setting] = $this->property();
        $staff = User::factory()->create();
        $staff->assignRole('hoa_staff');
        try {
            app(RecordPayment::class)->handle([
                'homeowner_id' => $owner->id,
                'dues_setting_id' => $setting->id,
                'amount_paid' => '-5.00',
                'payment_date' => today()->toDateString(),
                'payment_method' => 'Cash',
            ], $staff);
            $this->fail('Invalid money must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount_paid', $exception->errors());
        }
        $this->assertDatabaseCount('dues_obligations', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_partial_payment_on_the_due_date_is_not_prematurely_overdue(): void
    {
        [$owner, $setting] = $this->property();
        $due = $this->obligation($owner, $setting);
        $due->update(['due_date' => today()]);
        $staff = User::factory()->create();
        $staff->assignRole('hoa_admin');
        $recorded = app(RecordPayment::class)->handle([
            'dues_obligation_id' => $due->id, 'amount_paid' => '50.00',
            'payment_date' => today()->toDateString(), 'payment_method' => 'Cash',
        ], $staff);
        $this->assertSame('Partial', $due->fresh()->status);
        $proof = LegacyPaymentProof::create($owner->user, $due->fresh(), $this->payload(), UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'));
        app(ReviewPaymentProof::class)->approve($proof, $staff);
        $this->assertSame('Partial', $due->fresh()->status);
        app(ChangePaymentDeletionState::class)->delete($recorded, $staff);
        $this->assertSame('Partial', $due->fresh()->status);
        app(ChangePaymentDeletionState::class)->restore($recorded->fresh(), $staff);
        $this->assertSame('Partial', $due->fresh()->status);
    }

    public function test_inactive_liable_resident_cannot_bypass_payer_eligibility(): void
    {
        [$owner] = $this->property();
        $owner->user->forceFill(['account_status' => 'Suspended'])->saveQuietly();
        $owner->load('user');
        $this->assertFalse(app(PropertyDuesAccounts::class)->payerIsEligible($owner, $owner));
    }

    public function test_legacy_household_payments_use_one_liable_property_obligation(): void
    {
        [$owner, $setting] = $this->property();
        $payer = User::factory()->create();
        $payer->assignRole('homeowner');
        $coOwner = $payer->homeowner()->create([
            'house_number' => '1', 'street' => 'Audit Street', 'block' => '1', 'lot' => '1',
            'phase' => 'Southville Phase I', 'residency_date' => '2020-01-01', 'ownership_type' => 'Co-owner', 'status' => 'Active',
        ]);
        $staff = User::factory()->create();
        $staff->assignRole('hoa_staff');
        $payload = [
            'dues_setting_id' => $setting->id, 'amount_paid' => '50.00',
            'payment_date' => today()->toDateString(), 'payment_method' => 'Cash',
        ];
        $first = app(RecordPayment::class)->handle([...$payload, 'homeowner_id' => $coOwner->id], $staff);
        $second = app(RecordPayment::class)->handle([...$payload, 'homeowner_id' => $owner->id], $staff);
        $this->assertSame($owner->id, $first->homeowner_id);
        $this->assertSame($coOwner->id, $first->payer_homeowner_id);
        $this->assertSame($first->dues_obligation_id, $second->dues_obligation_id);
        $this->assertDatabaseCount('dues_obligations', 1);
    }

    public function test_deleted_landlord_user_cannot_match_a_current_homeowner_record(): void
    {
        [$owner] = $this->property();
        $name = $owner->user->full_name;
        $owner->user->delete();
        $result = app(LandlordMatcher::class)->match([
            'landlord_name' => $name, 'house_number' => '1', 'street' => 'Audit Street',
            'block' => '1', 'lot' => '1', 'phase' => 'Southville Phase I',
        ]);
        $this->assertFalse($result['valid']);
    }

    public function test_inactive_owner_cannot_submit_proof_directly(): void
    {
        Storage::fake('local');
        [$owner, $setting] = $this->property();
        $due = $this->obligation($owner, $setting);
        $owner->user->forceFill(['account_status' => 'Inactive'])->saveQuietly();
        $this->expectException(AuthorizationException::class);
        app(SubmitPaymentProof::class)->handle($owner->user->fresh(), $due, $this->payload(), UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'));
    }

    public function test_revoked_proof_submission_permission_is_enforced_by_the_action(): void
    {
        Storage::fake('local');
        [$owner, $setting] = $this->property();
        $due = $this->obligation($owner, $setting);
        Role::findByName('homeowner')->revokePermissionTo('submit_own_payment_proof');
        $this->expectException(AuthorizationException::class);
        app(SubmitPaymentProof::class)->handle($owner->user->fresh(), $due, $this->payload(), UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'));
    }

    public function test_recording_cannot_replay_a_proof_submission_token(): void
    {
        Storage::fake('local');
        [$owner, $setting] = $this->property();
        $due = $this->obligation($owner, $setting);
        $payload = $this->payload();
        LegacyPaymentProof::create($owner->user, $due, $payload, UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'));
        $staff = User::factory()->create();
        $staff->assignRole('hoa_staff');
        $this->expectException(ValidationException::class);
        app(RecordPayment::class)->handle([...$payload, 'payment_method' => 'Cash', 'dues_obligation_id' => $due->id, 'payer_homeowner_id' => $owner->id], $staff);
    }

    public function test_delinquent_active_owner_remains_billable_and_can_pay(): void
    {
        [$owner, $setting] = $this->property();
        $owner->update(['status' => 'Delinquent']);
        $accounts = app(PropertyDuesAccounts::class);
        $this->assertSame($owner->id, $accounts->primaryForAddress($owner->address_key)?->id);
        $this->assertTrue($accounts->payerIsEligible($owner, $owner));
        $this->assertTrue($accounts->eligiblePayers($owner)->whereKey($owner->id)->exists());
        $this->assertSame(1, app(GenerateSettingObligations::class)->handle($setting, CarbonImmutable::create(2026, 10, 1)));
    }

    public function test_suspended_admin_cannot_export_using_a_stale_session(): void
    {
        $admin = User::factory()->create(['account_status' => 'Suspended']);
        $admin->assignRole('hoa_admin');
        $this->actingAs($admin, 'admin')->get(route('admin.reports.download', ['report' => 'homeowners', 'format' => 'csv']))->assertForbidden();
    }

    public function test_inactive_resident_cannot_download_a_private_profile_photo(): void
    {
        Storage::fake('local');
        [$owner] = $this->property();
        Storage::disk('local')->put('homeowner-profile-photos/audit.jpg', 'private');
        $owner->update(['profile_photo' => 'homeowner-profile-photos/audit.jpg', 'profile_photo_disk' => 'local']);
        $owner->user->forceFill(['account_status' => 'Inactive'])->saveQuietly();
        $this->actingAs($owner->user->fresh(), 'homeowner')->get(route('homeowner.profile-photo', $owner))->assertForbidden();
    }

    public function test_preview_clamps_forged_page_and_page_size_without_recursive_queries(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('hoa_admin');
        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(Reports::class)->call('previewReport', 'homeowners')
            ->set('previewPerPage', 100000)->call('previewPage', 100000)
            ->assertSet('previewPage', 1)->assertSet('previewPerPage', 10);
    }

    public function test_registration_storage_failure_rolls_back_all_records(): void
    {
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('putFileAs')->once()->andReturn(false);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);
        try {
            app(RegisterHomeowner::class)->handle([
                'first_name' => 'Audit', 'last_name' => 'Registration', 'sex' => 'Male',
                'contact_number' => '09171234567', 'date_of_birth' => '1990-01-01',
                'email' => 'audit-storage@example.test', 'password' => 'AuditPassword2026!',
                'phase_suffix' => 'A', 'street' => 'Storage Street', 'block' => '1', 'lot' => '2',
                'ownership_type' => 'Owner', 'residency_date' => '2020-01-01',
                'verification_document' => UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'),
            ]);
            $this->fail('Storage failure must not create a registration.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('verification_document', $exception->errors());
        }
        $this->assertDatabaseMissing('users', ['email' => 'audit-storage@example.test']);
        $this->assertDatabaseCount('homeowners', 0);
        $this->assertDatabaseCount('model_has_roles', 0);
    }

    public function test_read_only_audit_detects_a_ledger_mismatch_without_changing_it(): void
    {
        [$owner, $setting] = $this->property();
        $due = $this->obligation($owner, $setting);
        $due->update(['amount_paid' => '10.00']);
        $this->artisan('hoa:audit-data', ['--json' => true])->expectsOutputToContain('"obligations_with_accepted_payment_mismatch": 1')->assertFailed();
        $this->assertSame('10.00', $due->fresh()->amount_paid);
    }

    public function test_dashboard_collections_exclude_pending_proofs(): void
    {
        Storage::fake('local');
        [$owner, $setting] = $this->property();
        $due = $this->obligation($owner, $setting);
        $admin = User::factory()->create();
        $admin->assignRole('hoa_admin');
        app(RecordPayment::class)->handle([
            'dues_obligation_id' => $due->id, 'amount_paid' => '75.00',
            'payment_date' => today()->toDateString(), 'payment_method' => 'Cash',
        ], $admin);
        LegacyPaymentProof::create($owner->user, $due->fresh(), $this->payload(), UploadedFile::fake()->create('proof.pdf', 10, 'application/pdf'));
        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::test(HoaOverview::class)->assertSee('PHP 75.00')->assertDontSee('PHP 150.00');
    }

    public function test_reports_filter_dates_in_the_same_timezone_used_to_store_application_timestamps(): void
    {
        [$homeowner] = $this->property();
        foreach (['2026-10-17 23:59:59', '2026-10-18 00:00:00', '2026-10-18 23:59:59', '2026-10-19 00:00:00'] as $index => $timestamp) {
            $record = Complaint::query()->create([
                'homeowner_id' => $homeowner->id, 'ticket_number' => 'CMP-TIME-'.$index,
                'subject' => 'Date boundary '.$index, 'description' => 'Local application timestamp boundary.',
                'category' => 'Common Area', 'priority' => 'Medium', 'status' => 'Pending',
            ]);
            $record->forceFill(['created_at' => CarbonImmutable::parse($timestamp, config('app.timezone'))])->saveQuietly();
        }
        $catalog = app(HoaReportCatalog::class);
        $filters = $catalog->validateFilters('complaints', ['from' => '2026-10-18', 'to' => '2026-10-18', 'sort' => 'ticket'], true);
        $this->assertSame(['CMP-TIME-1', 'CMP-TIME-2'], $catalog->query('complaints', $filters)->pluck('ticket_number')->all());
    }

    public function test_linked_tenant_dashboard_shows_the_property_balance(): void
    {
        [$owner, $setting] = $this->property();
        $this->obligation($owner, $setting);
        $tenantUser = User::factory()->create();
        $tenantUser->assignRole('homeowner');
        $tenantUser->homeowner()->create([
            'house_number' => '1', 'street' => 'Audit Street', 'block' => '1', 'lot' => '1',
            'phase' => 'Southville Phase I', 'residency_date' => '2020-01-01', 'ownership_type' => 'Tenant',
            'status' => 'Active', 'landlord_homeowner_id' => $owner->id,
        ]);
        $this->actingAs($tenantUser, 'homeowner');
        Filament::setCurrentPanel(Filament::getPanel('homeowner'));
        Livewire::test(HomeownerOverview::class)->assertSee('PHP 500.00')->assertDontSee('Your account is current');
    }

    public function test_bulk_payment_deletion_reconciles_the_ledger_inside_a_transaction(): void
    {
        [$owner, $setting] = $this->property();
        $due = $this->obligation($owner, $setting);
        $admin = User::factory()->create();
        $admin->assignRole('hoa_admin');
        $payment = app(RecordPayment::class)->handle([
            'dues_obligation_id' => $due->id, 'amount_paid' => '75.00',
            'payment_date' => today()->toDateString(), 'payment_method' => 'Cash',
        ], $admin);
        $transactionLevel = 0;
        Payment::deleting(function () use (&$transactionLevel): void {
            $transactionLevel = DB::transactionLevel();
        });
        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $baseline = DB::transactionLevel();
        Livewire::test(ListPayments::class)->callTableBulkAction('delete', [$payment]);
        $this->assertSoftDeleted($payment);
        $this->assertGreaterThan($baseline, $transactionLevel);
        $this->assertSame('0.00', $due->fresh()->amount_paid);
    }

    public function test_stale_payment_metadata_edit_rechecks_current_review_state(): void
    {
        [$owner, $setting] = $this->property();
        $due = $this->obligation($owner, $setting);
        $admin = User::factory()->create();
        $admin->assignRole('hoa_admin');
        $payment = app(RecordPayment::class)->handle([
            'dues_obligation_id' => $due->id, 'amount_paid' => '75.00',
            'payment_date' => today()->toDateString(), 'payment_method' => 'Cash',
        ], $admin);
        Payment::query()->whereKey($payment->id)->update(['review_status' => 'Approved']);
        $this->expectException(AuthorizationException::class);
        app(UpdatePaymentMetadata::class)->handle($payment, [
            'payment_date' => today()->toDateString(), 'payment_method' => 'Cash', 'notes' => 'Stale editor',
        ], $admin);
    }

    public function test_certificate_file_is_removed_when_its_path_cannot_be_persisted(): void
    {
        Storage::fake('local');
        [$owner] = $this->property();
        $admin = User::factory()->create();
        $admin->assignRole('hoa_admin');
        Certificate::updating(function (Certificate $certificate): void {
            if ($certificate->isDirty('file_path')) {
                throw new RuntimeException('Simulated persistence failure.');
            }
        });
        try {
            $this->issueCertificate(['homeowner_id' => $owner->id, 'type' => 'Certificate of Residency'], $admin);
            $this->fail('The simulated persistence failure must abort issuance.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated persistence failure.', $exception->getMessage());
        }
        $this->assertDatabaseCount('certificates', 0);
        $this->assertSame([], Storage::disk('local')->allFiles('certificates'));
    }

    public function test_long_approved_request_can_issue_a_certificate_without_losing_request_details(): void
    {
        Storage::fake('local');
        [$owner] = $this->property();
        $admin = User::factory()->create();
        $admin->assignRole('hoa_admin');
        $request = ServiceRequest::query()->create([
            'homeowner_id' => $owner->id, 'request_type' => 'Certificate of Residency',
            'subject' => 'Long request', 'details' => str_repeat('Valid request detail. ', 40), 'status' => 'Approved',
        ]);
        $details = $request->details;
        $certificate = $this->issueRequestedCertificate($request, $admin, 'Barangay clearance');
        $this->assertSame('Barangay clearance', $certificate->purpose);
        $this->assertSame($details, $request->fresh()->details);
        $this->assertSame('Completed', $request->fresh()->status);
    }

    public function test_announcement_export_mapping_does_not_repeat_population_queries_per_row(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('hoa_admin');
        foreach (range(1, 4) as $index) {
            Announcement::query()->create([
                'title' => 'Audit announcement '.$index, 'content' => 'A readable announcement.',
                'category' => 'General', 'audience' => 'Residents', 'status' => 'Draft', 'created_by' => $admin->id,
            ]);
        }
        $export = new HoaReportExport('announcements');
        $rows = $export->query()->get();
        DB::enableQueryLog();
        DB::flushQueryLog();
        foreach ($rows as $row) {
            $export->map($row);
        }
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $populationQueries = array_filter($queries, fn (array $query): bool => str_contains(strtolower($query['query']), 'count(*)') && str_contains($query['query'], 'users'));
        $this->assertCount(1, $populationQueries);
        $this->assertLessThanOrEqual(2, count($queries));
    }

    /** @return array{Homeowner, DuesSetting} */
    private function property(): array
    {
        $user = User::factory()->create();
        $user->assignRole('homeowner');
        $owner = $user->homeowner()->create([
            'house_number' => '1', 'street' => 'Audit Street', 'block' => '1', 'lot' => '1',
            'phase' => 'Southville Phase I', 'residency_date' => '2020-01-01', 'ownership_type' => 'Owner', 'status' => 'Active',
        ])->load('user');
        $setting = DuesSetting::query()->create([
            'name' => 'Audit Dues', 'amount' => '500.00', 'frequency' => 'Monthly', 'starts_on' => '2026-01-01', 'is_active' => true,
        ]);

        return [$owner, $setting];
    }

    private function obligation(Homeowner $owner, DuesSetting $setting): DuesObligation
    {
        return DuesObligation::query()->create([
            'homeowner_id' => $owner->id, 'dues_setting_id' => $setting->id,
            'billing_year' => 2026, 'billing_month' => 10, 'due_date' => '2026-10-31',
            'amount_due' => '500.00', 'penalty_amount' => '0.00', 'amount_paid' => '0.00', 'status' => 'Pending',
        ]);
    }

    private function payload(): array
    {
        return [
            'amount_paid' => '75.00', 'payment_date' => today()->toDateString(),
            'payment_method' => 'GCash', 'transaction_reference' => 'AUDIT-123', 'submission_token' => (string) Str::uuid(),
        ];
    }
}
