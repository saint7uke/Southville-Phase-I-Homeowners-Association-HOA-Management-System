<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Certificates\IssueCertificate;
use App\Filament\Resources\Certificates\Pages\CreateCertificate;
use App\Models\CertificateTemplate;
use App\Models\Homeowner;
use App\Models\User;
use App\Services\CohabitationCertificateData;
use App\Services\CohabitationCertificateSource;
use App\Services\OriginalCertificateRenderer;
use App\Services\ResidencyCertificatePreviews;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;
use ZipArchive;

final class CohabitationPhaseCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Homeowner $owner;

    private array $data;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-21 10:00:00'));
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
        Notification::fake();
        $this->admin = User::factory()->create();
        $this->admin->assignRole('hoa_admin');
        $resident = User::factory()->create(['first_name' => 'José', 'middle_name' => null, 'last_name' => 'Dela Cruz', 'date_of_birth' => '1990-01-02']);
        $resident->assignRole('homeowner');
        $this->owner = $resident->homeowner()->create(['street' => 'Test Street', 'block' => '1', 'lot' => '2', 'phase' => 'Southville Phase I', 'phase_suffix' => 'B',
            'residency_date' => '2020-01-01', 'ownership_type' => 'Owner', 'status' => 'Active']);
        $this->data = ['homeowner_id' => $this->owner->id, 'type' => CohabitationCertificateData::TYPE, 'cohabitant_one' => $resident->full_name,
            'cohabitant_one_birth_date' => '1990-01-02', 'cohabitant_two' => 'María Santos', 'cohabitant_two_birth_date' => '1991-02-03',
            'years_together' => 5, 'requester' => 'one'];
    }

    private function requireFont(): void
    {
        if (! is_file((string) config('certificates.cohabitation_font'))) {
            $this->markTestSkipped('The original Arial font is required; no substitution is permitted.');
        }
    }

    public function test_the_corrected_copy_changes_only_the_authorized_phase_and_signatory_punctuation(): void
    {
        $directory = resource_path('certificates/cohabitation-2026');
        $this->assertSame(CohabitationCertificateSource::UNADJUSTED_SOURCE_SHA256, hash_file('sha256', $directory.'/original-unadjusted.docx'));
        $original = new ZipArchive;
        $corrected = new ZipArchive;
        $this->assertTrue($original->open($directory.'/original-unadjusted.docx'));
        $this->assertTrue($corrected->open($directory.'/original.docx'));
        try {
            $this->assertSame($original->numFiles, $corrected->numFiles);
            for ($index = 0; $index < $original->numFiles; $index++) {
                $name = $original->getNameIndex($index);
                $expected = $original->getFromIndex($index);
                if ($name === 'word/document.xml') {
                    $this->assertSame(1, substr_count($expected, 'Southville 2 Phase 1-A </w:t>'));
                    $expected = str_replace('Southville 2 Phase 1-A </w:t>', 'Southville 2 Phase 1 - </w:t>', $expected);
                    $this->assertSame(1, substr_count($expected, '<w:t>D,</w:t>'));
                    $expected = str_replace('<w:t>D,</w:t>', '<w:t>D.</w:t>', $expected);
                }
                $this->assertSame($expected, $corrected->getFromName($name), $name);
            }
        } finally {
            $original->close();
            $corrected->close();
        }
        $layout = app(CohabitationCertificateSource::class)->manifest();
        $this->assertSame(hash_file('sha256', $directory.'/original.docx'), $layout['source_sha256']);
        $this->assertSame(hash_file('sha256', $directory.'/reference.pdf'), $layout['sha256']);
        $this->assertCount(2, array_filter($layout['fields'], fn ($field) => $field['key'] === 'phase_suffix'));
        $this->assertNotContains('address_continuation', array_column($layout['fields'], 'key'));
        $this->assertSame(2026, $layout['printed_year']);
        $this->assertSame('JOSEPHINE D. ALARZAR', $layout['printed_signatory_name']);
        $this->assertDatabaseCount('certificate_templates', 0);
    }

    public function test_registered_phase_is_authoritative_and_obsolete_browser_values_are_not_stored(): void
    {
        $validated = app(CohabitationCertificateData::class)->validate([...$this->data, 'phase_suffix' => 'FORGED', 'address_continuation' => 'Obsolete input'], $this->owner);
        $this->assertSame('B', $validated['address']['phase_suffix']);
        $this->assertArrayNotHasKey('phase_suffix', $validated);
        $this->assertArrayNotHasKey('address_continuation', $validated);
        $values = app(CohabitationCertificateData::class)->values(['cohabitation' => $validated], now());
        $this->assertSame('B', $values['phase_suffix']);
        $this->assertArrayNotHasKey('address_continuation', $values);
        $this->assertSame('B', $this->owner->fresh()->phase_suffix);
    }

    public function test_missing_registered_phase_blocks_generation_without_modifying_the_account(): void
    {
        $this->owner->phase_suffix = null;
        try {
            app(CohabitationCertificateData::class)->validate($this->data, $this->owner);
            $this->fail('A missing phase must not be guessed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('homeowner_id', $exception->errors());
            $this->assertSame('B', $this->owner->fresh()->phase_suffix);
            $this->assertDatabaseCount('certificates', 0);
        }
    }

    public function test_the_corrected_source_is_reviewable_but_never_automatically_confirmed(): void
    {
        $this->requireFont();
        $this->assertSame('ready', app(CohabitationCertificateSource::class)->setupStatus()['state']);
        $this->actingAs($this->admin, 'admin')->get(route('certificates.cohabitation-reference'))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseCount('certificate_templates', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $token = app(ResidencyCertificatePreviews::class)->prepare($this->data, $this->admin);
        try {
            app(IssueCertificate::class)->handle([...$this->data, 'preview_token' => $token], $this->admin);
            $this->fail('Correcting the document is not signatory approval.');
        } catch (ValidationException) {
            $this->assertSame('Draft', CertificateTemplate::sole()->status);
            $this->assertDatabaseCount('certificates', 0);
        }
    }

    public function test_real_source_issuance_snapshots_the_registered_phase_without_the_obsolete_address_field(): void
    {
        $this->requireFont();
        app(CohabitationCertificateSource::class)->confirm(['reviewed' => true, 'confirmed' => true, 'note' => 'ISOLATED TEST EVIDENCE ONLY; not real HOA approval.'], $this->admin);
        $token = app(ResidencyCertificatePreviews::class)->prepare($this->data, $this->admin);
        $certificate = app(IssueCertificate::class)->handle([...$this->data, 'preview_token' => $token], $this->admin);
        $this->assertSame('B', $certificate->document_snapshot['cohabitation']['address']['phase_suffix']);
        $this->assertArrayNotHasKey('address_continuation', $certificate->document_snapshot['cohabitation']);
        $this->assertSame(app(CohabitationCertificateSource::class)->manifest()['sha256'], $certificate->document_snapshot['layout']['sha256']);
        $this->assertSame('JOSEPHINE D. ALARZAR', $certificate->document_snapshot['signatory_name']);
        $this->assertTrue(Storage::disk('local')->exists($certificate->file_path));
        $this->assertDatabaseCount('certificates', 1);
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('dues_obligations', 0);
    }

    public function test_the_signatory_correction_requires_fresh_confirmation_without_changing_the_previous_pdf(): void
    {
        $this->requireFont();
        $currentDirectory = config('certificates.cohabitation_source_directory');
        config(['certificates.cohabitation_source_directory' => $currentDirectory.'/history/phase-only-20261021']);
        $source = app(CohabitationCertificateSource::class);
        $oldTemplate = $source->confirm(['reviewed' => true, 'confirmed' => true, 'note' => 'ISOLATED TEST ONLY: approval of the archived phase-only version.'], $this->admin);
        $oldAttributes = $oldTemplate->getAttributes();
        $previews = app(ResidencyCertificatePreviews::class);
        $token = $previews->prepare($this->data, $this->admin);
        $oldCertificate = app(IssueCertificate::class)->handle([...$this->data, 'preview_token' => $token], $this->admin);
        $oldPdf = Storage::disk('local')->get($oldCertificate->file_path);
        $staleToken = $previews->prepare($this->data, $this->admin);

        config(['certificates.cohabitation_source_directory' => $currentDirectory]);
        $this->assertSame('ready', $source->setupStatus()['state']);
        try {
            app(IssueCertificate::class)->handle([...$this->data, 'preview_token' => $staleToken], $this->admin);
            $this->fail('An old preview/source approval must not authorize the changed signatory document.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('certificates', 1);
        }
        $this->assertSame($oldAttributes, $oldTemplate->fresh()->getAttributes());
        $this->assertSame('JOSEPHINE D, ALARZAR', $oldCertificate->document_snapshot['signatory_name']);
        $this->assertSame($oldPdf, Storage::disk('local')->get($oldCertificate->file_path));

        $newTemplate = $source->confirm(['reviewed' => true, 'confirmed' => true, 'note' => 'ISOLATED TEST ONLY: approval of the corrected signatory punctuation.'], $this->admin);
        $this->assertSame($oldTemplate->version + 1, $newTemplate->version);
        $this->assertSame('JOSEPHINE D. ALARZAR', $newTemplate->layout['printed_signatory_name']);
        $this->assertSame($oldAttributes, $oldTemplate->fresh()->getAttributes());
        $this->assertSame($oldPdf, Storage::disk('local')->get($oldCertificate->file_path));
        $this->assertDatabaseCount('payments', 0);
        $this->assertDatabaseCount('dues_obligations', 0);
    }

    public function test_the_form_shows_the_registered_phase_and_no_additional_address_input(): void
    {
        $this->actingAs($this->admin, 'admin');
        Filament::setCurrentPanel('admin');
        Livewire::test(CreateCertificate::class)->set('data.type', CohabitationCertificateData::TYPE)->set('data.homeowner_id', $this->owner->id)
            ->assertSee('Phase 1 - B')->assertDontSee('Additional address detail');
        $this->assertDatabaseCount('certificates', 0);
    }

    public function test_original_font_overflow_remains_a_field_error_and_historical_values_remain_readable(): void
    {
        $this->requireFont();
        try {
            app(ResidencyCertificatePreviews::class)->prepare([...$this->data, 'cohabitant_two' => str_repeat('W', 150)], $this->admin);
            $this->fail('Long text must not shrink or truncate the original blank.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('cohabitant_two', $exception->errors());
            $this->assertDatabaseCount('certificates', 0);
        }
        $validated = app(CohabitationCertificateData::class)->validate($this->data, $this->owner);
        $values = app(OriginalCertificateRenderer::class)->values(['layout' => ['font_profile' => 'cohabitation'], 'cohabitation' => [...$validated, 'address_continuation' => 'Historical snapshot text']], now());
        $this->assertSame('Historical snapshot text', $values['address_continuation']);
    }
}
