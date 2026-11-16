<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Households\ManageHousehold;
use App\Filament\Pages\Households;
use App\Models\Household;
use App\Models\User;
use App\Services\Reporting\HoaReportCatalog;
use App\Support\CurrentPropertyAddress;
use App\Support\PropertyAddress;
use App\Support\PropertyIdentity;
use App\Support\PropertySnapshot;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

final class HouseNumberRetirementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();
        Queue::fake();
    }

    private function actor(string $panel = 'admin'): User
    {
        $actor = User::factory()->create();
        $actor->assignRole($panel === 'admin' ? 'hoa_admin' : 'hoa_staff');
        Filament::setCurrentPanel($panel);
        $this->actingAs($actor, $panel);

        return $actor;
    }

    private function legacy(?string $suffix = null): Household
    {
        $address = ['house_number' => 'LEGACY-HOUSE-991', 'street' => 'Historical Street', 'block' => '7', 'lot' => (string) (8 + Household::count()), 'phase' => 'Southville Phase I', 'phase_suffix' => $suffix];

        return Household::create([...$address, 'identifier' => (string) Str::uuid(), 'address_key' => PropertyAddress::key($address), 'needs_review' => true]);
    }

    public function test_edits_preserve_legacy_values_identity_head_and_memberships_even_with_crafted_input(): void
    {
        $actor = $this->actor();
        foreach ([null, 'A'] as $suffix) {
            $household = $this->legacy($suffix);
            $head = $household->members()->create(['full_name' => 'Legacy Head', 'relationship' => 'Head', 'residency_status' => 'Resident']);
            $household->update(['head_member_id' => $head->id]);
            $before = $household->only(['house_number', 'street', 'address_key', 'property_key', 'head_member_id', 'needs_review']);
            $membership = $head->fresh()->getAttributes();
            foreach ([null, '', 'REPLACEMENT', ['forged']] as $value) {
                app(ManageHousehold::class)->save($household->id, [...$household->only(['block', 'lot']), 'house_number' => $value, 'occupancy' => 'Updated'], $actor);
                $this->assertSame($before, $household->fresh()->only(array_keys($before)));
                $this->assertSame($membership, $head->fresh()->getAttributes());
            }
        }
    }

    public function test_new_households_store_null_and_reject_nonempty_retired_input(): void
    {
        $actor = $this->actor();
        foreach (['99', ['99']] as $value) {
            try {
                app(ManageHousehold::class)->save(null, ['house_number' => $value, 'block' => '7', 'lot' => '8', 'phase_suffix' => 'A'], $actor);
                $this->fail('Retired input accepted.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('house_number', $exception->errors());
            }
        }
        $this->assertDatabaseCount('households', 0);
        $household = app(ManageHousehold::class)->save(null, ['block' => '7', 'lot' => '8', 'phase_suffix' => 'A'], $actor);
        $this->assertNull($household->house_number);
    }

    public function test_property_move_errors_target_visible_block_field_without_writing(): void
    {
        $actor = $this->actor();
        $household = $this->legacy();
        try {
            app(ManageHousehold::class)->save($household->id, ['block' => 'NEW', 'lot' => '8'], $actor);
            $this->fail('Property move accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('block', $exception->errors());
            $this->assertArrayNotHasKey('house_number', $exception->errors());
        }
        $this->assertSame('7', $household->fresh()->block);
    }

    public function test_current_presentation_and_new_snapshots_hide_house_without_rekeying_history(): void
    {
        $household = $this->legacy();
        $before = $household->fresh()->getAttributes();
        $snapshot = PropertySnapshot::from($household);
        $this->assertArrayNotHasKey('house_number', $snapshot);
        $this->assertArrayNotHasKey('street', $snapshot);
        $this->assertSame(PropertyIdentity::key($household), $snapshot['property_key']);
        $this->assertStringNotContainsString('LEGACY-HOUSE-991', CurrentPropertyAddress::display($household));
        $historical = ['house_number' => 'OLD-HOUSE', 'street' => 'Old Street', 'block' => '7', 'lot' => '8'];
        $this->assertStringContainsString('OLD-HOUSE', PropertySnapshot::display($historical));
        $this->assertSame('Original archived address', PropertySnapshot::display(['display_address' => 'Original archived address']));
        $this->assertSame($before, $household->fresh()->getAttributes());
    }

    public function test_legacy_membership_key_survives_when_house_number_is_not_copied_to_new_household(): void
    {
        $actor = $this->actor();
        $household = $this->legacy();
        $key = $household->address_key;
        $household->update(['house_number' => null]);
        app(ManageHousehold::class)->save($household->id, ['block' => '7', 'lot' => '8', 'occupancy' => 'Reviewed'], $actor);
        $this->assertSame($key, $household->fresh()->address_key);
        $this->assertNull($household->fresh()->house_number);
    }

    public function test_linked_legacy_owner_remains_selectable_when_new_household_has_null_house_number(): void
    {
        $actor = $this->actor();
        $resident = User::factory()->create();
        $resident->assignRole('homeowner');
        $owner = $resident->homeowner()->create(['house_number' => 'OLD-LEGACY-44', 'street' => 'Legacy Street', 'block' => '7', 'lot' => '8', 'phase' => 'Southville Phase I',
            'ownership_type' => 'Owner', 'status' => 'Active', 'residency_date' => '2020-01-01']);
        $household = Household::sole();
        $this->assertNull($household->house_number);
        $this->assertSame($owner->address_key, $household->address_key);
        app(ManageHousehold::class)->save($household->id, ['block' => '7', 'lot' => '8', 'registered_homeowner_id' => $owner->id], $actor);
        $this->assertSame($owner->id, $household->fresh()->registered_homeowner_id);
        $this->assertSame($owner->address_key, $household->fresh()->address_key);
    }

    public function test_admin_and_staff_dialogs_can_save_without_the_retired_field(): void
    {
        $household = $this->legacy('A');
        foreach (['admin', 'staff'] as $panel) {
            $this->actor($panel);
            Livewire::test(Households::class)->mountTableAction('editHousehold', $household)
                ->assertSee('Block')->assertDontSee('House number')->assertDontSee('House Number');
            app(ManageHousehold::class)->save($household->id, ['block' => '7', 'lot' => '8', 'occupancy' => $panel], Filament::auth()->user());
            $this->assertSame('LEGACY-HOUSE-991', $household->fresh()->house_number);
        }
    }

    public function test_report_search_ignores_retired_house_number_and_keeps_current_block_search(): void
    {
        $this->actor();
        $resident = User::factory()->create();
        $resident->assignRole('homeowner');
        $owner = $resident->homeowner()->create(['house_number' => 'UNIQUE-HOUSE-RETIRED', 'block' => 'BSEARCH', 'lot' => 'LSEARCH', 'phase_suffix' => 'A', 'phase' => 'Southville Phase I', 'ownership_type' => 'Owner', 'status' => 'Active', 'residency_date' => '2020-01-01']);
        $catalog = app(HoaReportCatalog::class);
        $filters = $catalog->validateFilters('homeowners', ['q' => 'UNIQUE-HOUSE-RETIRED'], true);
        $this->assertFalse($catalog->query('homeowners', $filters)->whereKey($owner->id)->exists());
        $filters = $catalog->validateFilters('homeowners', ['q' => 'BSEARCH'], true);
        $this->assertTrue($catalog->query('homeowners', $filters)->whereKey($owner->id)->exists());
    }
}
