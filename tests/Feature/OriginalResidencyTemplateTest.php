<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Certificates\Pages\ListCertificates;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Homeowner;
use App\Models\User;
use App\Services\CertificateTemplates;
use App\Services\OriginalCertificateRenderer;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Mockery;
use RuntimeException;
use Tests\Support\UsesUploadedResidency;
use Tests\TestCase;

final class OriginalResidencyTemplateTest extends TestCase
{
    use RefreshDatabase;
    use UsesUploadedResidency;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-20 10:00:00'));
        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();
        Storage::set('local', Storage::fake('original-residency-'.getmypid()));
        $this->admin = User::factory()->create();
        $this->admin->assignRole('hoa_admin');
    }

    private function draft(bool $approved = false): CertificateTemplate
    {
        $service = app(CertificateTemplates::class);
        $draft = $service->createOriginalResidencyDraft(true, $this->admin);
        if ($approved) {
            $service->configureSignatory(['name' => 'JOSEPHINE ALARZAR', 'title' => 'SHOAi PRESIDENT', 'confirmed' => true, 'note' => 'Synthetic approval for isolated tests only.'], $this->admin);
            $service->approve($draft->id, 'Synthetic visual and wording approval for test only.', true, $this->admin);
        }

        return $draft->refresh();
    }

    private function payload(string $purpose = 'Barangay clearance'): array
    {
        $resident = User::factory()->create(['first_name' => 'José', 'middle_name' => null, 'last_name' => 'Dela Cruz', 'suffix' => null]);
        $resident->assignRole('homeowner');
        $owner = $resident->homeowner()->create(['house_number' => '9', 'street' => 'Test Street', 'block' => '12', 'lot' => '34', 'phase' => 'Southville Phase I', 'residency_date' => '2020-01-01', 'ownership_type' => 'Owner', 'status' => 'Active']);

        return ['homeowner_id' => $owner->id, 'type' => 'Certificate of Residency', 'purpose' => $purpose];
    }

    public function test_registration_keeps_private_checksum_and_does_not_activate_or_provision_accounts(): void
    {
        $users = User::count();
        $template = $this->draft();
        $this->assertSame('Draft', $template->status);
        $this->assertSame(OriginalCertificateRenderer::ID, $template->renderer);
        $this->assertSame([['width' => 612, 'height' => 792]], $template->layout['pages']);
        $this->assertSame(2026, $template->layout['printed_year']);
        $this->assertCount(7, $template->layout['fields']);
        $this->assertContains('phase_suffix', array_column($template->layout['fields'], 'key'));
        $this->assertNotContains('phase', array_column($template->layout['fields'], 'key'));
        $this->assertSame($users, User::count());
        $this->assertNull(app(CertificateTemplates::class)->approved('Certificate of Residency'));
        $this->assertSame($template->layout['sha256'], hash('sha256', Storage::disk('local')->get($template->layout['background_path'])));
        $this->assertDatabaseCount('certificates', 0);
    }

    public function test_visual_review_is_required_and_staff_cannot_register(): void
    {
        try {
            app(CertificateTemplates::class)->createOriginalResidencyDraft(false, $this->admin);
            $this->fail('Visual review is required.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('reviewed', $exception->errors());
        }
        $staff = User::factory()->create();
        $staff->assignRole('hoa_staff');
        $this->expectException(AuthorizationException::class);
        app(CertificateTemplates::class)->createOriginalResidencyDraft(true, $staff);
    }

    public function test_approval_requires_the_exact_authorized_printed_signatory(): void
    {
        $draft = $this->draft();
        app(CertificateTemplates::class)->configureSignatory(['name' => 'Different Person', 'title' => 'President', 'confirmed' => true, 'note' => 'Synthetic confirmation for tests only.'], $this->admin);
        $this->expectException(ValidationException::class);
        app(CertificateTemplates::class)->approve($draft->id, 'Synthetic approval must not accept a different signatory.', true, $this->admin);
    }

    public function test_approved_original_issues_a_flattened_pdf_with_a_complete_snapshot(): void
    {
        $template = $this->draft(true);
        $certificate = $this->issueCertificate($this->payload(), $this->admin);
        $this->assertSame($template->id, $certificate->certificate_template_id);
        $this->assertSame('José Dela Cruz', $certificate->document_snapshot['resident_name']);
        $this->assertSame('12', $certificate->document_snapshot['address']['block']);
        $this->assertSame($template->layout, Arr::except($certificate->document_snapshot['layout'], ['font_sha256']));
        $pdf = Storage::disk('local')->get($certificate->file_path);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringNotContainsString('/AcroForm', $pdf);
        $this->assertStringNotContainsString('/JavaScript', $pdf);
        $this->assertSame(1, Certificate::count());
        $original = $pdf;
        $certificate->homeowner->user->update(['first_name' => 'Changed']);
        $this->assertSame('José Dela Cruz', $certificate->fresh()->document_snapshot['resident_name']);
        $this->assertSame($original, Storage::disk('local')->get($certificate->file_path));
    }

    public function test_long_purpose_is_a_field_error_and_rolls_back_issuance(): void
    {
        $this->draft(true);
        try {
            $this->issueCertificate($this->payload(str_repeat('W', 200)), $this->admin);
            $this->fail('Overflow must not be clipped.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('purpose', $exception->errors());
            $this->assertDatabaseCount('certificates', 0);
            $this->assertSame([], Storage::disk('local')->allFiles('certificates'));
        }
    }

    public function test_printed_year_cannot_be_silently_changed(): void
    {
        $this->draft(true);
        $this->travelTo(Carbon::parse('2027-01-01'));
        try {
            $this->issueCertificate($this->payload(), $this->admin);
            $this->fail('The 2026 template cannot issue a 2027 document.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('type', $exception->errors());
            $this->assertDatabaseCount('certificates', 0);
        }
    }

    public function test_corrupt_background_blocks_issuance_and_preserves_history(): void
    {
        $template = $this->draft(true);
        Storage::disk('local')->put($template->layout['background_path'], '%PDF-corrupted');
        $this->expectException(ValidationException::class);
        $this->issueCertificate($this->payload(), $this->admin);
    }

    public function test_approved_layout_cannot_be_mutated(): void
    {
        $template = $this->draft(true);
        $this->expectException(ValidationException::class);
        $template->forceFill(['layout' => []])->save();
    }

    public function test_private_reference_and_draft_previews_create_no_certificate(): void
    {
        $template = $this->draft();
        $this->actingAs($this->admin, 'admin')->get(route('certificates.residency-reference'))->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('Cache-Control', 'no-store, private');
        $this->get(route('certificates.source.preview', $template))->assertOk();
        $resident = User::factory()->create();
        $resident->assignRole('homeowner');
        $this->actingAs($resident, 'admin')->get(route('certificates.residency-reference'))->assertForbidden();
        $this->get(route('certificates.source.preview', $template))->assertForbidden();
        $this->assertDatabaseCount('certificates', 0);
        Notification::assertNothingSent();
    }

    public function test_storage_failure_cleans_partial_output_and_rolls_back(): void
    {
        $this->requireResidencyFont();
        $template = $this->draft(true);
        $payload = $this->payload();
        $disk = Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('get')->with($template->layout['background_path'])->andReturn(file_get_contents(resource_path('certificates/residency-2026/reference.pdf')));
        $disk->shouldReceive('put')->once()->andReturn(false);
        $disk->shouldReceive('delete')->once()->andReturn(true);
        Storage::shouldReceive('disk')->with('local')->andReturn($disk);
        try {
            $this->issueCertificate($payload, $this->admin);
            $this->fail('Storage failure must abort issuance.');
        } catch (RuntimeException) {
            $this->assertDatabaseCount('certificates', 0);
        }
    }

    public function test_original_draft_is_reachable_through_the_admin_action(): void
    {
        $this->requireResidencyFont();
        $this->actingAs($this->admin, 'admin');
        Filament::setCurrentPanel('admin');
        Livewire::test(ListCertificates::class)
            ->callAction('confirmResidencySource', ['reviewed' => true, 'confirmed' => true, 'note' => 'Synthetic HOA confirmation for this isolated test.'])
            ->assertHasNoActionErrors()
            ->assertNotified('Official Residency source confirmed');
        $this->assertSame(OriginalCertificateRenderer::ID, CertificateTemplate::sole()->renderer);
        $this->assertSame('Approved', CertificateTemplate::sole()->status);
    }

    public function test_incomplete_registered_address_is_not_silently_left_blank(): void
    {
        $this->draft(true);
        $payload = $this->payload();
        Homeowner::findOrFail($payload['homeowner_id'])->forceFill(['block' => ''])->save();
        try {
            $this->issueCertificate($payload, $this->admin);
            $this->fail('Missing registered block must be corrected before issuance.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('homeowner_id', $exception->errors());
            $this->assertDatabaseCount('certificates', 0);
        }
    }

    public function test_additive_migration_preserves_existing_text_versions(): void
    {
        $template = CertificateTemplate::create(['type' => 'Certificate of Residency', 'version' => 1, 'title' => 'Existing version', 'body' => 'Historical text {{resident_name}}', 'created_by' => $this->admin->id]);
        $migration = require database_path('migrations/2026_10_20_000004_add_original_certificate_layouts.php');
        $migration->down();
        $migration->up();
        $this->assertSame('text-v1', $template->fresh()->renderer);
        $this->assertNull($template->fresh()->layout);
        $this->assertSame('Historical text {{resident_name}}', $template->fresh()->body);
        $this->assertSame($template->version, $template->fresh()->version);
    }
}
