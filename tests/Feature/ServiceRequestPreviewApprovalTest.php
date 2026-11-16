<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Homeowner\Resources\ServiceRequests\Pages\ListServiceRequests as HomeownerRequests;
use App\Filament\Homeowner\Resources\ServiceRequests\Pages\ViewServiceRequest;
use App\Filament\Resources\ServiceRequests\Pages\ListServiceRequests;
use App\Models\Certificate;
use App\Models\Homeowner;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\OriginalCertificateRenderer;
use App\Services\ResidencyCertificatePreviews;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\UsesUploadedResidency;
use Tests\TestCase;

final class ServiceRequestPreviewApprovalTest extends TestCase
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
        $this->travelTo(now()->setDate(2026, 11, 8));
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
            'phase' => 'Southville Phase I', 'residency_date' => '2020-01-01', 'ownership_type' => 'Owner', 'status' => 'Active']);
    }

    #[DataProvider('statuses')]
    public function test_buttons_and_direct_urls_require_approval_in_every_panel(string $panel, string $status): void
    {
        $request = $this->serviceRequest($status);
        $this->login($panel);
        $page = Livewire::test($panel === 'homeowner' ? HomeownerRequests::class : ListServiceRequests::class);
        if ($status === 'Approved' && $panel !== 'homeowner') {
            $page->assertTableActionVisible('draftPreview', $request)
                ->assertTableActionHasUrl('draftPreview', route('service-requests.draft', $request), $request);
            $this->confirmUploadedSource();
            $renderer = app(OriginalCertificateRenderer::class);
            $proxy = Mockery::mock($renderer);
            $proxy->shouldReceive('render')->once()->with(Mockery::type('array'), Mockery::type('array'), true)
                ->andReturnUsing(fn (array $layout, array $values, bool $draft): string => $renderer->render($layout, $values, $draft));
            $this->instance(OriginalCertificateRenderer::class, $proxy);
        } else {
            $page->assertTableActionHidden('draftPreview', $request);
        }
        $before = $request->fresh()->getAttributes();
        $auditCount = DB::table('audit_logs')->count();
        Notification::fake();
        $response = $this->get(route('service-requests.draft', $request));
        if ($status === 'Approved' && $panel !== 'homeowner') {
            $response->assertOk()->assertHeader('Content-Type', 'application/pdf')->assertHeader('Cache-Control', 'no-store, private');
            $this->assertTrue(str_starts_with($response->getContent(), '%PDF-'), 'The approved draft must contain PDF bytes.');
        } else {
            $response->assertForbidden();
            $this->assertFalse(str_starts_with($response->getContent(), '%PDF-'));
        }
        $this->assertSame($before, $request->fresh()->getAttributes());
        $this->assertDatabaseCount('certificates', 0);
        $this->assertDatabaseCount('certificate_previews', 0);
        $this->assertSame($auditCount, DB::table('audit_logs')->count());
        Notification::assertNothingSent();
    }

    public static function statuses(): array
    {
        $cases = [];
        foreach (['admin', 'staff', 'homeowner'] as $panel) {
            foreach (['Pending', 'Processing', 'Approved', 'Rejected', 'Completed'] as $status) {
                $cases[$panel.' '.$status] = [$panel, $status];
            }
        }

        return $cases;
    }

    public function test_approved_preview_still_checks_ownership_permissions_and_verified_email(): void
    {
        $request = $this->serviceRequest('Approved');
        $stranger = User::factory()->create();
        $stranger->assignRole('homeowner');
        $this->actingAs($stranger, 'homeowner')->get(route('service-requests.draft', $request))->assertForbidden();
        $this->login('homeowner');
        $this->resident->forceFill(['email_verified_at' => null])->save();
        $this->get(route('service-requests.draft', $request))->assertForbidden();
        $this->login('staff');
        $this->staff->syncRoles([]);
        $this->get(route('service-requests.draft', $request))->assertForbidden();
        $this->assertDatabaseCount('certificates', 0);
    }

    public function test_archived_approved_request_is_hidden_and_its_direct_url_is_not_found(): void
    {
        $request = $this->serviceRequest('Approved');
        $request->delete();
        foreach (['admin', 'staff', 'homeowner'] as $panel) {
            $this->login($panel);
            $this->get(route('service-requests.draft', $request))->assertNotFound();
            $page = Livewire::test($panel === 'homeowner' ? HomeownerRequests::class : ListServiceRequests::class);
            $page->assertCanNotSeeTableRecords([$request]);
        }
    }

    public function test_prepared_token_cannot_bypass_a_subsequent_status_change(): void
    {
        $request = $this->serviceRequest('Approved');
        $this->login('admin');
        $this->confirmUploadedSource();
        $data = ['homeowner_id' => $this->owner->id, 'type' => 'Certificate of Residency', 'purpose' => 'Test purpose'];
        $previews = app(ResidencyCertificatePreviews::class);
        $token = $previews->prepare($data, $this->admin, $request->id);
        // Simulate stale persisted state without weakening production transition rules.
        DB::table('service_requests')->where('id', $request->id)->update(['status' => 'Rejected']);
        $this->get(route('certificates.residency-preview', $token))->assertGone();
        $this->get(route('service-requests.draft', $request))->assertForbidden();
        try {
            DB::transaction(fn () => $previews->lockAndValidate($token, $data, $this->admin, $request->id));
            $this->fail('A stale token must not authorize issuance.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('preview_token', $exception->errors());
        }
        $this->assertDatabaseCount('certificates', 0);
        $this->assertNull(DB::table('certificate_previews')->value('certificate_id'));
    }

    public function test_completed_request_keeps_the_issued_file_for_managers_and_denies_resident_download(): void
    {
        $request = $this->serviceRequest('Completed');
        $certificate = Certificate::create(['homeowner_id' => $this->owner->id, 'service_request_id' => $request->id,
            'certificate_number' => 'CERT-2026-PREVIEW-TEST', 'type' => 'Certificate of Residency',
            'file_path' => 'certificates/synthetic.pdf', 'issued_at' => now(), 'issued_by' => $this->admin->id, 'status' => 'Issued']);
        $savedBytes = file_get_contents(resource_path('certificates/residency-2026/reference.pdf'));
        Storage::disk('local')->put($certificate->file_path, $savedBytes);
        $this->login('homeowner');
        $url = URL::temporarySignedRoute('certificates.download', now()->addMinutes(30), $certificate);
        Livewire::test(ViewServiceRequest::class, ['record' => $request->id])
            ->assertActionDoesNotExist('download_certificate');
        $this->get($url)->assertForbidden();
        $this->login('admin');
        $download = $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame(hash('sha256', $savedBytes), hash('sha256', $download->streamedContent()));
        $this->get(route('service-requests.draft', $request))->assertForbidden();
        $this->assertDatabaseCount('certificates', 1);
    }

    public function test_a_consumed_request_preview_token_cannot_render_another_draft_after_completion(): void
    {
        $request = $this->serviceRequest('Approved');
        $this->login('admin');
        $this->confirmUploadedSource();
        $data = ['homeowner_id' => $this->owner->id, 'type' => 'Certificate of Residency', 'purpose' => 'Test purpose'];
        $previews = app(ResidencyCertificatePreviews::class);
        $token = $previews->prepare($data, $this->admin, $request->id);
        $certificate = Certificate::create(['homeowner_id' => $this->owner->id, 'service_request_id' => $request->id,
            'certificate_number' => 'CERT-2026-CONSUMED-PREVIEW', 'type' => 'Certificate of Residency',
            'file_path' => 'certificates/synthetic.pdf', 'issued_at' => now(), 'issued_by' => $this->admin->id, 'status' => 'Issued']);
        DB::table('certificate_previews')->update(['certificate_id' => $certificate->id]);
        DB::table('service_requests')->where('id', $request->id)->update(['status' => 'Completed']);
        $this->get(route('certificates.residency-preview', $token))->assertForbidden();
        // The existing domain-level consumed-token semantics remain available to issuance retries.
        $preview = DB::transaction(fn () => $previews->lockAndValidate($token, $data, $this->admin, $request->id));
        $this->assertSame($certificate->id, $preview->certificate_id);
        $this->assertDatabaseCount('certificates', 1);
    }

    public function test_denial_message_has_a_filipino_translation_and_is_returned_by_the_endpoint(): void
    {
        $message = 'Certificates must be collected at the HOA office with the required signature and dry seal.';
        $this->assertNotSame($message, __($message, [], 'fil'));
        $request = $this->serviceRequest('Pending');
        $this->resident->forceFill(['preferred_locale' => 'fil'])->save();
        $this->login('homeowner');
        $this->getJson(route('service-requests.draft', $request))->assertForbidden()
            ->assertJsonPath('message', __($message, [], 'fil'));
    }

    private function serviceRequest(string $status): ServiceRequest
    {
        $request = ServiceRequest::create(['homeowner_id' => $this->owner->id, 'request_type' => 'Certificate of Residency',
            'subject' => 'Synthetic certificate request', 'details' => 'Test purpose', 'status' => $status,
            'address_snapshot' => $this->owner->only(['house_number', 'street', 'block', 'lot', 'phase', 'phase_suffix'])]);
        Notification::fake();

        return $request;
    }

    private function login(string $panel): void
    {
        foreach (['admin', 'staff', 'homeowner', 'web'] as $guard) {
            Auth::guard($guard)->logout();
        }
        Filament::setCurrentPanel(Filament::getPanel($panel));
        $this->actingAs(match ($panel) {
            'admin' => $this->admin, 'staff' => $this->staff, default => $this->resident
        }, $panel);
    }
}
