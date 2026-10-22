<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Homeowner\Resources\ServiceRequests\Pages\ListServiceRequests as HomeownerRequests;
use App\Filament\Homeowner\Resources\ServiceRequests\Pages\ViewServiceRequest;
use App\Filament\Resources\Certificates\CertificateResource;
use App\Filament\Resources\Certificates\Pages\CreateCertificate;
use App\Filament\Resources\ServiceRequests\Pages\ListServiceRequests;
use App\Models\Certificate;
use App\Models\Homeowner;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\CohabitationCertificateData;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\Support\UsesUploadedResidency;
use Tests\TestCase;

final class CohabitationRequestGuidanceTest extends TestCase
{
    use RefreshDatabase;
    use UsesUploadedResidency;

    private User $admin;

    private User $staff;

    private User $resident;

    private Homeowner $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 22));
        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();
        Storage::fake('local');
        $this->admin = User::factory()->create();
        $this->admin->assignRole('hoa_admin');
        $this->staff = User::factory()->create();
        $this->staff->assignRole('hoa_staff');
        $this->resident = User::factory()->create(['first_name' => 'Synthetic', 'last_name' => 'Resident', 'middle_name' => null]);
        $this->resident->assignRole('homeowner');
        $this->owner = $this->resident->homeowner()->create(['street' => 'Test Street', 'block' => '1', 'lot' => '2',
            'phase' => 'Southville Phase I', 'phase_suffix' => 'A', 'residency_date' => '2020-01-01', 'ownership_type' => 'Owner', 'status' => 'Active']);
    }

    private function serviceRequest(string $status, string $type = CohabitationCertificateData::TYPE): ServiceRequest
    {
        $request = ServiceRequest::create(['homeowner_id' => $this->owner->id, 'request_type' => $type,
            'subject' => 'Synthetic certificate request', 'details' => 'Preserve the original request details.', 'status' => $status,
            'cohabitant_one' => $this->resident->full_name, 'cohabitant_two' => 'Verified Partner',
            'address_snapshot' => $this->owner->only(['house_number', 'street', 'block', 'lot', 'phase', 'phase_suffix'])]);
        // Ignore the normal creation alert; each following GET must send no notifications.
        Notification::fake();

        return $request;
    }

    private function login(User $user, string $guard): void
    {
        foreach (['admin', 'staff', 'homeowner', 'web'] as $name) {
            Auth::guard($name)->logout();
        }
        Filament::setCurrentPanel(null);
        $this->actingAs($user, $guard);
    }

    public function test_managers_follow_approved_requests_into_their_own_panel_without_writes(): void
    {
        $request = $this->serviceRequest('Approved');
        $original = $request->refresh()->getAttributes();
        $auditCount = DB::table('audit_logs')->where('auditable_type', ServiceRequest::class)->count();
        foreach ([[$this->admin, 'admin'], [$this->staff, 'staff']] as [$actor, $panel]) {
            $this->login($actor, $panel);
            $url = CertificateResource::getUrl('create', ['request' => $request->id], panel: $panel);
            $this->get(route('service-requests.draft', $request))->assertRedirect($url)->assertHeader('Cache-Control', 'no-store, private');
            $this->get($url)->assertOk();
            $this->assertNull(Auth::guard($panel === 'admin' ? 'staff' : 'admin')->user());
            Filament::setCurrentPanel($panel);
            Livewire::withQueryParams(['request' => $request->id])->test(CreateCertificate::class)
                ->assertSet('sourceRequestId', $request->id)
                ->assertSet('data.type', CohabitationCertificateData::TYPE)
                ->assertSet('data.cohabitant_one', $request->cohabitant_one)
                ->assertSet('data.cohabitant_two', $request->cohabitant_two)
                ->assertSet('data.cohabitant_two_birth_date', null)
                ->assertSet('data.years_together', null);
        }
        $this->assertSame($original, $request->fresh()->getAttributes());
        $this->assertSame($auditCount, DB::table('audit_logs')->where('auditable_type', ServiceRequest::class)->count());
        $this->assertDatabaseCount('certificates', 0);
        $this->assertDatabaseCount('certificate_previews', 0);
        $this->assertDatabaseCount('certificate_templates', 0);
        Notification::assertNothingSent();
    }

    public function test_nonapproved_manager_links_show_status_guidance_instead_of_409(): void
    {
        foreach ([[$this->admin, 'admin'], [$this->staff, 'staff']] as [$actor, $panel]) {
            $this->login($actor, $panel);
            foreach (['Pending' => 'awaiting HOA review', 'Processing' => 'The HOA is reviewing', 'Rejected' => 'was rejected', 'Completed' => 'is completed'] as $status => $message) {
                $request = $this->serviceRequest($status);
                $response = $this->get(route('service-requests.draft', $request))->assertOk()
                    ->assertViewIs('certificates.request-guidance')->assertSee($message)
                    ->assertSee('Back to requests')->assertDontSee('Stack Trace')
                    ->assertDontSee('Confirm the original Cohabitation source')
                    ->assertHeader('Cache-Control', 'no-store, private');
                $response->assertViewHas('returnUrl', route("filament.{$panel}.resources.service-requests.edit", $request));
                $this->get(CertificateResource::getUrl('create', ['request' => $request->id], panel: $panel))->assertNotFound();
                $this->assertSame($status, $request->fresh()->status);
                Notification::assertNothingSent();
            }
        }
        $this->assertDatabaseCount('certificates', 0);
        $this->assertDatabaseCount('certificate_previews', 0);
        Notification::assertNothingSent();
    }

    public function test_homeowners_get_guidance_for_every_status_and_no_management_link(): void
    {
        $this->login($this->resident, 'homeowner');
        foreach (['Pending' => 'awaiting HOA review', 'Processing' => 'The HOA is reviewing', 'Approved' => 'is approved', 'Rejected' => 'was rejected', 'Completed' => 'is completed'] as $status => $message) {
            $request = $this->serviceRequest($status);
            $this->get(route('service-requests.draft', $request))->assertOk()->assertSee($message)
                ->assertViewHas('returnUrl', route('filament.homeowner.resources.service-requests.view', $request))
                ->assertDontSee('/admin/certificates/create')->assertDontSee('/staff/certificates/create')
                ->assertHeader('Cache-Control', 'no-store, private');
            Notification::assertNothingSent();
        }
        $this->assertDatabaseCount('certificates', 0);
        $this->assertDatabaseCount('certificate_previews', 0);
        $this->assertDatabaseCount('certificate_templates', 0);
        Notification::assertNothingSent();
    }

    public function test_draft_guidance_does_not_expose_requests_to_unauthorized_actors(): void
    {
        $request = $this->serviceRequest('Approved');
        $stranger = User::factory()->create();
        $stranger->assignRole('homeowner');
        $this->login($stranger, 'homeowner');
        $this->get(route('service-requests.draft', $request))->assertForbidden()->assertDontSee($request->ticket_number);
        $this->resident->forceFill(['email_verified_at' => null])->save();
        $this->login($this->resident, 'homeowner');
        $this->get(route('service-requests.draft', $request))->assertForbidden();
        $this->staff->roles()->first()->revokePermissionTo('manage_requests');
        $this->login($this->staff, 'staff');
        $this->get(route('service-requests.draft', $request))->assertForbidden();
        $this->admin->forceFill(['account_status' => 'Inactive'])->save();
        $this->login($this->admin, 'admin');
        $this->get(route('service-requests.draft', $request))->assertForbidden();
    }

    public function test_deleted_requests_are_not_redirected_into_certificate_preparation(): void
    {
        $request = $this->serviceRequest('Approved');
        $request->delete();
        $this->login($this->admin, 'admin');
        $this->get(route('service-requests.draft', $request))->assertNotFound();
        $this->assertDatabaseCount('certificates', 0);
    }

    public function test_manager_table_only_offers_preparation_after_approval_and_keeps_residency_drafts(): void
    {
        $requests = collect(['Pending', 'Processing', 'Approved', 'Rejected', 'Completed'])->mapWithKeys(fn (string $status) => [$status => $this->serviceRequest($status)]);
        $residency = $this->serviceRequest('Pending', 'Certificate of Residency');
        foreach ([[$this->admin, 'admin'], [$this->staff, 'staff']] as [$actor, $panel]) {
            $this->login($actor, $panel);
            Filament::setCurrentPanel($panel);
            $page = Livewire::test(ListServiceRequests::class);
            foreach ($requests as $status => $request) {
                $page->assertTableActionHidden('draftPreview', $request);
                if ($status === 'Approved') {
                    $page->assertTableActionVisible('issueCertificate', $request)
                        ->assertTableActionHasLabel('issueCertificate', 'Prepare certificate', $request)
                        ->assertTableActionHasUrl('issueCertificate', CertificateResource::getUrl('create', ['request' => $request->id], panel: $panel), $request);
                } else {
                    $page->assertTableActionHidden('issueCertificate', $request);
                }
            }
            $page->assertTableActionVisible('draftPreview', $residency)
                ->assertTableActionHasUrl('draftPreview', route('service-requests.draft', $residency), $residency);
        }
    }

    public function test_homeowner_table_hides_cohabitation_drafts_but_keeps_residency_previews(): void
    {
        $requests = collect(['Pending', 'Processing', 'Approved', 'Rejected', 'Completed'])->map(fn (string $status) => $this->serviceRequest($status));
        $residency = $this->serviceRequest('Pending', 'Certificate of Residency');
        $this->login($this->resident, 'homeowner');
        Filament::setCurrentPanel('homeowner');
        $page = Livewire::test(HomeownerRequests::class);
        foreach ($requests as $request) {
            $page->assertTableActionHidden('draftPreview', $request);
        }
        $page->assertTableActionVisible('draftPreview', $residency);
    }

    public function test_homeowner_request_details_show_guidance_and_keep_the_saved_certificate_action(): void
    {
        $request = $this->serviceRequest('Completed');
        $certificate = Certificate::create(['homeowner_id' => $this->owner->id, 'service_request_id' => $request->id,
            'certificate_number' => 'CERT-2026-SYNTHETIC', 'type' => CohabitationCertificateData::TYPE, 'file_path' => 'certificates/synthetic.pdf',
            'issued_at' => now(), 'issued_by' => $this->admin->id, 'status' => 'Issued']);
        $this->login($this->resident, 'homeowner');
        Filament::setCurrentPanel('homeowner');
        Livewire::test(ViewServiceRequest::class, ['record' => $request->id])
            ->assertSee('This Cohabitation request is completed.')
            ->assertActionVisible('download_certificate')
            ->assertActionHasUrl('download_certificate', URL::temporarySignedRoute('certificates.download', now()->addMinutes(30), $certificate));
    }

    public function test_filipino_guidance_translates_presentation_without_changing_request_data(): void
    {
        $request = $this->serviceRequest('Approved');
        $this->resident->forceFill(['preferred_locale' => 'fil'])->save();
        $this->login($this->resident, 'homeowner');
        $this->get(route('service-requests.draft', $request))->assertOk()
            ->assertSee('Paghahanda ng sertipiko ng Cohabitation')
            ->assertSee('Aprubado na ang iyong kahilingan para sa Cohabitation.')
            ->assertSee('Bumalik sa mga kahilingan')->assertSee('lang="fil"', false);
        $this->assertSame('Approved', $request->fresh()->status);
        $this->assertSame('Preserve the original request details.', $request->fresh()->details);
    }

    public function test_guidance_is_accessible_and_escapes_record_text_in_both_languages(): void
    {
        if (getenv('HOA_BROWSER_EXPORT_REQUEST_GUIDANCE_FIXTURES') === 'true') {
            URL::forceRootUrl('http://certificate-guidance.test');
        }
        $this->login($this->resident, 'homeowner');
        foreach (['en', 'fil'] as $locale) {
            $this->resident->forceFill(['preferred_locale' => $locale])->save();
            foreach (['Pending', 'Approved', 'Rejected', 'Completed'] as $status) {
                $request = $this->serviceRequest($status);
                $response = $this->get(route('service-requests.draft', $request))->assertOk()
                    ->assertSee('aria-labelledby="request-guidance-heading"', false)
                    ->assertSee('data-i18n="Back to requests"', false)->assertSee($request->ticket_number)
                    ->assertDontSee('Stack Trace');
                $this->exportFixture($locale.'-'.strtolower($status), $response->getContent());
            }
        }
        $request = $this->serviceRequest('Pending');
        DB::table('service_requests')->where('id', $request->id)->update(['ticket_number' => '<b>TEST</b>']);
        $this->get(route('service-requests.draft', $request))->assertOk()
            ->assertSee('&lt;b&gt;TEST&lt;/b&gt;', false)->assertDontSee('<b>TEST</b>', false);
    }

    private function exportFixture(string $name, string $html): void
    {
        if (getenv('HOA_BROWSER_EXPORT_REQUEST_GUIDANCE_FIXTURES') !== 'true') {
            return;
        }
        if (! app()->environment('testing') || config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Only synthetic in-memory SQLite responses may be exported.');
        }
        $directory = storage_path('framework/testing/cohabitation-request-guidance');
        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        file_put_contents($directory.'/'.$name.'.html', $html, LOCK_EX);
    }

    public function test_residency_draft_still_returns_a_private_pdf_without_issuing(): void
    {
        $this->confirmUploadedSource();
        $request = $this->serviceRequest('Pending', 'Certificate of Residency');
        $this->login($this->resident, 'homeowner');
        $response = $this->get(route('service-requests.draft', $request))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertDatabaseCount('certificates', 0);
        $this->assertDatabaseCount('certificate_previews', 0);
        $this->assertSame('Pending', $request->fresh()->status);
        Notification::assertNothingSent();
    }
}
