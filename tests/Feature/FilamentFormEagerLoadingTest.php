<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FilamentFormEagerLoadingTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_create_forms_eager_load_homeowner_user_labels(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $admin = User::factory()->create(['account_status' => 'Active']);
        $admin->assignRole('hoa_admin');

        $resident = User::factory()->create(['account_status' => 'Active']);
        $resident->assignRole('homeowner');
        $resident->homeowner()->create([
            'house_number' => '12',
            'street' => 'Acacia Street',
            'block' => 'A',
            'lot' => '12',
            'phase' => 'Southville Phase I',
            'residency_date' => '2020-01-01',
            'ownership_type' => 'Owner',
            'status' => 'Active',
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/admin/certificates/create')
            ->assertOk()
            ->assertSeeText('Automatically set to the last day of the current year.');

        foreach (['complaints', 'service-requests'] as $resource) {
            $this->actingAs($admin, 'admin')
                ->get("/admin/{$resource}/create")
                ->assertOk();
        }
    }
}
