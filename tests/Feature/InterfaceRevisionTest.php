<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Certificates\ManageCertificateCollection;
use App\Actions\ContactMessages\CreateResidentInquiry;
use App\Actions\Dues\PrepareMonthlyAssessment;
use App\Actions\Payments\RecordPayment;
use App\Filament\Homeowner\Pages\AnnualDues;
use App\Filament\Homeowner\Pages\MyConcerns;
use App\Models\Certificate;
use App\Models\Complaint;
use App\Models\ContactMessage;
use App\Models\DuesSetting;
use App\Models\Homeowner;
use App\Models\User;
use App\Notifications\CertificateIssued;
use App\Services\AnnualDuesSummary;
use App\Services\CertificateDuesEligibility;
use App\Services\ResidentConcerns;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

final class InterfaceRevisionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Homeowner $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 6, 10));
        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();
        Http::preventStrayRequests();
        Storage::fake('local');
        $this->admin = User::factory()->create(['account_status' => 'Active']);
        $this->admin->assignRole('hoa_admin');
        $user = User::factory()->create(['account_status' => 'Active', 'email_verified_at' => now()]);
        $user->assignRole('homeowner');
        $this->owner = $user->homeowner()->create(['block' => '7', 'lot' => '8', 'phase' => 'Southville Phase I', 'phase_suffix' => 'A', 'ownership_type' => 'Owner', 'status' => 'Active', 'residency_date' => '2020-01-01']);
    }

    public function test_registration_and_public_contact_no_longer_enroll_or_accept_guest_messages(): void
    {
        $this->get('/homeowner/register')->assertOk()->assertDontSee('data-sms-enrollment', false)->assertSee('Important Reminder')->assertSee('Data Privacy and Consent');
        $this->postJson('/contact', ['subject' => 'Guest', 'message' => 'Not allowed'])->assertUnauthorized();
        $this->postJson('/sms/verification/request', ['scope' => 'registration', 'contact_number' => '09171234567'])->assertForbidden();
        $this->postJson('/sms/verification/confirm', ['scope' => 'registration', 'code' => '123456'])->assertForbidden();
        $this->postJson('/homeowner/register', ['sms_transactional_opt_in' => true, 'sms_verification_id' => 'forged'])->assertUnprocessable()->assertJsonValidationErrors(['sms_transactional_opt_in', 'sms_verification_id', 'privacy_consent']);
        $this->assertDatabaseCount('sms_deliveries', 0);
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_inquiry_owner_is_authenticated_and_legacy_matching_email_gives_no_access(): void
    {
        $other = User::factory()->create();
        ContactMessage::create(['name' => 'Legacy', 'email' => $this->owner->user->email, 'subject' => 'Legacy public message', 'message' => 'No ownership inferred', 'status' => 'New']);
        $this->actingAs($this->owner->user, 'homeowner')->postJson('/contact', [
            'user_id' => $other->id, 'name' => 'Forged', 'email' => 'forged@example.test', 'subject' => 'My inquiry', 'message' => '<script>alert(1)</script>',
        ])->assertCreated();
        $inquiry = ContactMessage::where('subject', 'My inquiry')->sole();
        $this->assertSame($this->owner->user_id, $inquiry->user_id);
        $this->assertSame($this->owner->user->email, $inquiry->email);
        $this->assertSame(1, app(ResidentConcerns::class)->listing($this->owner->user)->total());
        $inquiry->activities()->create(['type' => 'internal_note', 'body' => 'SECRET INTERNAL NOTE', 'author_id' => $this->admin->id]);
        $inquiry->activities()->create(['type' => 'outbound_reply', 'body' => 'Visible HOA reply', 'delivery_status' => 'Sent', 'author_id' => $this->admin->id]);
        $inquiry->activities()->create(['type' => 'outbound_reply', 'body' => 'Unsent reply', 'delivery_status' => 'Failed', 'author_id' => $this->admin->id]);
        Filament::setCurrentPanel(Filament::getPanel('homeowner'));
        $page = Livewire::test(MyConcerns::class)->assertDontSee('data-concern-detail', false)
            ->mountAction('viewConcern', arguments: ['concern' => 'Inquiry:'.$inquiry->id])->assertActionMounted('viewConcern');
        // Filament renders action modals in a separate Livewire partial.
        $content = $page->instance()->getMountedAction()->getModalContent()->render();
        $this->assertStringContainsString('Visible HOA reply', $content);
        $this->assertStringNotContainsString('SECRET INTERNAL NOTE', $content);
        $this->assertStringNotContainsString('Unsent reply', $content);
        $this->assertStringContainsString('&lt;script&gt;', $content);
        $page->unmountAction()->assertActionNotMounted('viewConcern')->assertDontSee('data-concern-detail', false);
    }

    public function test_concerns_submission_derives_subject_and_priority_and_filters_both_types(): void
    {
        $this->actingAs($this->owner->user, 'homeowner');
        Filament::setCurrentPanel(Filament::getPanel('homeowner'));
        Livewire::test(MyConcerns::class)->callAction('submitConcern', data: [
            'kind' => 'Complaint', 'category' => 'Others', 'category_other' => 'Street obstruction', 'priority' => 'Urgent', 'message' => 'An obstruction blocks the common access road.',
        ])->assertHasNoActionErrors()->assertNotified('Complaint Submitted Successfully!');
        $complaint = Complaint::sole();
        $this->assertSame('Street obstruction', $complaint->subject);
        $this->assertSame('Medium', $complaint->priority);
        Livewire::test(MyConcerns::class)->callAction('submitConcern', data: ['kind' => 'Inquiry', 'subject' => 'Office hours', 'message' => 'What are the office hours?'])->assertHasNoActionErrors()->assertNotified('Inquiry Submitted Successfully!');
        $service = app(ResidentConcerns::class);
        $this->assertSame(2, $service->listing($this->owner->user)->total());
        $this->assertSame(1, $service->listing($this->owner->user, 'Inquiry', 'New')->total());
        Livewire::test(MyConcerns::class)->callAction('submitConcern', data: ['kind' => 'Complaint', 'category' => 'Others', 'message' => 'A valid description that needs a specification.'])->assertHasActionErrors(['category_other'])->assertNotNotified('Complaint Submitted Successfully!');
        $this->assertDatabaseCount('complaints', 1);
    }

    public function test_cross_account_concern_access_is_denied_and_rollbacks_create_no_inquiry(): void
    {
        $inquiry = app(CreateResidentInquiry::class)->handle($this->owner->user, ['subject' => 'Own inquiry', 'message' => 'Message']);
        $inquiry->update(['user_id' => $this->admin->id]);
        $this->actingAs($this->owner->user, 'homeowner');
        Filament::setCurrentPanel(Filament::getPanel('homeowner'));
        try {
            app(ResidentConcerns::class)->detail($this->owner->user, 'Inquiry:'.$inquiry->id);
            $this->fail('Another account inquiry must be inaccessible.');
        } catch (ModelNotFoundException) {
            $this->assertTrue(true);
        }
        DB::beginTransaction();
        app(CreateResidentInquiry::class)->handle($this->owner->user, ['subject' => 'Rollback', 'message' => 'Message']);
        DB::rollBack();
        $this->assertDatabaseMissing('contact_messages', ['subject' => 'Rollback']);
    }

    public function test_concern_modal_preserves_filters_and_is_read_only(): void
    {
        $this->actingAs($this->owner->user, 'homeowner');
        Filament::setCurrentPanel(Filament::getPanel('homeowner'));
        $complaint = Complaint::create(['homeowner_id' => $this->owner->id, 'subject' => '<b>Safety</b>', 'category' => 'Others',
            'description' => str_repeat('Long description ', 400), 'status' => 'Resolved', 'priority' => 'Medium', 'admin_remarks' => '<script>private()</script>']);
        $attachment = $complaint->caseAttachments()->create(['disk' => 'local', 'path' => 'private/proof.pdf', 'original_name' => '<b>proof</b>.pdf', 'mime_type' => 'application/pdf', 'size_bytes' => 10]);
        Notification::fake();
        $before = $complaint->fresh()->getAttributes();
        $page = Livewire::test(MyConcerns::class)->set('typeFilter', 'Complaint')->set('statusFilter', 'Resolved')->call('setPage', 2)
            ->mountAction('viewConcern', arguments: ['concern' => 'Complaint:'.$complaint->id])->assertActionMounted('viewConcern');
        $action = $page->instance()->getMountedAction();
        $this->assertNull($action->getModalSubmitAction());
        $this->assertTrue($action->isModalClosedByEscaping());
        $content = $action->getModalContent()->render();
        $this->assertStringContainsString('&lt;b&gt;Safety&lt;/b&gt;', $content);
        $this->assertStringContainsString('&lt;script&gt;private()&lt;/script&gt;', $content);
        $this->assertStringContainsString($complaint->ticket_number, $content);
        $this->assertStringContainsString('&lt;b&gt;proof&lt;/b&gt;.pdf', $content);
        $this->assertStringContainsString(route('case-attachments.download', $attachment), $content);
        $page->unmountAction()->assertSet('typeFilter', 'Complaint')->assertSet('statusFilter', 'Resolved')
            ->assertSet('paginators.page', 2)
            ->assertDontSee('data-concern-detail', false);
        $this->assertSame($before, $complaint->fresh()->getAttributes());
        Notification::assertNothingSent();
    }

    public function test_concern_modal_rejects_forged_missing_other_owner_and_archived_records(): void
    {
        $this->actingAs($this->owner->user, 'homeowner');
        Filament::setCurrentPanel(Filament::getPanel('homeowner'));
        $other = ContactMessage::create(['user_id' => $this->admin->id, 'name' => 'Other', 'email' => 'other@example.test', 'subject' => 'Not yours', 'message' => 'Private', 'status' => 'New']);
        $archived = ContactMessage::create(['user_id' => $this->owner->user_id, 'name' => 'Own', 'email' => 'own@example.test', 'subject' => 'Archived', 'message' => 'Private', 'status' => 'New']);
        $archived->delete();
        foreach (['bad', ['Inquiry:1']] as $key) {
            Livewire::test(MyConcerns::class)->mountAction('viewConcern', arguments: ['concern' => $key])->assertStatus(404);
        }
        foreach (['Inquiry:999999', 'Inquiry:'.$other->id, 'Inquiry:'.$archived->id] as $key) {
            try {
                Livewire::test(MyConcerns::class)->mountAction('viewConcern', arguments: ['concern' => $key]);
                $this->fail('An inaccessible record must not mount.');
            } catch (ModelNotFoundException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_pickup_requires_issuance_then_ready_then_collection_and_audits_changes(): void
    {
        $this->actingAs($this->admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $certificate = $this->certificate();
        Notification::fake();
        try {
            app(ManageCertificateCollection::class)->handle($certificate->id, 'collect', $this->admin);
            $this->fail('Collection requires readiness.');
        } catch (ValidationException) {
            $this->assertNull($certificate->fresh()->collected_at);
        }
        app(ManageCertificateCollection::class)->handle($certificate->id, 'ready', $this->admin);
        $this->assertSame($this->admin->id, $certificate->fresh()->ready_for_pickup_by);
        app(ManageCertificateCollection::class)->handle($certificate->id, 'collect', $this->admin);
        $this->assertNotNull($certificate->fresh()->collected_at);
        $this->assertDatabaseHas('audit_logs', ['auditable_type' => Certificate::class, 'auditable_id' => $certificate->id, 'action' => 'Certificate.updated']);
        Notification::assertNothingSent();
    }

    public function test_resident_pdf_access_is_denied_but_authorized_staff_can_download(): void
    {
        $certificate = $this->certificate();
        Storage::disk('local')->put($certificate->file_path, '%PDF-synthetic');
        $url = URL::temporarySignedRoute('certificates.download', now()->addMinutes(5), $certificate);
        $this->actingAs($this->owner->user, 'homeowner')->get($url)->assertForbidden();
        $this->get(route('certificates.residency-preview', str_repeat('a', 64)))->assertForbidden();
        auth('homeowner')->logout();
        $this->actingAs($this->admin, 'admin')->get($url)->assertOk();
        $mail = (new CertificateIssued($certificate->id))->toMail($this->owner->user);
        $this->assertStringContainsString('/homeowner/service-requests', $mail->actionUrl);
        $this->assertStringNotContainsString('/download', $mail->actionUrl);
    }

    public function test_advance_month_assessment_is_idempotent_preserves_rates_and_does_not_block_certificates(): void
    {
        $setting = DuesSetting::create(['name' => 'Monthly dues', 'amount' => '100.00', 'frequency' => 'Monthly', 'starts_on' => '2026-01-01', 'due_day' => 15, 'is_active' => true]);
        $data = ['homeowner_id' => $this->owner->id, 'dues_setting_id' => $setting->id, 'billing_year' => 2026, 'billing_month' => 12];
        $obligation = app(PrepareMonthlyAssessment::class)->handle($data, $this->admin);
        $setting->update(['amount' => '200.00']);
        $this->assertSame($obligation->id, app(PrepareMonthlyAssessment::class)->handle($data, $this->admin)->id);
        $this->assertSame('100.00', $obligation->fresh()->amount_due);
        $this->assertSame('eligible', app(CertificateDuesEligibility::class)->inspect($this->owner)['state']);
        $summary = app(AnnualDuesSummary::class)->forResident($this->owner, 2026);
        $this->assertFalse($summary['months'][0]['assessed']);
        $this->assertSame('100.00', $summary['totals']['balance']);
        $payment = app(RecordPayment::class)->handle(['dues_obligation_id' => $obligation->id, 'payer_homeowner_id' => $this->owner->id, 'amount_paid' => '40.00', 'payment_method' => 'Cash', 'payment_date' => '2026-06-10'], $this->admin);
        $this->assertSame('December 2026', $payment->covered_period);
        $this->assertSame('60.00', app(AnnualDuesSummary::class)->forResident($this->owner, 2026)['totals']['balance']);
        try {
            app(PrepareMonthlyAssessment::class)->handle([...$data, 'billing_year' => 2027], $this->admin);
            $this->fail('Future years must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('billing_year', $exception->errors());
        }
        $this->assertDatabaseCount('dues_obligations', 1);
    }

    private function certificate(): Certificate
    {
        return Certificate::create(['homeowner_id' => $this->owner->id, 'certificate_number' => 'SYNTHETIC-2026', 'type' => 'Certificate of Residency', 'purpose' => 'Synthetic test', 'status' => 'Issued', 'issued_at' => now(), 'issued_by' => $this->admin->id, 'file_path' => 'certificates/synthetic.pdf']);
    }

    public function test_portal_concerns_share_ownership_and_default_complaint_priority(): void
    {
        $this->actingAs($this->owner->user)->post(route('portal.concerns.store'), [
            'kind' => 'Complaint', 'category' => 'Security', 'description' => 'A synthetic security concern for testing.', 'priority' => 'Urgent',
        ])->assertRedirect();
        $this->assertSame('Medium', Complaint::sole()->priority);
        $this->get(route('portal.concerns.index'))->assertOk()->assertSee('Security');
        $this->post(route('portal.concerns.store'), ['kind' => 'Inquiry', 'subject' => 'Office inquiry', 'message' => 'Please confirm office hours.'])->assertRedirect();
        $this->assertSame($this->owner->user_id, ContactMessage::sole()->user_id);
    }

    public function test_collection_rollback_and_repeat_submission_do_not_change_issued_identity(): void
    {
        $certificate = $this->certificate();
        $before = Arr::only($certificate->fresh()->getAttributes(), ['certificate_number', 'issued_at', 'file_path', 'document_snapshot']);
        DB::beginTransaction();
        app(ManageCertificateCollection::class)->handle($certificate->id, 'ready', $this->admin);
        DB::rollBack();
        $this->assertNull($certificate->fresh()->ready_for_pickup_at);
        app(ManageCertificateCollection::class)->handle($certificate->id, 'ready', $this->admin);
        try {
            app(ManageCertificateCollection::class)->handle($certificate->id, 'ready', $this->admin);
            $this->fail('Duplicate readiness must require a refresh.');
        } catch (ValidationException) {
            $this->assertSame($before, Arr::only($certificate->fresh()->getAttributes(), array_keys($before)));
        }
        $this->expectException(AuthorizationException::class);
        app(ManageCertificateCollection::class)->handle($certificate->id, 'collect', $this->owner->user);
    }

    public function test_resident_annual_view_ignores_forged_homeowner_selection_and_staff_access_requires_permission(): void
    {
        $setting = DuesSetting::create(['name' => 'Annual view test', 'amount' => '100.00', 'frequency' => 'Monthly', 'starts_on' => '2026-01-01', 'is_active' => true]);
        $data = ['homeowner_id' => $this->owner->id, 'dues_setting_id' => $setting->id, 'billing_year' => 2026, 'billing_month' => 12];
        app(PrepareMonthlyAssessment::class)->handle($data, $this->admin);
        $this->actingAs($this->owner->user, 'homeowner');
        Filament::setCurrentPanel(Filament::getPanel('homeowner'));
        Livewire::test(AnnualDues::class)->set('homeownerId', $this->admin->id)->assertSee('PHP 100.00')->assertActionDoesNotExist('prepareBillingMonth');
        auth('homeowner')->logout();
        $staff = User::factory()->create(['account_status' => 'Active']);
        $staff->assignRole('hoa_staff');
        $this->actingAs($staff, 'staff')->get('/staff/annual-dues')->assertOk();
        $staff->roles()->first()->revokePermissionTo('manage_payments');
        $this->get('/staff/annual-dues')->assertForbidden();
    }
}
