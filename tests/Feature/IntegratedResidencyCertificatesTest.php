<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Certificates\IssueCertificate;
use App\Filament\Resources\Certificates\Pages\CreateCertificate;
use App\Filament\Resources\Certificates\Pages\ListCertificates;
use App\Models\CertificateTemplate;
use App\Models\Homeowner;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\CertificateIssued;
use App\Services\CertificateTemplates;
use App\Services\ResidencyCertificatePreviews;
use App\Services\ResidencyFont;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\UsesSyntheticCohabitation;
use Tests\Support\UsesUploadedResidency;
use Tests\TestCase;

final class IntegratedResidencyCertificatesTest extends TestCase
{
    use RefreshDatabase;
    use UsesSyntheticCohabitation;
    use UsesUploadedResidency;

    private User $admin;

    private array $payload;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-20 10:00:00'));
        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();
        Storage::set('local', Storage::fake('integrated-certificates-'.getmypid()));
        $this->admin = User::factory()->create();
        $this->admin->assignRole('hoa_admin');
        $resident = User::factory()->create(['first_name' => 'José', 'middle_name' => null, 'last_name' => 'Dela Cruz', 'suffix' => null]);
        $resident->assignRole('homeowner');
        $owner = $resident->homeowner()->create(['house_number' => '9', 'street' => 'Test Street', 'block' => '1', 'lot' => '2', 'phase' => 'Southville Phase I', 'residency_date' => '2020-01-01', 'ownership_type' => 'Owner', 'status' => 'Active']);
        $this->payload = ['homeowner_id' => $owner->id, 'type' => 'Certificate of Residency', 'purpose' => 'Barangay clearance'];
    }

    private function source(): void
    {
        $this->requireResidencyFont();
        app(CertificateTemplates::class)->confirmResidencySource(['reviewed' => true, 'confirmed' => true, 'note' => 'Synthetic source and signatory confirmation for tests only.'], $this->admin);
    }

    private function preview(): string
    {
        $this->source();

        return app(ResidencyCertificatePreviews::class)->prepare($this->payload, $this->admin);
    }

    public function test_source_confirmation_is_idempotent_and_the_removed_page_returns_404(): void
    {
        $this->assertFalse(app(CertificateTemplates::class)->isResidencySourceConfirmed());
        $this->source();
        $this->source();
        $this->assertTrue(app(CertificateTemplates::class)->isResidencySourceConfirmed());
        $this->assertDatabaseCount('certificate_templates', 1);
        $this->assertSame('Approved', CertificateTemplate::sole()->status);
        $this->assertDatabaseCount('certificates', 0);
        $this->actingAs($this->admin, 'admin')->get('/admin/certificate-templates')->assertNotFound();
        $this->get('/admin/certificates')->assertOk()->assertDontSee('href="http://localhost/admin/certificate-templates"', false);
        Filament::setCurrentPanel('admin');
        Livewire::test(ListCertificates::class)->assertActionHidden('confirmResidencySource')->assertActionVisible('reviewResidencySource');
    }

    public function test_preview_creates_no_certificate_number_or_notification_and_is_private(): void
    {
        $token = $this->preview();
        $this->actingAs($this->admin, 'admin')->get(route('certificates.residency-preview', $token))->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseCount('certificates', 0);
        $this->assertDatabaseCount('certificate_sequences', 0);
        $this->assertDatabaseCount('certificate_previews', 1);
        Notification::assertNothingSent();
    }

    public function test_changed_signatory_requires_setup_again_without_silently_reapproving_the_source(): void
    {
        $this->source();
        $template = CertificateTemplate::sole();
        $approvedAt = $template->approved_at;
        SystemSetting::current()->forceFill(['certificate_signatory_name' => 'A Different Officer'])->save();
        $this->assertFalse(app(CertificateTemplates::class)->isResidencySourceConfirmed());
        $this->actingAs($this->admin, 'admin');
        Filament::setCurrentPanel('admin');
        Livewire::test(ListCertificates::class)->assertActionVisible('confirmResidencySource');
        $this->assertSame('Approved', $template->fresh()->status);
        $this->assertEquals($approvedAt, $template->fresh()->approved_at);
        $this->assertSame('A Different Officer', SystemSetting::current()->certificate_signatory_name);
        $this->expectException(ValidationException::class);
        app(CertificateTemplates::class)->assertIssuable('Certificate of Residency');
    }

    public function test_confirmation_is_idempotent_and_view_print_uses_the_same_stored_pdf(): void
    {
        $token = $this->preview();
        $data = [...$this->payload, 'preview_token' => $token];
        $first = app(IssueCertificate::class)->handle($data, $this->admin);
        $again = app(IssueCertificate::class)->handle($data, $this->admin);
        $this->assertSame($first->id, $again->id);
        $this->assertDatabaseCount('certificates', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles('certificates'));
        $this->assertSame(hash('sha256', $token), $first->issuance_key);
        Notification::assertSentToTimes($first->homeowner->user, CertificateIssued::class, 1);
        $this->actingAs($this->admin, 'admin');
        $response = $this->get(URL::temporarySignedRoute('certificates.download', now()->addMinutes(10), ['certificate' => $first->id, 'view' => '1']))->assertOk();
        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition'));
        $this->assertSame(Storage::disk('local')->get($first->file_path), $response->streamedContent());
        $this->get(URL::temporarySignedRoute('certificates.download', now()->addMinutes(10), $first))->assertDownload();
    }

    public function test_direct_issuance_cannot_bypass_preview_confirmation(): void
    {
        $this->source();
        $this->expectException(ValidationException::class);
        app(IssueCertificate::class)->handle($this->payload, $this->admin);
    }

    public function test_changed_input_and_another_actor_cannot_reuse_preview(): void
    {
        $token = $this->preview();
        try {
            app(IssueCertificate::class)->handle([...$this->payload, 'purpose' => 'Different purpose', 'preview_token' => $token], $this->admin);
            $this->fail('Changed purpose requires a fresh preview.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('preview_token', $exception->errors());
        }
        $staff = User::factory()->create();
        $staff->assignRole('hoa_staff');
        $this->actingAs($staff, 'staff')->get(route('certificates.residency-preview', $token))->assertStatus(410);
        $this->expectException(ValidationException::class);
        app(IssueCertificate::class)->handle([...$this->payload, 'preview_token' => $token], $staff);
    }

    public function test_expired_preview_cannot_issue(): void
    {
        $token = $this->preview();
        $this->travel(16)->minutes();
        $this->expectException(ValidationException::class);
        app(IssueCertificate::class)->handle([...$this->payload, 'preview_token' => $token], $this->admin);
    }

    public function test_registered_data_changes_invalidate_the_preview(): void
    {
        $token = $this->preview();
        Homeowner::findOrFail($this->payload['homeowner_id'])->user->update(['first_name' => 'Changed']);
        $this->actingAs($this->admin, 'admin')->get(route('certificates.residency-preview', $token))->assertStatus(410);
        $this->expectException(ValidationException::class);
        app(IssueCertificate::class)->handle([...$this->payload, 'preview_token' => $token], $this->admin);
    }

    public function test_permission_revocation_is_rechecked_at_confirmation(): void
    {
        $token = $this->preview();
        $this->admin->forceFill(['account_status' => 'Inactive'])->save();
        $this->expectException(AuthorizationException::class);
        app(IssueCertificate::class)->handle([...$this->payload, 'preview_token' => $token], $this->admin);
    }

    public function test_html_residency_versions_cannot_be_used_as_a_fallback(): void
    {
        $version = CertificateTemplate::create(['type' => 'Certificate of Residency', 'version' => 1, 'title' => 'Old design', 'body' => 'Old design', 'created_by' => $this->admin->id]);
        $version->forceFill(['status' => 'Approved'])->save();
        $this->expectException(ValidationException::class);
        app(ResidencyCertificatePreviews::class)->prepare($this->payload, $this->admin);
    }

    public function test_missing_font_or_pending_migration_returns_a_readable_error(): void
    {
        config(['certificates.residency_font' => null]);
        try {
            app(ResidencyFont::class)->register();
            $this->fail('No font substitution is allowed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('type', $exception->errors());
        }
        $migration = require database_path('migrations/2026_10_20_000005_add_certificate_previews.php');
        $migration->down();
        try {
            app(CertificateTemplates::class)->assertSchema(true);
            $this->fail('Pending setup must be reported as validation.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('pending certificate migrations', $exception->getMessage());
        } finally {
            $migration->up();
        }
    }

    public function test_admin_form_requires_preview_then_confirms_issuance(): void
    {
        $this->source();
        $this->actingAs($this->admin, 'admin');
        Filament::setCurrentPanel('admin');
        Livewire::test(CreateCertificate::class)->fillForm($this->payload)
            ->call('preparePreview')->assertHasNoFormErrors()
            ->assertSet('previewToken', fn ($token) => is_string($token) && strlen($token) === 64)
            ->call('create')->assertHasNoFormErrors();
        $this->assertDatabaseCount('certificates', 1);
    }

    public function test_unsupported_glyphs_and_overflow_are_rejected_without_font_substitution_or_shrinking(): void
    {
        $this->source();
        foreach ([str_repeat('W', 80), "Purpose \u{1F600}", "Purpose\nSecond line"] as $purpose) {
            try {
                app(ResidencyCertificatePreviews::class)->prepare([...$this->payload, 'purpose' => $purpose], $this->admin);
                $this->fail('The original font/blank must not be changed to accommodate the value.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('purpose', $exception->errors());
            }
        }
        $this->assertDatabaseCount('certificate_previews', 0);
    }

    public function test_existing_cohabitation_approval_remains_inside_the_certificate_resource(): void
    {
        $this->syntheticCohabitationSource();
        $this->actingAs($this->admin, 'admin');
        Filament::setCurrentPanel('admin');
        Livewire::test(ListCertificates::class)
            ->callAction('setupCohabitationSource', ['reviewed' => true, 'confirmed' => true, 'note' => 'Synthetic evidence for this original Cohabitation source.'])
            ->assertHasNoActionErrors();
        $this->assertSame('Approved', CertificateTemplate::where('type', 'Certificate of Cohabitation')->sole()->status);
        $this->assertDatabaseCount('certificates', 0);
    }

    public function test_staff_cannot_confirm_the_official_source(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole('hoa_staff');
        $this->expectException(AuthorizationException::class);
        app(CertificateTemplates::class)->confirmResidencySource(['reviewed' => true, 'confirmed' => true, 'note' => 'Forged source confirmation must not be accepted.'], $staff);
    }
}
