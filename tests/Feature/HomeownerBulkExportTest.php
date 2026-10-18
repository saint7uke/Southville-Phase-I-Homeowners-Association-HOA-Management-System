<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Homeowners\Pages\ListHomeowners;
use App\Models\AuditLog;
use App\Models\Homeowner;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class HomeownerBulkExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_export_selected_homeowners(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('hoa_admin');
        $homeowner = $this->createHomeowner();

        Filament::setCurrentPanel('admin');
        Livewire::actingAs($admin, 'admin')
            ->test(ListHomeowners::class)
            ->callTableBulkAction('exportSelected', [$homeowner]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'panel' => 'admin',
            'action' => 'Homeowner.selected_exported',
        ]);
    }

    public function test_homeowner_cannot_use_the_admin_bulk_export(): void
    {
        $homeownerUser = User::factory()->create();
        $homeownerUser->assignRole('homeowner');
        $homeowner = $this->createHomeowner($homeownerUser);

        $this->actingAs($homeownerUser, 'homeowner')
            ->get('/admin/homeowners')
            ->assertForbidden();
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'Homeowner.selected_exported',
        ]);
    }

    private function createHomeowner(?User $user = null): Homeowner
    {
        $user ??= User::factory()->create();
        $user->assignRole('homeowner');

        return $user->homeowner()->create([
            'house_number' => '1',
            'street' => 'Narra Street',
            'phase' => 'Southville Phase I',
            'residency_date' => '2021-01-01',
            'ownership_type' => 'Owner',
            'status' => 'Active',
        ]);
    }
}
