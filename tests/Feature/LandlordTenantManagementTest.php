<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Homeowners\Pages\EditHomeowner;
use App\Filament\Resources\Homeowners\Pages\ViewHomeowner;
use App\Filament\Resources\Homeowners\RelationManagers\TenantsRelationManager;
use App\Models\AuditLog;
use App\Models\Homeowner;
use App\Models\User;
use App\Services\LandlordMatcher;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

final class LandlordTenantManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_endpoint_matches_one_active_owner_at_the_same_normalized_address(): void
    {
        [$landlord] = $this->resident('Owner', [], ['first_name' => 'Juan', 'middle_name' => null, 'last_name' => 'Dela Cruz', 'suffix' => 'Jr.']);

        $this->postJson(route('homeowner.register.landlord-availability'), [
            ...$this->address(['street' => 'Narra St.', 'block' => 'Block 7', 'lot' => 'Lot 8', 'house_number' => 'No. 12']),
            'landlord_name' => '  JUAN   DELA-CRUZ JR. ',
        ])->assertExactJson([
            'valid' => true,
            'message' => LandlordMatcher::VALID_MESSAGE,
        ]);

        $this->assertSame('Juan Dela Cruz Jr.', $landlord->full_name);
    }

    public function test_endpoint_rejects_wrong_address_unknown_or_ineligible_landlords(): void
    {
        $this->resident('Owner', [], ['first_name' => 'Valid', 'middle_name' => null, 'last_name' => 'Owner']);
        $this->resident('Tenant', [], ['first_name' => 'Tenant', 'middle_name' => null, 'last_name' => 'Person']);
        $this->resident('Owner', ['status' => 'Inactive'], ['first_name' => 'Inactive', 'middle_name' => null, 'last_name' => 'Owner', 'account_status' => 'Inactive']);
        [, $pending] = $this->resident('Owner', [], ['first_name' => 'Pending', 'middle_name' => null, 'last_name' => 'Owner', 'account_status' => 'Pending']);
        [, $deleted] = $this->resident('Owner', [], ['first_name' => 'Deleted', 'middle_name' => null, 'last_name' => 'Owner']);
        $deleted->delete();

        foreach ([
            ['landlord_name' => 'Valid Owner', 'lot' => '99'],
            ['landlord_name' => 'Unknown Person'],
            ['landlord_name' => 'Tenant Person'],
            ['landlord_name' => 'Inactive Owner'],
            ['landlord_name' => 'Pending Owner'],
            ['landlord_name' => 'Deleted Owner'],
        ] as $overrides) {
            $this->postJson(route('homeowner.register.landlord-availability'), [
                ...$this->address($overrides),
                'landlord_name' => $overrides['landlord_name'],
            ])->assertExactJson([
                'valid' => false,
                'message' => LandlordMatcher::NOT_FOUND_MESSAGE,
            ]);
        }
    }

    public function test_endpoint_rejects_ambiguous_matches_without_exposing_records(): void
    {
        foreach (['Owner', 'Co-owner'] as $ownershipType) {
            $this->resident($ownershipType, [], [
                'first_name' => 'Same', 'middle_name' => null, 'last_name' => 'Name', 'suffix' => null,
            ]);
        }

        $this->postJson(route('homeowner.register.landlord-availability'), [
            ...$this->address(),
            'landlord_name' => 'Same Name',
        ])->assertExactJson([
            'valid' => false,
            'message' => LandlordMatcher::AMBIGUOUS_MESSAGE,
        ]);
    }

    public function test_endpoint_validates_input_and_has_rate_limiting(): void
    {
        $this->postJson(route('homeowner.register.landlord-availability'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['landlord_name', 'house_number', 'street', 'block', 'lot']);

        $middleware = Route::getRoutes()->getByName('homeowner.register.landlord-availability')->gatherMiddleware();
        $this->assertContains('throttle:30,1', $middleware);
    }

    public function test_endpoint_enforces_its_request_limit(): void
    {
        $payload = [...$this->address(), 'landlord_name' => 'Unknown Owner'];

        for ($request = 0; $request < 30; $request++) {
            $this->postJson(route('homeowner.register.landlord-availability'), $payload)->assertOk();
        }

        $this->postJson(route('homeowner.register.landlord-availability'), $payload)->assertTooManyRequests();
    }

    public function test_tenant_registration_persists_the_landlord_relationship(): void
    {
        [$landlord, $landlordRecord] = $this->resident('Owner', [], [
            'first_name' => 'Property', 'middle_name' => null, 'last_name' => 'Owner', 'suffix' => null,
        ]);

        $this->post(route('homeowner.register.store'), $this->registrationPayload($landlord->full_name))
            ->assertRedirect();

        $tenant = Homeowner::query()->where('ownership_type', 'Tenant')->firstOrFail();
        $this->assertSame($landlordRecord->id, $tenant->landlord_homeowner_id);
        $this->assertSame($landlord->full_name, $tenant->landlord_name);
    }

    public function test_registration_rejects_an_unmatched_landlord_without_partial_records(): void
    {
        $this->post(route('homeowner.register.store'), $this->registrationPayload('Unknown Landlord'))
            ->assertSessionHasErrors('landlord_name');

        $this->assertDatabaseMissing('users', ['email' => 'linked-tenant@example.test']);
    }

    public function test_relationship_rules_prevent_invalid_links_and_address_changes(): void
    {
        [, $landlord] = $this->resident('Owner');
        [, $tenant] = $this->resident('Tenant');
        $tenant->update(['landlord_homeowner_id' => $landlord->id]);
        $this->assertSame($landlord->id, $tenant->refresh()->landlord_homeowner_id);

        $this->expectException(ValidationException::class);
        $landlord->update(['lot' => '99']);
    }

    public function test_owner_sees_only_linked_tenants_while_admin_and_staff_can_manage_records(): void
    {
        [$landlordUser, $landlord] = $this->resident('Owner', [], ['email' => 'landlord@example.test']);
        [$tenantUser, $tenant] = $this->resident('Tenant', [
            'landlord_homeowner_id' => $landlord->id,
            'lease_start_date' => '2026-01-01',
            'lease_end_date' => '2027-01-01',
        ], ['first_name' => 'Linked', 'middle_name' => null, 'last_name' => 'Tenant', 'email' => 'private-tenant@example.test']);
        [$otherTenant] = $this->resident('Tenant', [], [
            'first_name' => 'Unlinked', 'middle_name' => null, 'last_name' => 'Tenant', 'email' => 'unlinked-tenant@example.test',
        ]);
        $admin = User::factory()->create();
        $admin->assignRole('hoa_admin');
        $staff = User::factory()->create();
        $staff->assignRole('hoa_staff');

        $this->actingAs($landlordUser, 'homeowner')->get('/homeowner/my-profile')
            ->assertOk()
            ->assertSee('My tenants')
            ->assertSee('Linked Tenant')
            ->assertSee($tenantUser->contact_number)
            ->assertDontSee('private-tenant@example.test')
            ->assertDontSee('Unlinked Tenant');

        $this->actingAs($admin, 'admin')->get("/admin/homeowners/{$landlord->id}")
            ->assertOk()
            ->assertSee('Tenants');

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin, 'admin');
        Livewire::test(TenantsRelationManager::class, [
            'ownerRecord' => $landlord,
            'pageClass' => ViewHomeowner::class,
        ])->assertCanSeeTableRecords([$tenant]);

        $this->actingAs($staff, 'staff')->get("/staff/homeowners/{$tenant->id}/edit")
            ->assertOk()
            ->assertSee('Registered landlord');

        Filament::setCurrentPanel(Filament::getPanel('staff'));
        $this->actingAs($staff, 'staff');
        Livewire::test(EditHomeowner::class, ['record' => $tenant->getRouteKey()])
            ->fillForm([
                'landlord_homeowner_id' => null,
                'lease_end_date' => '2027-06-01',
                'emergency_contact_name' => 'Emergency Contact',
                'emergency_contact_number' => '09190000000',
            ])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertNull($tenant->refresh()->landlord_homeowner_id);
        $this->assertSame('2027-06-01', $tenant->lease_end_date?->toDateString());

        Livewire::test(EditHomeowner::class, ['record' => $tenant->getRouteKey()])
            ->fillForm(['landlord_homeowner_id' => $landlord->id])
            ->call('save')
            ->assertHasNoFormErrors();
        $this->assertSame($landlord->id, $tenant->refresh()->landlord_homeowner_id);

        $audit = AuditLog::query()
            ->where('user_id', $staff->id)
            ->where('panel', 'staff')
            ->where('auditable_type', Homeowner::class)
            ->where('auditable_id', $tenant->id)
            ->latest('id')
            ->firstOrFail();
        $this->assertArrayHasKey('landlord_homeowner_id', $audit->new_values);

        $this->assertSame('Unlinked Tenant', $otherTenant->full_name);
    }

    public function test_migration_backfills_unique_matches_and_preserves_unmatched_tenants(): void
    {
        [$owner, $ownerRecord] = $this->resident('Owner', [], [
            'first_name' => 'Legacy', 'middle_name' => null, 'last_name' => 'Owner', 'suffix' => null,
        ]);
        [, $matched] = $this->resident('Tenant', ['landlord_name' => $owner->full_name]);
        [, $unmatched] = $this->resident('Tenant', ['landlord_name' => 'Missing Owner'], ['email' => 'unmatched@example.test']);
        $migration = require database_path('migrations/2026_10_18_000001_link_tenants_to_landlords.php');

        $migration->down();
        $migration->up();

        $this->assertSame($ownerRecord->id, $matched->fresh()->landlord_homeowner_id);
        $this->assertNull($unmatched->fresh()->landlord_homeowner_id);
        $this->assertSame('Missing Owner', $unmatched->fresh()->landlord_name);
    }

    /** @param array<string, mixed> $homeownerOverrides @param array<string, mixed> $userOverrides @return array{User, Homeowner} */
    private function resident(string $ownershipType, array $homeownerOverrides = [], array $userOverrides = []): array
    {
        $user = User::factory()->create($userOverrides);
        $user->assignRole('homeowner');
        $homeowner = $user->homeowner()->create([
            ...$this->address(),
            'phase' => 'Southville Phase I',
            'residency_date' => '2020-01-01',
            'ownership_type' => $ownershipType,
            'status' => 'Active',
            ...$homeownerOverrides,
        ]);

        return [$user, $homeowner];
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function address(array $overrides = []): array
    {
        return ['house_number' => '12', 'street' => 'Narra Street', 'block' => '7', 'lot' => '8', ...$overrides];
    }

    /** @return array<string, mixed> */
    private function registrationPayload(string $landlordName): array
    {
        return [
            'first_name' => 'Linked', 'last_name' => 'Tenant', 'sex' => 'Male',
            'date_of_birth' => '1990-01-01', 'contact_number' => '09171234567',
            'email' => 'linked-tenant@example.test', 'password' => 'ResidentPass!2026',
            'password_confirmation' => 'ResidentPass!2026', ...$this->address(),
            'ownership_type' => 'Tenant', 'landlord_name' => $landlordName,
            'landlord_contact' => '09181234567', 'lease_start_date' => '2026-01-01',
            'lease_end_date' => '2027-01-01', 'privacy_consent' => '1',
        ];
    }
}
