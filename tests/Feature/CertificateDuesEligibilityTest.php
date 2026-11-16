<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Certificates\IssueCertificate;
use App\Actions\Payments\ChangePaymentDeletionState;
use App\Actions\Payments\RecordPayment;
use App\Actions\Payments\ReviewPaymentProof;
use App\Actions\ServiceRequests\CreateServiceRequest;
use App\Actions\ServiceRequests\UpdateServiceRequest;
use App\Exceptions\CertificateDuesBlocked;
use App\Filament\Homeowner\Resources\ServiceRequests\Pages\CreateServiceRequest as HomeownerCreate;
use App\Filament\Resources\Certificates\Pages\CreateCertificate;
use App\Models\Certificate;
use App\Models\CertificatePreview;
use App\Models\DuesObligation;
use App\Models\DuesSetting;
use App\Models\Homeowner;
use App\Models\Payment;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\CertificateDuesEligibility;
use App\Services\ResidencyCertificatePreviews;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\UsesUploadedResidency;
use Tests\TestCase;

final class CertificateDuesEligibilityTest extends TestCase
{
    use RefreshDatabase;
    use UsesUploadedResidency;

    private Homeowner $owner;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();
        Http::preventStrayRequests();
        $this->owner = $this->resident();
        $this->admin = User::factory()->create(['account_status' => 'Active']);
        $this->admin->assignRole('hoa_admin');
    }

    public static function balances(): array
    {
        return [
            'pending future' => ['Pending', '0.00', '0.00', 'Monthly', false],
            'partial' => ['Partial', '50.00', '0.00', 'Monthly', false],
            'overdue' => ['Overdue', '0.00', '0.00', 'Monthly', false],
            'paid' => ['Paid', '100.00', '0.00', 'Monthly', true],
            'unpaid penalty' => ['Partial', '100.00', '10.00', 'Monthly', false],
            'cancelled' => ['Cancelled', '0.00', '0.00', 'Monthly', true],
            'annual' => ['Pending', '0.00', '0.00', 'Annual', true],
            'special' => ['Pending', '0.00', '0.00', 'Special Assessment', true],
            'zero despite stale status' => ['Overdue', '100.00', '0.00', 'Monthly', true],
            'balance despite paid label' => ['Paid', '0.00', '0.00', 'Monthly', false],
        ];
    }

    #[DataProvider('balances')]
    public function test_exact_assessed_balances_control_eligibility(string $status, string $paid, string $penalty, string $frequency, bool $eligible): void
    {
        $this->due($this->owner, ['status' => $status, 'amount_paid' => $paid, 'penalty_amount' => $penalty], $frequency);
        $result = app(CertificateDuesEligibility::class)->inspect($this->owner);
        $this->assertSame($eligible ? 'eligible' : 'outstanding', $result['state']);
    }

    public function test_no_assessments_does_not_generate_any_or_require_a_delinquent_label_reset(): void
    {
        $this->owner->update(['status' => 'Delinquent']);
        $this->assertSame('eligible', app(CertificateDuesEligibility::class)->inspect($this->owner)['state']);
        $this->assertDatabaseCount('dues_obligations', 0);
    }

    public function test_both_certificate_types_are_blocked_for_homeowner_and_admin_without_side_effects(): void
    {
        $this->due($this->owner);
        foreach ([$this->owner->user, $this->admin] as $actor) {
            foreach (['Certificate of Residency', 'Certificate of Cohabitation'] as $type) {
                try {
                    app(CreateServiceRequest::class)->handle($this->owner, $this->data($type), uploader: $actor);
                    $this->fail('Outstanding dues must block requests.');
                } catch (CertificateDuesBlocked $exception) {
                    $this->assertSame(__(CertificateDuesEligibility::OUTSTANDING), $exception->errors()['request_type'][0]);
                }
            }
        }
        $this->assertDatabaseCount('service_requests', 0);
        $this->assertDatabaseCount('certificate_previews', 0);
        $this->assertDatabaseCount('certificates', 0);
        Notification::assertNothingSent();
    }

    public function test_full_cash_settlement_restores_requests_while_partial_payment_does_not(): void
    {
        $due = $this->due($this->owner);
        $this->owner->update(['status' => 'Delinquent']);
        $payload = ['dues_obligation_id' => $due->id, 'amount_paid' => '40.00', 'payment_method' => 'Cash', 'payment_date' => today()->toDateString()];
        app(RecordPayment::class)->handle($payload, $this->admin);
        $this->assertSame('outstanding', app(CertificateDuesEligibility::class)->inspect($this->owner)['state']);
        app(RecordPayment::class)->handle([...$payload, 'amount_paid' => '60.00'], $this->admin);
        $this->assertSame('Paid', $due->fresh()->status);
        $request = app(CreateServiceRequest::class)->handle($this->owner, $this->data(), uploader: $this->owner->user);
        $this->assertSame('Pending', $request->status);
        $this->assertSame('Delinquent', $this->owner->fresh()->status);
    }

    public function test_pending_legacy_proof_is_not_credit_but_approval_is(): void
    {
        $due = $this->due($this->owner);
        $proof = Payment::create(['homeowner_id' => $this->owner->id, 'dues_setting_id' => $due->dues_setting_id, 'dues_obligation_id' => $due->id,
            'amount_paid' => '100.00', 'penalty' => '0.00', 'balance' => '100.00', 'payment_date' => today(), 'covered_period' => 'Synthetic', 'payment_method' => 'Cash', 'review_status' => 'Pending', 'status' => 'Pending', 'recorded_by' => $this->admin->id]);
        $this->assertSame('outstanding', app(CertificateDuesEligibility::class)->inspect($this->owner)['state']);
        app(ReviewPaymentProof::class)->approve($proof, $this->admin);
        $this->assertSame('eligible', app(CertificateDuesEligibility::class)->inspect($this->owner)['state']);
    }

    public function test_verified_tenant_and_coowner_share_the_liable_balance_but_other_phases_do_not(): void
    {
        $tenant = $this->resident(['ownership_type' => 'Tenant', 'landlord_homeowner_id' => $this->owner->id]);
        $coowner = $this->resident(['ownership_type' => 'Co-owner']);
        $other = $this->resident(['phase_suffix' => 'B']);
        $this->due($this->owner);
        foreach ([$tenant, $coowner] as $resident) {
            $result = app(CertificateDuesEligibility::class)->inspect($resident);
            $this->assertSame('outstanding', $result['state']);
            $this->assertSame('100.00', $result['balance']);
        }
        $this->assertSame('eligible', app(CertificateDuesEligibility::class)->inspect($other)['state']);
    }

    public function test_unlinked_tenant_and_conflicting_legacy_phase_require_review(): void
    {
        $tenant = $this->resident(['ownership_type' => 'Tenant', 'block' => '99']);
        $this->assertSame('review', app(CertificateDuesEligibility::class)->inspect($tenant)['state']);
        $this->resident(['phase_suffix' => null, 'ownership_type' => 'Co-owner']);
        $this->assertSame('review', app(CertificateDuesEligibility::class)->inspect($this->owner)['state']);
    }

    public function test_approval_rolls_back_but_notes_and_rejection_remain_available(): void
    {
        $request = $this->requestRecord();
        $this->due($this->owner);
        try {
            app(UpdateServiceRequest::class)->handle($request, ['status' => 'Approved', 'subject' => 'Not saved', 'details' => 'Synthetic'], $this->admin);
            $this->fail('Approval must fail.');
        } catch (CertificateDuesBlocked $exception) {
            $this->assertArrayHasKey('status', $exception->errors());
        }
        $this->assertSame('Processing', $request->fresh()->status);
        $this->assertSame('Synthetic', $request->fresh()->subject);
        app(UpdateServiceRequest::class)->handle($request->fresh(), ['status' => 'Rejected', 'subject' => 'Synthetic', 'details' => 'Synthetic', 'admin_remarks' => 'Synthetic rejection'], $this->admin);
        $this->assertSame('Rejected', $request->fresh()->status);
    }

    public function test_new_previews_and_old_unissued_tokens_cannot_bypass_dues(): void
    {
        $request = $this->requestRecord('Approved');
        $token = str_repeat('x', 64);
        CertificatePreview::create(['token_hash' => hash('sha256', $token), 'actor_id' => $this->admin->id, 'payload' => ['homeowner_id' => $this->owner->id, 'type' => 'Certificate of Residency', 'purpose' => 'Synthetic', 'service_request_id' => $request->id], 'snapshot' => [], 'fingerprint' => 'synthetic', 'expires_at' => now()->addMinutes(10)]);
        $this->due($this->owner);
        foreach (['prepare', 'read'] as $method) {
            try {
                $service = app(ResidencyCertificatePreviews::class);
                $method === 'prepare' ? $service->prepare(['homeowner_id' => $this->owner->id], $this->admin) : $service->read($token, $this->admin);
                $this->fail('Dues must block previews.');
            } catch (CertificateDuesBlocked $exception) {
                $this->assertArrayHasKey('homeowner_id', $exception->errors());
            }
        }
        $this->actingAs($this->owner->user, 'homeowner')->get(route('service-requests.draft', $request))->assertForbidden();
        try {
            app(IssueCertificate::class)->fromApprovedRequest($request, $this->admin, 'Synthetic', $token);
            $this->fail('An old token must not authorize issuance while dues are unpaid.');
        } catch (CertificateDuesBlocked $exception) {
            $this->assertArrayHasKey('homeowner_id', $exception->errors());
        }
        $this->assertSame('Approved', $request->fresh()->status);
        $this->assertDatabaseCount('certificates', 0);
        $this->assertDatabaseCount('certificate_previews', 1);
    }

    public function test_homeowner_form_shows_one_specific_error_and_no_success(): void
    {
        $this->due($this->owner);
        Filament::setCurrentPanel(Filament::getPanel('homeowner'));
        $this->actingAs($this->owner->user, 'homeowner');
        Livewire::test(HomeownerCreate::class)->assertSee(__(CertificateDuesEligibility::OUTSTANDING))
            ->fillForm($this->data())->call('create')->assertHasFormErrors(['request_type'])
            ->assertNotified('Certificate Processing Blocked');
        $this->assertDatabaseCount('service_requests', 0);
    }

    public function test_payment_archival_restoration_and_rollback_recalculate_live_eligibility(): void
    {
        $due = $this->due($this->owner);
        $payment = app(RecordPayment::class)->handle(['dues_obligation_id' => $due->id, 'amount_paid' => '100.00', 'payment_method' => 'Cash', 'payment_date' => today()->toDateString()], $this->admin);
        $this->assertSame('eligible', app(CertificateDuesEligibility::class)->inspect($this->owner)['state']);
        app(ChangePaymentDeletionState::class)->delete($payment, $this->admin);
        $this->assertSame('outstanding', app(CertificateDuesEligibility::class)->inspect($this->owner)['state']);
        app(ChangePaymentDeletionState::class)->restore($payment, $this->admin);
        $this->assertSame('eligible', app(CertificateDuesEligibility::class)->inspect($this->owner)['state']);
        DB::beginTransaction();
        $due->refresh()->update(['amount_paid' => '0.00', 'status' => 'Pending']);
        DB::rollBack();
        $this->assertSame('eligible', app(CertificateDuesEligibility::class)->inspect($this->owner)['state']);
    }

    public function test_unauthorized_preview_does_not_disclose_dues_and_fil_translation_is_available(): void
    {
        $request = $this->requestRecord('Approved');
        $other = $this->resident(['block' => '99']);
        $this->due($this->owner);
        $this->actingAs($other->user, 'homeowner')->get(route('service-requests.draft', $request))
            ->assertForbidden()->assertDontSee(__(CertificateDuesEligibility::OUTSTANDING));
        app()->setLocale('fil');
        $this->assertNotSame(CertificateDuesEligibility::OUTSTANDING, __(CertificateDuesEligibility::OUTSTANDING));
        $this->assertNotSame(CertificateDuesEligibility::REVIEW, __(CertificateDuesEligibility::REVIEW));
    }

    public function test_issued_preview_read_is_not_restricted_by_new_debt(): void
    {
        $certificate = Certificate::withoutEvents(fn () => Certificate::create(['homeowner_id' => $this->owner->id, 'certificate_number' => 'SYNTHETIC-ISSUED',
            'type' => 'Certificate of Residency', 'status' => 'Issued', 'issued_at' => now(), 'issued_by' => $this->admin->id]));
        $token = str_repeat('z', 64);
        CertificatePreview::create(['token_hash' => hash('sha256', $token), 'actor_id' => $this->admin->id, 'certificate_id' => $certificate->id,
            'payload' => ['homeowner_id' => $this->owner->id, 'type' => 'Certificate of Residency', 'purpose' => '', 'service_request_id' => null], 'snapshot' => [], 'fingerprint' => 'synthetic', 'expires_at' => now()->addMinutes(10)]);
        $this->due($this->owner);
        $this->assertSame($certificate->id, app(ResidencyCertificatePreviews::class)->read($token, $this->admin)->certificate_id);
    }

    public function test_standalone_issuance_rechecks_dues_after_a_valid_preview_and_shows_specific_feedback(): void
    {
        Storage::fake('local');
        $this->confirmUploadedSource();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->admin, 'admin');
        $page = Livewire::test(CreateCertificate::class)
            ->fillForm(['document_kind' => 'certificate', 'homeowner_id' => $this->owner->id, 'type' => 'Certificate of Residency', 'purpose' => 'Synthetic'])
            ->call('preparePreview')->assertHasNoFormErrors();
        $this->due($this->owner);
        $page->call('create')->assertHasFormErrors(['homeowner_id'])->assertNotified('Certificate Processing Blocked');
        $this->assertDatabaseCount('certificates', 0);
    }

    private function resident(array $overrides = []): Homeowner
    {
        $user = User::factory()->create(['account_status' => 'Active', 'email_verified_at' => now()]);
        $user->assignRole('homeowner');

        return $user->homeowner()->create(['block' => '7', 'lot' => '8', 'phase' => 'Southville Phase I', 'phase_suffix' => 'A', 'status' => 'Active', 'ownership_type' => 'Owner', 'residency_date' => '2020-01-01', ...$overrides]);
    }

    private function due(Homeowner $owner, array $overrides = [], string $frequency = 'Monthly'): DuesObligation
    {
        $setting = DuesSetting::create(['name' => 'Synthetic '.uniqid(), 'amount' => '100.00', 'frequency' => $frequency, 'starts_on' => '2026-01-01', 'is_active' => true]);

        return DuesObligation::create(['homeowner_id' => $owner->id, 'dues_setting_id' => $setting->id, 'billing_year' => now()->year, 'billing_month' => now()->month,
            'due_date' => today()->addMonth(), 'amount_due' => '100.00', 'penalty_amount' => '0.00', 'amount_paid' => '0.00', 'status' => 'Pending', ...$overrides]);
    }

    private function data(string $type = 'Certificate of Residency'): array
    {
        return ['request_type' => $type, 'subject' => 'Synthetic', 'details' => 'Synthetic', 'cohabitant_one' => 'Synthetic One', 'cohabitant_two' => 'Synthetic Two'];
    }

    private function requestRecord(string $status = 'Processing'): ServiceRequest
    {
        return ServiceRequest::create(['homeowner_id' => $this->owner->id, ...$this->data(), 'status' => $status]);
    }
}
