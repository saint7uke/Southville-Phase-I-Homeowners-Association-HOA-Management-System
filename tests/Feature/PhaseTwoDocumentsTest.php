<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Certificates\IssueCertificate;
use App\Actions\ServiceRequests\CreateServiceRequest;
use App\Actions\ServiceRequests\UpdateServiceRequest;
use App\Actions\Users\InviteManagedUser;
use App\Filament\Resources\Certificates\Pages\CreateCertificate;
use App\Filament\Resources\Certificates\Pages\EditCertificate;
use App\Models\Certificate;
use App\Models\Complaint;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Notifications\CertificateIssued;
use App\Services\CertificateTemplates;
use App\Services\CertificateTypeCatalog;
use App\Services\CohabitationCertificateSource;
use App\Services\ResidencyCertificatePreviews;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\UsesSyntheticCohabitation;
use Tests\TestCase;

final class PhaseTwoDocumentsTest extends TestCase
{
    use RefreshDatabase;
    use UsesSyntheticCohabitation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
        Notification::fake();
        $this->travelTo(now()->setDate(2026, 10, 21));
    }

    private function people(): array
    {
        $admin = User::factory()->create();
        $admin->assignRole('hoa_admin');
        $resident = User::factory()->create();
        $resident->assignRole('homeowner');
        $owner = $resident->homeowner()->create(['house_number' => '9', 'street' => 'Test Street', 'block' => '1', 'lot' => '2', 'phase' => 'Southville Phase I', 'residency_date' => '2020-01-01', 'ownership_type' => 'Owner', 'status' => 'Active']);

        return [$admin, $resident, $owner];
    }

    public function test_only_two_new_types_and_cohabitation_requires_structured_names(): void
    {
        [, $resident, $owner] = $this->people();
        $this->assertEqualsCanonicalizing(CertificateTypeCatalog::ALLOWED, app(CertificateTypeCatalog::class)->requestTypes());
        foreach (['Repair/Maintenance', 'Certificate of Good Standing', 'Certificate of Cohabitation'] as $type) {
            try {
                app(CreateServiceRequest::class)->handle($owner, ['request_type' => $type, 'subject' => 'Request', 'details' => 'Request details'], [], $resident);
                $this->fail('Retired types and missing cohabitation names must fail.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('service_requests', 0);
            }
        }
        $request = app(CreateServiceRequest::class)->handle($owner, ['request_type' => 'Certificate of Cohabitation', 'subject' => 'Request', 'details' => 'Request details', 'cohabitant_one' => 'Juan Dela Cruz', 'cohabitant_two' => 'Maria Dela Cruz'], [], $resident);
        $this->assertSame('Test Street', $request->address_snapshot['street']);
        $this->assertFalse($request->grandfathered);
    }

    public function test_cohabitation_issuance_requires_approval_then_preserves_document_snapshot(): void
    {
        [$admin, , $owner] = $this->people();
        $service = app(CertificateTemplates::class);
        $service->configureSignatory(['name' => 'Authorized President', 'title' => 'President', 'note' => 'Confirmed by HOA for this test.', 'confirmed' => true], $admin);
        $owner->update(['phase_suffix' => 'A']);
        $this->syntheticCohabitationSource();
        $payload = ['homeowner_id' => $owner->id, 'type' => 'Certificate of Cohabitation', 'purpose' => 'Requested purpose',
            'cohabitant_one' => $owner->user->full_name, 'cohabitant_two' => 'Maria Dela Cruz', 'cohabitant_one_birth_date' => $owner->user->date_of_birth->toDateString(),
            'cohabitant_two_birth_date' => '1991-01-01', 'years_together' => 5, 'address_continuation' => 'Verified test detail', 'requester' => 'one'];
        $payload['preview_token'] = app(ResidencyCertificatePreviews::class)->prepare($payload, $admin);
        try {
            app(IssueCertificate::class)->handle($payload, $admin);
            $this->fail('Unapproved draft cannot be issued.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('certificates', 0);
        }
        $template = app(CohabitationCertificateSource::class)->confirm(['reviewed' => true, 'confirmed' => true, 'note' => 'Synthetic source confirmation only.'], $admin);
        $certificate = app(IssueCertificate::class)->handle($payload, $admin);
        $this->assertSame($template->id, $certificate->certificate_template_id);
        $this->assertSame('SYNTHETIC COHABITATION OFFICER', $certificate->document_snapshot['signatory_name']);
        $this->assertSame($payload['cohabitant_one'], $certificate->document_snapshot['cohabitation']['cohabitant_one']);
        $owner->update(['street' => 'New Street']);
        $this->assertSame('Test Street', $certificate->fresh()->document_snapshot['address']['street']);
        Storage::disk('local')->assertExists($certificate->file_path);
        $this->expectException(ValidationException::class);
        $template->refresh()->update(['body' => 'Changed text']);
    }

    public function test_staff_cannot_approve_templates_and_multirole_admin_cannot_submit_complaints(): void
    {
        [$admin, , $owner] = $this->people();
        $admin->assignRole('homeowner');
        $this->assertFalse($admin->can('create', Complaint::class));
        $staff = User::factory()->create();
        $staff->assignRole('hoa_staff');
        $this->expectException(AuthorizationException::class);
        app(CertificateTemplates::class)->createDraft(['type' => 'Certificate of Residency', 'title' => 'DRAFT', 'body' => 'Draft text'], $staff);
    }

    public function test_grandfathered_retired_request_can_finish_but_new_direct_issuance_cannot(): void
    {
        [$admin, , $owner] = $this->people();
        app(CertificateTemplates::class)->configureSignatory(['name' => 'Authorized President', 'title' => 'President', 'note' => 'Confirmed by HOA for this test.', 'confirmed' => true], $admin);
        $request = ServiceRequest::query()->create(['homeowner_id' => $owner->id, 'request_type' => 'Certificate of Good Standing', 'subject' => 'Legacy', 'details' => 'Legacy request', 'status' => 'Approved']);
        DB::table('service_requests')->where('id', $request->id)->update(['grandfathered' => true]);
        $certificate = app(IssueCertificate::class)->fromApprovedRequest($request->fresh(), $admin);
        $this->assertSame('Certificate of Good Standing', $certificate->type);
        $this->assertSame('Completed', $request->fresh()->status);
        $this->expectException(ValidationException::class);
        app(IssueCertificate::class)->handle(['homeowner_id' => $owner->id, 'type' => 'Certificate of Good Standing'], $admin);
    }

    public function test_invitation_has_no_shared_password_and_leaves_account_pending(): void
    {
        [$admin] = $this->people();
        $user = app(InviteManagedUser::class)->handle(['first_name' => 'Invited', 'last_name' => 'Officer', 'sex' => 'Female', 'date_of_birth' => '1990-01-01', 'contact_number' => '09123456789', 'email' => ' INVITED@EXAMPLE.TEST ', 'role' => 'hoa_staff'], $admin);
        $this->assertSame('Pending', $user->account_status);
        $this->assertSame('invited@example.test', $user->email);
        $this->assertTrue($user->hasRole('hoa_staff'));
        $this->assertFalse(Hash::check('Southville2026!', $user->password));
        $this->expectException(ValidationException::class);
        app(InviteManagedUser::class)->handle(['email' => $user->email], $admin);
    }

    public function test_private_drafts_do_not_allocate_numbers_or_notify_issuance(): void
    {
        [$admin, $resident, $owner] = $this->people();
        $request = app(CreateServiceRequest::class)->handle($owner, ['request_type' => 'Certificate of Cohabitation', 'subject' => 'Draft request', 'details' => 'Review the cohabitation wording.', 'cohabitant_one' => 'Juan Dela Cruz', 'cohabitant_two' => 'Maria Dela Cruz'], [], $resident);
        $stranger = User::factory()->create();
        $stranger->assignRole('homeowner');
        $this->actingAs($stranger, 'homeowner')->get(route('service-requests.draft', $request))->assertForbidden();
        $this->actingAs($resident, 'homeowner')->get(route('service-requests.draft', $request))->assertOk()
            ->assertViewIs('certificates.request-guidance')->assertSee('Your Cohabitation request is awaiting HOA review.');
        $resident->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($resident, 'homeowner')->get(route('service-requests.draft', $request))->assertForbidden();
        $this->assertDatabaseCount('certificates', 0);
        Notification::assertNotSentTo($resident, CertificateIssued::class);
    }

    public function test_processing_approval_metadata_and_eligibility_are_server_controlled(): void
    {
        [$admin, $resident, $owner] = $this->people();
        $request = app(CreateServiceRequest::class)->handle($owner, ['request_type' => 'Certificate of Residency', 'subject' => 'Review', 'details' => 'Review this residency request.'], [], $resident);
        $data = ['subject' => $request->subject, 'details' => $request->details, 'status' => 'Processing'];
        $request = app(UpdateServiceRequest::class)->handle($request, $data, $admin);
        $this->assertSame($admin->id, $request->processed_by);
        $this->assertNotNull($request->processed_at);
        $request = app(UpdateServiceRequest::class)->handle($request, [...$data, 'status' => 'Approved'], $admin);
        $this->assertSame($admin->id, $request->approved_by);
        $this->assertNotNull($request->approved_at);
        $this->expectException(ValidationException::class);
        app(UpdateServiceRequest::class)->handle($request, [...$data, 'status' => 'Approved', 'grandfathered' => true], $admin);
    }

    public function test_migration_marks_only_existing_retired_requests_as_grandfathered(): void
    {
        [, , $owner] = $this->people();
        $originalLayouts = require database_path('migrations/2026_10_20_000004_add_original_certificate_layouts.php');
        $originalLayouts->down();
        $migration = require database_path('migrations/2026_10_20_000001_add_certificate_workflow_versions.php');
        $migration->down();
        foreach (['Repair/Maintenance', 'Certificate of Residency'] as $type) {
            DB::table('service_requests')->insert(['homeowner_id' => $owner->id, 'ticket_number' => 'LEGACY-'.substr(md5($type), 0, 24), 'request_type' => $type, 'subject' => 'Existing request', 'details' => 'Preserved historical request', 'status' => 'Pending', 'created_at' => now(), 'updated_at' => now()]);
        }
        $migration->up();
        $originalLayouts->up();
        $this->assertTrue(ServiceRequest::where('request_type', 'Repair/Maintenance')->first()->grandfathered);
        $this->assertFalse(ServiceRequest::where('request_type', 'Certificate of Residency')->first()->grandfathered);
    }

    public function test_certificate_form_exposes_the_signatory_gate_as_a_field_error(): void
    {
        [$admin, , $owner] = $this->people();
        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel('admin');
        Livewire::test(CreateCertificate::class)
            ->fillForm(['homeowner_id' => $owner->id, 'type' => 'Certificate of Residency', 'purpose' => 'Request'])
            ->call('preparePreview')->assertHasFormErrors(['type']);
        $this->assertDatabaseCount('certificates', 0);
    }

    public function test_invalid_template_placeholders_are_visible_form_errors(): void
    {
        [$admin] = $this->people();
        try {
            app(CertificateTemplates::class)->createDraft(['type' => 'Certificate of Cohabitation', 'title' => 'Draft', 'body' => '{{unknown_placeholder}}'], $admin);
            $this->fail('Unknown placeholders must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('type', $exception->errors());
        }
        $this->assertDatabaseCount('certificate_templates', 0);
    }

    public function test_issued_identity_and_forged_edit_submission_are_rejected(): void
    {
        [$admin, , $owner] = $this->people();
        $certificate = Certificate::query()->create(['homeowner_id' => $owner->id, 'certificate_number' => 'CERT-2026-9999', 'type' => 'Certificate of Residency', 'file_path' => 'certificates/unchanged.pdf', 'status' => 'Issued', 'issued_at' => now(), 'expires_at' => now()->endOfYear(), 'issued_by' => $admin->id]);
        foreach ([['certificate_number' => 'CERT-2026-0000'], ['file_path' => 'different.pdf']] as $forged) {
            try {
                $certificate->fresh()->update($forged);
                $this->fail('Issued identity must be immutable.');
            } catch (ValidationException) {
                $this->assertSame('CERT-2026-9999', $certificate->fresh()->certificate_number);
            }
        }
        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel('admin');
        Livewire::test(EditCertificate::class, ['record' => $certificate->id])->call('save')->assertHasFormErrors(['type']);
        $this->assertSame('certificates/unchanged.pdf', $certificate->fresh()->file_path);
    }
}
