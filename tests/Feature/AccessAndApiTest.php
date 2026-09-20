<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\DuesSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class AccessAndApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_public_announcements_api_has_a_data_envelope(): void
    {
        $admin = User::factory()->create();
        Announcement::query()->create(['title' => 'Water interruption', 'content' => 'Water service will pause for scheduled maintenance.', 'category' => 'Maintenance', 'status' => 'Published', 'published_at' => now(), 'created_by' => $admin->id]);

        $this->getJson('/api/v1/announcements/latest')->assertOk()->assertJsonPath('data.0.title', 'Water interruption')->assertJsonStructure(['data' => [['id', 'title', 'content', 'category', 'published_at']]]);
    }

    public function test_public_announcements_api_excludes_resident_only_and_expired_posts(): void
    {
        $admin = User::factory()->create();
        Announcement::query()->create(['title' => 'Resident only', 'content' => 'Private resident update.', 'category' => 'General', 'audience' => 'Residents', 'status' => 'Published', 'published_at' => now(), 'created_by' => $admin->id]);
        Announcement::query()->create(['title' => 'Expired', 'content' => 'Expired public update.', 'category' => 'General', 'audience' => 'Public', 'status' => 'Published', 'published_at' => now()->subDay(), 'expires_at' => now()->subMinute(), 'created_by' => $admin->id]);

        $this->getJson('/api/v1/announcements/latest')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_homeowner_cannot_access_filament_admin_panel(): void
    {
        $resident = User::factory()->create(['account_status' => 'Active']);
        $resident->assignRole('homeowner');

        $this->actingAs($resident)->get('/admin')->assertForbidden();
    }

    public function test_admin_can_access_filament_admin_panel(): void
    {
        $admin = User::factory()->create(['account_status' => 'Active']);
        $admin->assignRole('hoa_admin');

        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    public function test_staff_uses_the_staff_panel_and_cannot_access_admin(): void
    {
        $staff = User::factory()->create(['account_status' => 'Active']);
        $staff->assignRole('hoa_staff');

        $this->actingAs($staff)->get('/staff')->assertOk();
        $this->get('/admin')->assertForbidden();
        $this->get('/staff/homeowners')->assertOk();
        $this->get('/staff/announcements')->assertOk();
        $this->get('/staff/contact-messages')->assertOk();
        $this->get('/staff/users')->assertNotFound();
        $this->get('/staff/dues-settings')->assertOk();
        $this->get('/staff/dues-settings/create')->assertOk();
        $dues = DuesSetting::query()->create([
            'name' => 'Annual dues',
            'amount' => '1200.00',
            'frequency' => 'Annual',
            'is_active' => true,
        ]);
        $this->get("/staff/dues-settings/{$dues->id}/edit")->assertOk()->assertDontSee('Delete');
        $this->assertFalse($staff->can('delete', $dues));
        $this->get('/staff/complaints/create')->assertForbidden();
        $this->get('/staff/service-requests/create')->assertForbidden();
        $this->get('/admin/reports/payments.xlsx')->assertForbidden();
        $this->get('/staff/reports/payments.xlsx')->assertOk();
    }

    public function test_admin_cannot_access_the_staff_panel(): void
    {
        $admin = User::factory()->create(['account_status' => 'Active']);
        $admin->assignRole('hoa_admin');

        $this->actingAs($admin)->get('/staff')->assertForbidden();
        $this->get('/staff/reports/payments.xlsx')->assertForbidden();
    }

    public function test_inactive_staff_cannot_access_the_staff_panel(): void
    {
        $staff = User::factory()->create(['account_status' => 'Inactive']);
        $staff->assignRole('hoa_staff');

        $this->actingAs($staff)->get('/staff')->assertForbidden();
    }
}
