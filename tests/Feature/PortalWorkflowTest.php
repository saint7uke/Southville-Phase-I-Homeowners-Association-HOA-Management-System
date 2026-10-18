<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Homeowners\RegisterHomeowner;
use App\Models\Complaint;
use App\Models\Homeowner;
use App\Models\User;
use App\Notifications\NewHomeownerRegistration;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

final class PortalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_homeowner_can_submit_registration_for_review(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['email' => 'admin@test.local']);
        $admin->assignRole('hoa_admin');

        $response = $this->post(route('portal.register.store'), [
            'first_name' => 'juan', 'middle_name' => 'santos', 'last_name' => 'dela cruz', 'sex' => 'Male',
            'date_of_birth' => '1990-05-10', 'contact_number' => '09171234567', 'email' => 'JUAN@EXAMPLE.COM',
            'password' => 'ResidentPass!2026', 'password_confirmation' => 'ResidentPass!2026',
            'house_number' => '22', 'street' => 'Narra Street', 'block' => '3', 'lot' => '8',
            'residency_date' => '2020-01-01', 'ownership_type' => 'Owner', 'privacy_consent' => '1',
        ]);

        $response->assertRedirect(route('portal.login'));
        $this->assertDatabaseHas('users', ['email' => 'juan@example.com', 'account_status' => 'Pending']);
        $this->assertDatabaseHas('homeowners', ['house_number' => '22', 'status' => 'Inactive']);
        Notification::assertSentTo($admin, NewHomeownerRegistration::class);
    }

    public function test_email_availability_rejects_an_email_reserved_by_a_force_deleted_user(): void
    {
        $user = User::factory()->create(['email' => 'reserved@example.test']);
        $user->forceDelete();

        $this->getJson(route('homeowner.register.email-availability', ['email' => 'RESERVED@example.test']))
            ->assertOk()
            ->assertJson(['available' => false]);

        $this->post(route('homeowner.register.store'), [
            'first_name' => 'Reserved', 'last_name' => 'Applicant', 'sex' => 'Female',
            'date_of_birth' => '1990-05-10', 'contact_number' => '09171234567', 'email' => 'reserved@example.test',
            'password' => 'ResidentPass!2026', 'password_confirmation' => 'ResidentPass!2026',
            'house_number' => '22', 'street' => 'Narra Street', 'block' => '3', 'lot' => '8',
            'residency_date' => '2020-01-01', 'ownership_type' => 'Owner', 'privacy_consent' => '1',
        ])->assertSessionHasErrors('email');
    }

    public function test_email_availability_accepts_a_new_email_and_rejects_invalid_input(): void
    {
        $this->getJson(route('homeowner.register.email-availability', ['email' => 'new@example.test']))
            ->assertOk()
            ->assertJson(['available' => true]);

        $this->getJson(route('homeowner.register.email-availability', ['email' => 'not-an-email']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');
    }

    public function test_registration_route_retains_rate_limiting(): void
    {
        $middleware = Route::getRoutes()
            ->getByName('homeowner.register.store')
            ->gatherMiddleware();

        $this->assertContains('throttle:5,1', $middleware);
    }

    public function test_registration_page_is_not_redirected_to_landing_for_an_existing_homeowner_session(): void
    {
        $user = User::factory()->pending()->create();
        $user->assignRole('homeowner');

        $this->actingAs($user)
            ->get(route('homeowner.register'))
            ->assertOk()
            ->assertSee('Create your homeowner account');
    }

    public function test_existing_homeowner_session_can_submit_registration_to_the_status_page(): void
    {
        Notification::fake();
        $user = User::factory()->pending()->create(['email' => 'existing@example.test']);
        $user->assignRole('homeowner');
        $user->homeowner()->create([
            'house_number' => '1',
            'street' => 'Narra',
            'residency_date' => '2022-01-01',
            'ownership_type' => 'Owner',
            'status' => 'Inactive',
        ]);

        $response = $this->actingAs($user)->post(route('homeowner.register.store'), [
            'first_name' => 'New',
            'last_name' => 'Applicant',
            'sex' => 'Female',
            'date_of_birth' => '1990-05-10',
            'contact_number' => '09171234567',
            'email' => 'new-applicant@example.test',
            'password' => 'ResidentPass!2026',
            'password_confirmation' => 'ResidentPass!2026',
            'house_number' => '22',
            'street' => 'Narra Street',
            'block' => '3',
            'lot' => '8',
            'residency_date' => '2020-01-01',
            'ownership_type' => 'Owner',
            'privacy_consent' => '1',
        ]);

        $response->assertRedirect();
        $statusUrl = (string) $response->headers->get('Location');
        $this->assertStringStartsWith(route('homeowner.register.submitted'), $statusUrl);
        $this->get($statusUrl)
            ->assertOk()
            ->assertSee('Your application is under review')
            ->assertSee('new-applicant@example.test');
    }

    public function test_pending_homeowner_is_redirected_to_status_page(): void
    {
        $user = User::factory()->pending()->create();
        $user->assignRole('homeowner');
        $user->homeowner()->create(['house_number' => '1', 'street' => 'Narra', 'residency_date' => '2022-01-01', 'ownership_type' => 'Owner', 'status' => 'Inactive']);

        $this->actingAs($user)->get(route('portal.dashboard'))->assertRedirect(route('portal.status'));
    }

    public function test_registration_status_page_uses_an_expiring_signed_url(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['email' => 'admin@test.local']);
        $admin->assignRole('hoa_admin');

        $response = $this->post(route('homeowner.register.store'), [
            'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'sex' => 'Male',
            'date_of_birth' => '1990-05-10', 'contact_number' => '09171234567', 'email' => 'juan@example.com',
            'password' => 'ResidentPass!2026', 'password_confirmation' => 'ResidentPass!2026',
            'house_number' => '22', 'street' => 'Narra Street', 'block' => '3', 'lot' => '8', 'residency_date' => '2020-01-01',
            'ownership_type' => 'Owner', 'privacy_consent' => '1',
        ]);

        $response->assertRedirect();
        $statusUrl = (string) $response->headers->get('Location');
        $this->get($statusUrl)
            ->assertOk()
            ->assertSee('Your application is under review')
            ->assertSee('Pending verification');

        parse_str((string) parse_url($statusUrl, PHP_URL_QUERY), $query);
        $this->assertArrayHasKey('expires', $query);
        $this->assertArrayHasKey('signature', $query);
    }

    public function test_existing_signed_status_link_can_be_reopened_before_expiry(): void
    {
        $user = User::factory()->pending()->create(['email' => 'reopen@example.test']);
        $user->assignRole('homeowner');
        $user->homeowner()->create([
            'house_number' => '10',
            'street' => 'Mahogany',
            'residency_date' => '2022-01-01',
            'ownership_type' => 'Owner',
            'status' => 'Inactive',
        ]);

        $url = URL::temporarySignedRoute(
            'homeowner.register.submitted',
            now()->addHours(24),
            ['user' => $user->id],
            false,
        );

        $this->get($url)
            ->assertOk()
            ->assertSee('Your application is under review')
            ->assertSee('reopen@example.test');
    }

    public function test_registration_status_rejects_tampered_and_expired_signed_urls(): void
    {
        $first = User::factory()->pending()->create(['email' => 'first@example.com']);
        $first->assignRole('homeowner');
        $first->homeowner()->create(['house_number' => '1', 'street' => 'Narra', 'residency_date' => '2022-01-01', 'ownership_type' => 'Owner', 'status' => 'Inactive']);
        $second = User::factory()->pending()->create(['email' => 'second@example.com']);
        $second->assignRole('homeowner');
        $second->homeowner()->create(['house_number' => '2', 'street' => 'Narra', 'residency_date' => '2022-01-01', 'ownership_type' => 'Owner', 'status' => 'Inactive']);

        $url = URL::temporarySignedRoute('homeowner.register.submitted', now()->addHour(), ['user' => $first->id], false);
        $tampered = str_replace('user='.$first->id, 'user='.$second->id, $url);
        $this->get($tampered)->assertForbidden();

        $expired = URL::temporarySignedRoute('homeowner.register.submitted', now()->subMinute(), ['user' => $first->id], false);
        $this->get($expired)
            ->assertForbidden()
            ->assertSee('This status link has expired')
            ->assertSee('Continue to homeowner login');
    }

    public function test_authenticated_homeowner_can_only_view_own_status_and_sees_approval(): void
    {
        $first = User::factory()->pending()->create(['email' => 'first@example.com']);
        $first->assignRole('homeowner');
        $first->homeowner()->create(['house_number' => '1', 'street' => 'Narra', 'residency_date' => '2022-01-01', 'ownership_type' => 'Owner', 'status' => 'Inactive']);
        $second = User::factory()->pending()->create(['email' => 'second@example.com']);
        $second->assignRole('homeowner');
        $second->homeowner()->create(['house_number' => '2', 'street' => 'Narra', 'residency_date' => '2022-01-01', 'ownership_type' => 'Owner', 'status' => 'Inactive']);

        $this->actingAs($first)->get(route('portal.status'))
            ->assertOk()
            ->assertSee('Your application is under review')
            ->assertSee('first@example.com')
            ->assertDontSee('second@example.com');

        $this->actingAs($first)->get(URL::temporarySignedRoute('homeowner.register.submitted', now()->addHour(), ['user' => $second->id], false))
            ->assertNotFound();

        $first->forceFill([
            'account_status' => 'Active',
            'approved_at' => now(),
        ])->save();
        $first->homeowner->update(['status' => 'Active']);

        $this->actingAs($first)->get(route('portal.status'))
            ->assertOk()
            ->assertSee('Your application is approved')
            ->assertSee('Approved on');
    }

    public function test_rejected_registration_displays_admin_feedback(): void
    {
        $user = User::factory()->create([
            'account_status' => 'Rejected',
            'rejection_reason' => 'Please upload a clearer proof of residency.',
        ]);
        $user->assignRole('homeowner');
        $user->homeowner()->create([
            'house_number' => '3', 'street' => 'Narra', 'residency_date' => '2022-01-01',
            'ownership_type' => 'Owner', 'status' => 'Inactive',
        ]);

        $url = URL::temporarySignedRoute('homeowner.register.submitted', now()->addHour(), ['user' => $user->id], false);
        $this->get($url)
            ->assertOk()
            ->assertSee('Action is needed on your application')
            ->assertSee('Please upload a clearer proof of residency.')
            ->assertSee('Admin feedback');
    }

    public function test_registration_enforces_the_configured_password_composition(): void
    {
        $this->post(route('portal.register.store'), [
            'first_name' => 'Maria', 'last_name' => 'Reyes', 'sex' => 'Female',
            'date_of_birth' => '1992-04-10', 'contact_number' => '09181234567', 'email' => 'maria@example.test',
            'password' => 'ResidentPass2026', 'password_confirmation' => 'ResidentPass2026',
            'house_number' => '23', 'street' => 'Narra Street', 'block' => '3', 'lot' => '9',
            'residency_date' => '2021-01-01', 'ownership_type' => 'Owner', 'privacy_consent' => '1',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'maria@example.test']);
    }

    public function test_tenant_registration_rejects_a_lease_end_date_not_after_the_start_date(): void
    {
        $this->post(route('homeowner.register.store'), [
            'first_name' => 'Maria', 'last_name' => 'Reyes', 'sex' => 'Female',
            'date_of_birth' => '1992-04-10', 'contact_number' => '09181234567', 'email' => 'tenant@example.test',
            'password' => 'ResidentPass!2026', 'password_confirmation' => 'ResidentPass!2026',
            'house_number' => '23', 'street' => 'Narra Street', 'block' => '3', 'lot' => '9',
            'ownership_type' => 'Tenant', 'landlord_name' => 'Landlord Reyes', 'landlord_contact' => '09181234568',
            'lease_start_date' => '2026-10-10', 'lease_end_date' => '2026-10-10', 'privacy_consent' => '1',
        ])->assertSessionHasErrors('lease_end_date');

        $this->assertDatabaseMissing('users', ['email' => 'tenant@example.test']);
    }

    public function test_tenant_registration_uses_lease_start_as_residency_date(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['email' => 'admin@test.local']);
        $admin->assignRole('hoa_admin');
        $landlord = User::factory()->create([
            'first_name' => 'Luke',
            'middle_name' => null,
            'last_name' => 'Landlord',
            'suffix' => null,
        ]);
        $landlord->assignRole('homeowner');
        $landlordRecord = $landlord->homeowner()->create([
            'house_number' => '12', 'street' => 'Bugbane', 'block' => '71', 'lot' => '10',
            'phase' => 'Southville Phase I', 'residency_date' => '2020-01-01',
            'ownership_type' => 'Owner', 'status' => 'Active',
        ]);

        $this->post(route('homeowner.register.store'), [
            'first_name' => 'Luke', 'last_name' => 'Tenant', 'sex' => 'Male',
            'date_of_birth' => '1992-04-10', 'contact_number' => '09181234567', 'email' => 'tenant@example.test',
            'password' => 'ResidentPass!2026', 'password_confirmation' => 'ResidentPass!2026',
            'house_number' => '12', 'street' => 'Bugbane', 'block' => '71', 'lot' => '10',
            'ownership_type' => 'Tenant', 'landlord_name' => 'Luke Landlord', 'landlord_contact' => '09372372572',
            'lease_start_date' => '2026-10-10', 'lease_end_date' => '2026-12-18', 'privacy_consent' => '1',
        ])->assertRedirect();

        $tenant = Homeowner::query()->where('ownership_type', 'Tenant')->latest('id')->firstOrFail();
        $this->assertSame('2026-10-10', $tenant->residency_date->toDateString());
        $this->assertSame('2026-10-10', $tenant->lease_start_date->toDateString());
        $this->assertSame('2026-12-18', $tenant->lease_end_date->toDateString());
        $this->assertSame($landlordRecord->id, $tenant->landlord_homeowner_id);
    }

    public function test_registration_rolls_back_and_removes_a_stored_document_when_persistence_fails(): void
    {
        Storage::fake('local');

        $document = UploadedFile::fake()->create('proof-of-residency.pdf', 100, 'application/pdf');

        $this->expectException(QueryException::class);

        try {
            app(RegisterHomeowner::class)->handle([
                'first_name' => 'Failed',
                'last_name' => 'Registration',
                'sex' => 'Prefer not to say',
                'contact_number' => '09171234567',
                'date_of_birth' => '1990-05-10',
                'email' => 'failed-registration@example.test',
                'password' => 'ResidentPass!2026',
                'house_number' => '22',
                'street' => null,
                'ownership_type' => 'Owner',
                'residency_date' => '2020-01-01',
                'verification_document' => $document,
            ]);
        } finally {
            $this->assertEmpty(Storage::disk('local')->allFiles('verification_documents'));
            $this->assertDatabaseMissing('users', ['email' => 'failed-registration@example.test']);
        }
    }

    public function test_homeowner_cannot_view_another_residents_complaint(): void
    {
        [$first, $firstHome] = $this->resident('first@example.test');
        [, $secondHome] = $this->resident('second@example.test');
        $complaint = Complaint::query()->create(['homeowner_id' => $secondHome->id, 'ticket_number' => 'CMP-2026-TEST0001', 'subject' => 'Street light concern', 'description' => 'The street light has been off for several nights.', 'category' => 'Common Area', 'priority' => 'Medium', 'status' => 'Pending']);

        $this->actingAs($first)->get(route('portal.complaints.show', $complaint))->assertNotFound();
        $this->assertNotSame($firstHome->id, $secondHome->id);
    }

    /** @return array{User, Homeowner} */
    private function resident(string $email): array
    {
        $user = User::factory()->create(['email' => $email, 'account_status' => 'Active']);
        $user->assignRole('homeowner');
        $homeowner = $user->homeowner()->create(['house_number' => '10', 'street' => 'Mahogany', 'residency_date' => '2021-01-01', 'ownership_type' => 'Owner', 'status' => 'Active']);

        return [$user, $homeowner];
    }
}
