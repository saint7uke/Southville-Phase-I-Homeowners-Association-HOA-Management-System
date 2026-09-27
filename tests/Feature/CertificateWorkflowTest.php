<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Certificates\IssueCertificate;
use App\Actions\Certificates\RevokeCertificate;
use App\Filament\Resources\Certificates\Pages\CreateCertificate;
use App\Models\Certificate;
use App\Models\Homeowner;
use App\Models\ServiceRequest;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

final class CertificateWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_authorized_staff_issues_a_private_downloadable_certificate(): void
    {
        $homeowner = $this->homeowner();
        $staff = User::factory()->create(['account_status' => 'Active']);
        $staff->assignRole('hoa_staff');

        $certificate = app(IssueCertificate::class)->handle([
            'homeowner_id' => $homeowner->id,
            'type' => 'Certificate of Residency',
            'purpose' => 'Employment requirement',
        ], $staff);

        $this->assertSame('Issued', $certificate->status);
        $this->assertMatchesRegularExpression('/^CERT-\d{4}-\d{4}$/', $certificate->certificate_number);
        $this->assertNotNull($certificate->file_path);
        Storage::disk('local')->assertExists($certificate->file_path);
        $this->assertStringContainsString('/Subtype /Image', Storage::disk('local')->get($certificate->file_path));
        $this->actingAs($homeowner->user, 'web')
            ->get(URL::temporarySignedRoute('certificates.download', now()->addMinutes(30), $certificate))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
        $this->actingAs($this->homeowner()->user, 'web')
            ->get(URL::temporarySignedRoute('certificates.download', now()->addMinutes(30), $certificate))
            ->assertNotFound();
    }

    public function test_new_certificates_always_expire_at_the_end_of_the_issuance_year(): void
    {
        $this->travelTo(now()->setDate(2026, 4, 15)->setTime(10, 30));
        $homeowner = $this->homeowner();
        $admin = User::factory()->create(['account_status' => 'Active']);
        $admin->assignRole('hoa_admin');

        $certificate = app(IssueCertificate::class)->handle([
            'homeowner_id' => $homeowner->id,
            'type' => 'Community Clearance',
        ], $admin);

        $this->assertSame('2026-12-31 23:59:59', $certificate->expires_at?->format('Y-m-d H:i:s'));
    }

    public function test_certificate_expiry_cannot_be_overridden_by_form_input(): void
    {
        $homeowner = $this->homeowner();
        $admin = User::factory()->create(['account_status' => 'Active']);
        $admin->assignRole('hoa_admin');

        $this->expectException(ValidationException::class);

        app(IssueCertificate::class)->handle([
            'homeowner_id' => $homeowner->id,
            'type' => 'Community Clearance',
            'expires_at' => now()->addMonth(),
        ], $admin);
    }

    public function test_admin_form_issues_certificate_with_automatic_year_end_expiry(): void
    {
        Storage::fake('local');
        $this->travelTo(now()->setDate(2026, 7, 8)->setTime(9, 0));
        $homeowner = $this->homeowner();
        $admin = User::factory()->create(['account_status' => 'Active']);
        $admin->assignRole('hoa_admin');
        $this->actingAs($admin, 'admin');
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreateCertificate::class)
            ->fillForm([
                'homeowner_id' => $homeowner->id,
                'type' => 'Community Clearance',
                'purpose' => 'Employment requirement',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('2026-12-31 23:59:59', Certificate::query()->sole()->expires_at?->format('Y-m-d H:i:s'));
    }

    public function test_revoked_certificate_is_not_downloadable(): void
    {
        $homeowner = $this->homeowner();
        $admin = User::factory()->create(['account_status' => 'Active']);
        $admin->assignRole('hoa_admin');
        $certificate = app(IssueCertificate::class)->handle([
            'homeowner_id' => $homeowner->id,
            'type' => 'Community Clearance',
        ], $admin);

        app(RevokeCertificate::class)->handle($certificate, $admin, 'Information needs correction.');
        $this->assertSame('Revoked', $certificate->refresh()->status);
        $this->actingAs($homeowner->user, 'web')
            ->get(URL::temporarySignedRoute('certificates.download', now()->addMinutes(30), $certificate))
            ->assertNotFound();
    }

    public function test_unsigned_certificate_download_is_rejected(): void
    {
        $homeowner = $this->homeowner();
        $admin = User::factory()->create(['account_status' => 'Active']);
        $admin->assignRole('hoa_admin');
        $certificate = app(IssueCertificate::class)->handle(['homeowner_id' => $homeowner->id, 'type' => 'Community Clearance'], $admin);

        $this->actingAs($homeowner->user, 'web')->get(route('certificates.download', $certificate))->assertForbidden();
    }

    public function test_staff_can_issue_once_from_an_approved_certificate_request(): void
    {
        $homeowner = $this->homeowner();
        $staff = User::factory()->create(['account_status' => 'Active']);
        $staff->assignRole('hoa_staff');
        $request = ServiceRequest::query()->create([
            'homeowner_id' => $homeowner->id,
            'request_type' => 'Certificate of Residency',
            'details' => 'Employment requirement',
            'status' => 'Approved',
        ]);

        $certificate = app(IssueCertificate::class)->fromApprovedRequest($request, $staff);

        $this->assertSame($request->id, $certificate->service_request_id);
        $this->assertSame('Completed', $request->refresh()->status);
        $this->assertSame($certificate->certificate_number, $request->document_output);
        $this->assertSame(now()->endOfYear()->format('Y-m-d H:i:s'), $certificate->expires_at?->format('Y-m-d H:i:s'));
    }

    public function test_staff_can_issue_a_community_clearance_from_an_approved_request(): void
    {
        $homeowner = $this->homeowner();
        $staff = User::factory()->create(['account_status' => 'Active']);
        $staff->assignRole('hoa_staff');
        $request = ServiceRequest::query()->create([
            'homeowner_id' => $homeowner->id,
            'request_type' => 'Community Clearance',
            'subject' => 'Clearance request',
            'details' => 'Needed for a local administrative requirement.',
            'status' => 'Approved',
        ]);

        $certificate = app(IssueCertificate::class)->fromApprovedRequest($request, $staff);

        $this->assertSame('Community Clearance', $certificate->type);
        $this->assertSame('Completed', $request->refresh()->status);
    }

    public function test_manual_issuance_rejects_unsupported_certificate_types(): void
    {
        $admin = User::factory()->create(['account_status' => 'Active']);
        $admin->assignRole('hoa_admin');
        $homeowner = $this->homeowner();

        $this->expectException(ValidationException::class);
        app(IssueCertificate::class)->handle([
            'homeowner_id' => $homeowner->id,
            'type' => 'Unapproved certificate type',
        ], $admin);
    }

    public function test_staff_cannot_revoke_an_issued_certificate_through_the_action(): void
    {
        $admin = User::factory()->create(['account_status' => 'Active']);
        $admin->assignRole('hoa_admin');
        $staff = User::factory()->create(['account_status' => 'Active']);
        $staff->assignRole('hoa_staff');
        $homeowner = $this->homeowner();
        $certificate = app(IssueCertificate::class)->handle([
            'homeowner_id' => $homeowner->id,
            'type' => 'Community Clearance',
        ], $admin);

        try {
            app(RevokeCertificate::class)->handle($certificate, $staff, 'Staff must not revoke certificates.');
            $this->fail('Staff certificate revocation must be denied.');
        } catch (AuthorizationException) {
            $this->assertSame('Issued', $certificate->fresh()->status);
        }
    }

    public function test_certificate_revocation_requires_a_reason(): void
    {
        $admin = User::factory()->create(['account_status' => 'Active']);
        $admin->assignRole('hoa_admin');
        $homeowner = $this->homeowner();
        $certificate = app(IssueCertificate::class)->handle([
            'homeowner_id' => $homeowner->id,
            'type' => 'Community Clearance',
        ], $admin);

        $this->expectException(ValidationException::class);
        app(RevokeCertificate::class)->handle($certificate, $admin, '   ');
    }

    public function test_staff_certificate_page_does_not_render_admin_destructive_actions(): void
    {
        $staff = User::factory()->create(['account_status' => 'Active']);
        $staff->assignRole('hoa_staff');
        $admin = User::factory()->create(['account_status' => 'Active']);
        $admin->assignRole('hoa_admin');
        $homeowner = $this->homeowner();
        $certificate = app(IssueCertificate::class)->handle([
            'homeowner_id' => $homeowner->id,
            'type' => 'Certificate of Residency',
        ], $admin);

        $this->actingAs($staff, 'staff')
            ->get('/staff/certificates/'.$certificate->id.'/edit')
            ->assertOk()
            ->assertDontSee('Revoke')
            ->assertDontSee('Delete')
            ->assertDontSee('Restore');
    }

    public function test_certificate_soft_delete_and_restore_are_admin_only(): void
    {
        $homeowner = $this->homeowner();
        $admin = User::factory()->create(['account_status' => 'Active']);
        $admin->assignRole('hoa_admin');
        $staff = User::factory()->create(['account_status' => 'Active']);
        $staff->assignRole('hoa_staff');
        $certificate = app(IssueCertificate::class)->handle(['homeowner_id' => $homeowner->id, 'type' => 'Community Clearance'], $admin);

        $this->assertTrue($admin->can('delete', $certificate));
        $this->assertFalse($staff->can('delete', $certificate));
        $certificate->delete();
        $this->assertSoftDeleted($certificate);
        $this->assertTrue($admin->can('restore', $certificate));
        $this->assertFalse($staff->can('update', $certificate));
    }

    private function homeowner(): Homeowner
    {
        $index = User::query()->count() + 1;
        $user = User::factory()->create(['email' => "certificate{$index}@example.test", 'account_status' => 'Active']);
        $user->assignRole('homeowner');

        return $user->homeowner()->create([
            'house_number' => (string) $index,
            'street' => 'Certificate Street',
            'block' => "B {$index}",
            'lot' => "L {$index}",
            'phase' => 'Southville Phase I',
            'residency_date' => '2021-01-01',
            'ownership_type' => 'Owner',
            'emergency_contact_name' => 'Contact',
            'emergency_contact_number' => '09123456789',
            'status' => 'Active',
        ]);
    }
}
