<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Complaint;
use App\Models\Homeowner;
use App\Models\User;
use App\Notifications\NewHomeownerRegistration;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
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
            'password' => 'ResidentPass2026', 'password_confirmation' => 'ResidentPass2026',
            'house_number' => '22', 'street' => 'Narra Street', 'block' => '3', 'lot' => '8',
            'residency_date' => '2020-01-01', 'ownership_type' => 'Owner', 'privacy_consent' => '1',
        ]);

        $response->assertRedirect(route('portal.login'));
        $this->assertDatabaseHas('users', ['email' => 'juan@example.com', 'account_status' => 'Pending']);
        $this->assertDatabaseHas('homeowners', ['house_number' => '22', 'status' => 'Inactive']);
        Notification::assertSentTo($admin, NewHomeownerRegistration::class);
    }

    public function test_pending_homeowner_is_redirected_to_status_page(): void
    {
        $user = User::factory()->pending()->create();
        $user->assignRole('homeowner');
        $user->homeowner()->create(['house_number' => '1', 'street' => 'Narra', 'residency_date' => '2022-01-01', 'ownership_type' => 'Owner', 'status' => 'Inactive']);

        $this->actingAs($user)->get(route('portal.dashboard'))->assertRedirect(route('portal.status'));
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
