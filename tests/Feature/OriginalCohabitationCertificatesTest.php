<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Certificates\IssueCertificate;
use App\Filament\Resources\Certificates\Pages\CreateCertificate;
use App\Models\Certificate;
use App\Models\CertificatePreview;
use App\Models\CertificateTemplate;
use App\Models\Homeowner;
use App\Models\ServiceRequest;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\CohabitationCertificateData;
use App\Services\CohabitationCertificateSource;
use App\Services\ResidencyCertificatePreviews;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Support\UsesSyntheticCohabitation;
use Tests\TestCase;

final class OriginalCohabitationCertificatesTest extends TestCase
{
    use RefreshDatabase, UsesSyntheticCohabitation;

    private User $admin;

    private Homeowner $owner;

    private array $data;

    private ?string $token = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 21));
        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();
        Storage::set('local', Storage::fake('cohabitation-'.getmypid()));
        config(['certificates.cohabitation_source_directory' => Storage::disk('local')->path('missing-cohabitation-source')]);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('hoa_admin');
        $resident = User::factory()->create(['first_name' => 'José', 'middle_name' => null, 'last_name' => 'Dela Cruz', 'date_of_birth' => '1990-01-02']);
        $resident->assignRole('homeowner');
        $this->owner = $resident->homeowner()->create(['street' => 'Test Street', 'block' => '1', 'lot' => '2', 'phase' => 'Southville Phase I', 'phase_suffix' => 'B', 'residency_date' => '2020-01-01', 'ownership_type' => 'Owner', 'status' => 'Active']);
        $this->data = ['homeowner_id' => $this->owner->id, 'type' => CohabitationCertificateData::TYPE,
            'cohabitant_one' => $resident->full_name, 'cohabitant_one_birth_date' => '1990-01-02',
            'cohabitant_two' => 'María Santos', 'cohabitant_two_birth_date' => '1991-02-03',
            'cohabitant_two_homeowner_id' => null, 'years_together' => 5, 'requester' => 'two'];
    }

    private function confirm(): void
    {
        app(CohabitationCertificateSource::class)->confirm(['reviewed' => true, 'confirmed' => true, 'note' => 'Synthetic test confirmation ONLY; not HOA approval.'], $this->admin);
    }

    private function issue(): Certificate
    {
        $this->syntheticCohabitationSource();
        $this->confirm();
        $token = $this->token = app(ResidencyCertificatePreviews::class)->prepare($this->data, $this->admin);

        return app(IssueCertificate::class)->handle([...$this->data, 'preview_token' => $token], $this->admin);
    }

    public function test_real_upload_is_preserved_and_missing_corrected_source_never_uses_html(): void
    {
        $this->assertSame(CohabitationCertificateSource::UNADJUSTED_SOURCE_SHA256, hash_file('sha256', resource_path('certificates/cohabitation-2026/original-unadjusted.docx')));
        $this->actingAs($this->admin, 'admin')->get(route('certificates.cohabitation-reference'))->assertStatus(409)
            ->assertViewIs('errors.certificate-source-unavailable')->assertSee('Awaiting corrected document')
            ->assertSee('Back to Certificates')->assertSeeHtml('/admin/certificates')
            ->assertDontSee('Stack Trace')->assertHeader('Cache-Control', 'no-store, private');
        foreach (['prepare', 'issue'] as $attempt) {
            try {
                $attempt === 'prepare' ? app(ResidencyCertificatePreviews::class)->prepare($this->data, $this->admin)
                    : app(IssueCertificate::class)->handle($this->data, $this->admin);
                $this->fail('A corrected original and confirmed preview are required.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('certificates', 0);
                $this->assertDatabaseCount('certificate_previews', 0);
            }
        }
    }

    public function test_preview_is_watermarked_private_and_cannot_issue_until_source_confirmation(): void
    {
        $this->syntheticCohabitationSource();
        $token = app(ResidencyCertificatePreviews::class)->prepare($this->data, $this->admin);
        $this->login($this->admin, 'admin');
        $this->get(route('certificates.residency-preview', $token))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $other = User::factory()->create();
        $other->assignRole('hoa_staff');
        $this->login($other, 'staff');
        $this->get(route('certificates.residency-preview', $token))->assertStatus(410);
        try {
            app(IssueCertificate::class)->handle([...$this->data, 'preview_token' => $token], $this->admin);
            $this->fail('Drafts cannot be issued.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('certificates', 0);
        }
        $this->confirm();
        $certificate = app(IssueCertificate::class)->handle([...$this->data, 'preview_token' => $token], $this->admin);
        $this->assertNotNull($certificate->file_path);
    }

    public function test_issuance_is_idempotent_with_immutable_snapshot_and_same_saved_print_download(): void
    {
        $certificate = $this->issue();
        $again = app(IssueCertificate::class)->handle([...$this->data, 'preview_token' => $this->token], $this->admin);
        $this->assertSame($certificate->id, $again->id);
        $this->assertDatabaseCount('certificates', 1);
        $preview = CertificatePreview::sole();
        $snapshot = $certificate->fresh()->document_snapshot;
        $this->assertSame('María Santos', $snapshot['cohabitation']['cohabitant_two']);
        $this->assertSame('B', $snapshot['cohabitation']['address']['phase_suffix']);
        $this->assertSame('SYNTHETIC COHABITATION OFFICER', $snapshot['signatory_name']);
        $this->assertNotNull($snapshot['signatory_confirmation']);
        $this->assertSame($certificate->id, $preview->certificate_id);
        $this->actingAs($this->admin, 'admin');
        $url = URL::signedRoute('certificates.download', $certificate);
        $inline = URL::signedRoute('certificates.download', ['certificate' => $certificate, 'view' => '1']);
        $this->assertSame($this->get($url)->assertOk()->streamedContent(), $this->get($inline)->assertOk()->assertHeader('Content-Disposition', 'inline; filename=hoa-certificate-'.$certificate->certificate_number.'.pdf')->streamedContent());
        config(['certificates.cohabitation_font' => 'missing.ttf', 'certificates.cohabitation_source_directory' => 'missing-source']);
        $this->get($url)->assertOk();
        $this->owner->user->update(['last_name' => 'Changed']);
        $this->assertSame($snapshot, $certificate->fresh()->document_snapshot);
        $stranger = User::factory()->create();
        $stranger->assignRole('homeowner');
        $this->login($stranger, 'homeowner');
        $this->get($url)->assertNotFound();
    }

    public function test_confirmation_does_not_overwrite_residency_signatory_settings_and_versions_are_immutable(): void
    {
        $this->syntheticCohabitationSource();
        $settings = SystemSetting::current();
        $before = $settings->getAttributes();
        $this->confirm();
        $this->confirm();
        $this->assertSame($before, $settings->fresh()->getAttributes());
        $this->assertDatabaseCount('certificate_templates', 1);
        $this->expectException(ValidationException::class);
        CertificateTemplate::sole()->update(['body' => 'Unauthorized change']);
    }

    public function test_creation_hides_confirmed_source_notice_without_requiring_setup_for_each_certificate(): void
    {
        $this->syntheticCohabitationSource();
        $this->confirm();
        $staff = User::factory()->create();
        $staff->assignRole('hoa_staff');
        foreach ([[$this->admin, 'admin'], [$staff, 'staff']] as [$actor, $panel]) {
            $this->login($actor, $panel);
            Filament::setCurrentPanel($panel);
            Livewire::test(CreateCertificate::class)->set('data.type', CohabitationCertificateData::TYPE)
                ->assertFormFieldIsHidden('cohabitation_source_status')
                ->assertDontSee('Confirmed')->assertDontSee('This source version is confirmed. Preview and issue certificates without repeating setup.')
                ->assertSee('Issue certificate')->assertDontSee('Confirm issuance');
        }
        $this->assertSame('confirmed', app(CohabitationCertificateSource::class)->setupStatus()['state']);
        $this->assertDatabaseCount('certificates', 0);
        $this->assertDatabaseCount('certificate_previews', 0);
    }

    public function test_creation_keeps_unconfirmed_source_review_guidance_visible(): void
    {
        $this->syntheticCohabitationSource();
        $this->login($this->admin, 'admin');
        Filament::setCurrentPanel('admin');
        Livewire::test(CreateCertificate::class)->set('data.type', CohabitationCertificateData::TYPE)
            ->assertFormFieldIsVisible('cohabitation_source_status')
            ->assertSee('Ready for review')->assertSee('Review the original document and confirm its printed signatory once for this source version.');
        $this->assertFalse(app(CohabitationCertificateSource::class)->isConfirmed());
        $this->assertDatabaseCount('certificate_templates', 0);
        $this->assertDatabaseCount('certificates', 0);
    }

    public function test_creation_keeps_missing_source_requirements_visible(): void
    {
        $this->login($this->admin, 'admin');
        Filament::setCurrentPanel('admin');
        Livewire::test(CreateCertificate::class)->set('data.type', CohabitationCertificateData::TYPE)
            ->assertFormFieldIsVisible('cohabitation_source_status')
            ->assertSee('Awaiting corrected document')->assertSee('Corrected original DOCX containing Phase 1 - ____.')
            ->assertSee('Reviewed PDF exported from the corrected original.')->assertSee('Reviewed blank-field mapping for that PDF.');
        $this->assertDatabaseCount('certificate_templates', 0);
        $this->assertDatabaseCount('certificates', 0);
        $this->assertDatabaseCount('certificate_previews', 0);
    }

    public function test_invalid_or_forged_declarations_and_registered_details_are_rejected(): void
    {
        foreach ([['cohabitant_one' => 'Someone Else'], ['cohabitant_one_birth_date' => '1990-02-02'],
            ['cohabitant_two' => ''], ['cohabitant_two_birth_date' => '2026-10-22'],
            ['years_together' => -1], ['years_together' => 0.5], ['years_together' => 50], ['requester' => 'third'],
            ['cohabitant_two_homeowner_id' => $this->owner->id]] as $forged) {
            try {
                app(CohabitationCertificateData::class)->validate([...$this->data, ...$forged], $this->owner);
                $this->fail('Invalid declaration accepted: '.json_encode($forged));
            } catch (ValidationException) {
                $this->assertDatabaseCount('certificates', 0);
            }
        }
    }

    public function test_manual_details_stay_in_snapshot_and_registered_partner_must_share_property(): void
    {
        $valid = app(CohabitationCertificateData::class)->validate($this->data, $this->owner);
        $this->assertSame(5, $valid['years_together']);
        $partnerUser = User::factory()->create(['first_name' => 'María', 'middle_name' => null, 'last_name' => 'Santos', 'date_of_birth' => '1991-02-03']);
        $partner = $partnerUser->homeowner()->create([...$this->owner->only(['street', 'block', 'lot', 'phase', 'phase_suffix', 'residency_date']), 'ownership_type' => 'Co-owner', 'status' => 'Active']);
        $payload = [...$this->data, 'cohabitant_two_homeowner_id' => $partner->id];
        $this->assertSame($partner->id, app(CohabitationCertificateData::class)->validate($payload, $this->owner)['cohabitant_two_homeowner_id']);
        $partner->update(['phase_suffix' => 'C']);
        $this->expectException(ValidationException::class);
        app(CohabitationCertificateData::class)->validate($payload, $this->owner);
    }

    public function test_ui_prefills_registered_details_validates_live_and_clears_preview_on_changes(): void
    {
        $this->actingAs($this->admin, 'admin');
        Filament::setCurrentPanel('admin');
        $page = Livewire::test(CreateCertificate::class)->set('data.type', CohabitationCertificateData::TYPE)
            ->set('data.homeowner_id', $this->owner->id)->assertSet('data.cohabitant_one', $this->owner->user->full_name)
            ->assertSet('data.cohabitant_one_birth_date', '1990-01-02')->assertSet('data.requester', 'one')
            ->set('data.years_together', -1)->assertHasErrors('data.years_together')
            ->set('data.years_together', 5)->assertHasNoErrors('data.years_together');
        $page->assertDontSee('Additional address detail')->call('preparePreview')->assertHasErrors(['data.cohabitant_two', 'data.cohabitant_two_birth_date']);
        $exposed = $page->instance()->cohabitationOwner()->toArray();
        $this->assertArrayNotHasKey('emergency_contact_name', $exposed);
        $this->assertArrayNotHasKey('verification_document', $exposed);
        $this->assertArrayNotHasKey('email', $exposed['user']);
        $this->assertDatabaseCount('certificates', 0);
    }

    public function test_changed_data_expiry_and_wrong_year_require_a_new_preview(): void
    {
        $this->syntheticCohabitationSource();
        $this->confirm();
        $token = app(ResidencyCertificatePreviews::class)->prepare($this->data, $this->admin);
        foreach ([['requester' => 'one'], ['years_together' => 6], ['cohabitant_two' => 'Another Verified Person']] as $change) {
            try {
                app(IssueCertificate::class)->handle([...$this->data, ...$change, 'preview_token' => $token], $this->admin);
                $this->fail('Changed declarations must invalidate the preview.');
            } catch (ValidationException) {
                $this->assertDatabaseCount('certificates', 0);
            }
        }
        $this->travel(16)->minutes();
        $this->expectException(ValidationException::class);
        app(IssueCertificate::class)->handle([...$this->data, 'preview_token' => $token], $this->admin);
    }

    public function test_approved_request_routes_into_form_and_preserves_original_names_and_address(): void
    {
        $request = ServiceRequest::create(['homeowner_id' => $this->owner->id, 'request_type' => CohabitationCertificateData::TYPE,
            'cohabitant_one' => 'Request First Person', 'cohabitant_two' => 'Request Second Person', 'address_snapshot' => $this->owner->only(['house_number', 'street', 'block', 'lot', 'phase', 'phase_suffix']),
            'status' => 'Approved', 'subject' => 'Synthetic request', 'details' => 'Existing request description']);
        $this->actingAs($this->admin, 'admin');
        Filament::setCurrentPanel('admin');
        Livewire::withQueryParams(['request' => $request->id, 'document' => 'butaw'])->test(CreateCertificate::class)
            ->assertSet('sourceRequestId', $request->id)->assertSet('data.type', CohabitationCertificateData::TYPE)
            ->assertSet('data.cohabitant_one', $request->cohabitant_one)->assertSet('data.cohabitant_two', $request->cohabitant_two)
            ->assertSet('data.cohabitant_one_birth_date', null);
        $this->assertSame('Approved', $request->fresh()->status);
    }

    public function test_approved_request_can_finish_through_preview_without_rewriting_declarations(): void
    {
        $this->syntheticCohabitationSource();
        $this->confirm();
        $request = ServiceRequest::create(['homeowner_id' => $this->owner->id, 'request_type' => CohabitationCertificateData::TYPE,
            'cohabitant_one' => 'Request First Person', 'cohabitant_two' => 'Request Second Person', 'address_snapshot' => $this->owner->only(['house_number', 'street', 'block', 'lot', 'phase', 'phase_suffix']),
            'status' => 'Approved', 'subject' => 'Synthetic request', 'details' => 'Preserved request details']);
        $this->login($this->admin, 'admin');
        Filament::setCurrentPanel('admin');
        Livewire::withQueryParams(['request' => $request->id])->test(CreateCertificate::class)
            ->set('data.cohabitant_one_birth_date', '1990-01-01')->set('data.cohabitant_two_birth_date', '1991-01-01')
            ->set('data.years_together', 5)
            ->call('preparePreview')->assertHasNoErrors()->call('create')->assertHasNoErrors();
        $this->assertSame('Completed', $request->fresh()->status);
        $this->assertSame('Request First Person', Certificate::sole()->document_snapshot['cohabitation']['cohabitant_one']);
        $this->assertSame('Preserved request details', $request->fresh()->details);
    }

    public function test_missing_birth_date_is_certificate_only_and_snapshot_contains_only_address_fields(): void
    {
        $this->owner->refresh();
        $request = new ServiceRequest(['cohabitant_one' => 'Unregistered Request Person', 'cohabitant_two' => $this->data['cohabitant_two'],
            'address_snapshot' => $this->owner->only(['house_number', 'street', 'block', 'lot', 'phase', 'phase_suffix'])]);
        $data = app(CohabitationCertificateData::class)->validate([...$this->data, 'cohabitant_one' => $request->cohabitant_one, 'years_together' => 0], $this->owner, $request);
        $this->assertSame(0, $data['years_together']);
        $this->assertSame('1990-01-02', $this->owner->user->fresh()->date_of_birth->toDateString());
        $this->assertEqualsCanonicalizing(['house_number', 'street', 'block', 'lot', 'phase', 'phase_suffix'], array_keys($data['address']));
        $this->assertDatabaseCount('users', 2);
    }

    public function test_wrong_year_and_tampered_source_fail_without_creating_previews(): void
    {
        $this->syntheticCohabitationSource();
        $this->confirm();
        $this->travelTo(now()->setDate(2027, 1, 1));
        try {
            app(ResidencyCertificatePreviews::class)->prepare($this->data, $this->admin);
            $this->fail('Printed 2026 cannot be issued in 2027.');
        } catch (ValidationException) {
            $this->assertDatabaseCount('certificate_previews', 0);
        }
        Storage::disk('local')->put('synthetic-cohabitation/reference.pdf', 'TAMPERED TEST SOURCE');
        $this->expectException(ValidationException::class);
        app(CohabitationCertificateSource::class)->manifest();
    }

    public function test_overflow_and_unsupported_glyphs_are_field_errors_without_layout_changes(): void
    {
        $this->syntheticCohabitationSource();
        foreach ([str_repeat('W', 150), "Test \u{5C71}\u{7530}"] as $value) {
            try {
                app(ResidencyCertificatePreviews::class)->prepare([...$this->data, 'cohabitant_two' => $value], $this->admin);
                $this->fail('Original font and blank must not be changed to fit the value.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('cohabitant_two', $exception->errors());
                $this->assertDatabaseCount('certificate_previews', 0);
            }
        }
    }

    public function test_storage_failure_rolls_back_certificate_number_and_cleans_partial_file(): void
    {
        $this->syntheticCohabitationSource();
        $this->confirm();
        $token = app(ResidencyCertificatePreviews::class)->prepare($this->data, $this->admin);
        $disk = Storage::disk('local');
        $failing = \Mockery::mock($disk);
        $failing->shouldReceive('put')->with(\Mockery::on(fn ($path) => str_starts_with($path, 'certificates/')), \Mockery::type('string'))
            ->andReturnUsing(function ($path) use ($disk): bool {
                $disk->put($path, 'PARTIAL SYNTHETIC FILE');

                return false;
            });
        Storage::shouldReceive('disk')->with('local')->andReturn($failing);
        try {
            app(IssueCertificate::class)->handle([...$this->data, 'preview_token' => $token], $this->admin);
            $this->fail('Storage failure must not issue a certificate.');
        } catch (\RuntimeException) {
            $this->assertDatabaseCount('certificates', 0);
            $this->assertDatabaseCount('certificate_sequences', 0);
            $this->assertSame([], $disk->allFiles('certificates'));
            $this->assertNull(CertificatePreview::sole()->certificate_id);
        }
    }

    public function test_staff_can_prepare_but_not_confirm_and_homeowner_cannot_access_drafts(): void
    {
        $this->syntheticCohabitationSource();
        $staff = User::factory()->create();
        $staff->assignRole('hoa_staff');
        $token = app(ResidencyCertificatePreviews::class)->prepare($this->data, $staff);
        $this->login($this->owner->user, 'homeowner');
        $this->get(route('certificates.residency-preview', $token))->assertForbidden();
        $this->login($staff, 'staff');
        $this->get(route('certificates.cohabitation-reference'))->assertForbidden();
        $this->expectException(AuthorizationException::class);
        app(CohabitationCertificateSource::class)->confirm(['reviewed' => true, 'confirmed' => true, 'note' => 'Forged confirmation'], $staff);
    }

    private function login(User $actor, string $guard): void
    {
        foreach (['admin', 'staff', 'homeowner', 'web'] as $name) {
            Auth::guard($name)->logout();
        }
        Filament::setCurrentPanel(null);
        $this->actingAs($actor, $guard);
    }

    public function test_json_object_key_reordering_does_not_expire_an_unchanged_preview(): void
    {
        $this->syntheticCohabitationSource();
        $token = app(ResidencyCertificatePreviews::class)->prepare($this->data, $this->admin);
        $source = CertificateTemplate::sole();
        $source->forceFill(['layout' => array_reverse($source->layout, true)])->save();
        $this->assertSame($this->admin->id, app(ResidencyCertificatePreviews::class)->read($token, $this->admin)->actor_id);
        $this->assertDatabaseCount('certificates', 0);
    }

    public function test_unicode_and_spacing_normalization_preserves_approved_request_records(): void
    {
        $request = new ServiceRequest(['cohabitant_one' => "Jose\u{0301}  Dela Cruz", 'cohabitant_two' => ' María   Santos ',
            'address_snapshot' => $this->owner->only(['house_number', 'street', 'block', 'lot', 'phase', 'phase_suffix'])]);
        $validated = app(CohabitationCertificateData::class)->validate($this->data, $this->owner, $request);
        $this->assertSame('José Dela Cruz', $validated['cohabitant_one']);
        $this->assertSame('María Santos', $validated['cohabitant_two']);
        $this->assertSame("Jose\u{0301}  Dela Cruz", $request->cohabitant_one);
        $this->assertSame(' María   Santos ', $request->cohabitant_two);
    }
}
