<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Users\CreatePendingUser;
use App\Filament\Resources\Homeowners\Pages\CreateHomeowner;
use App\Filament\Resources\Homeowners\Pages\EditHomeowner;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\DuesObligation;
use App\Models\DuesSetting;
use App\Models\Homeowner;
use App\Models\Household;
use App\Models\HouseholdMember;
use App\Models\User;
use App\Services\Reporting\HoaReportCatalog;
use App\Support\PropertySnapshot;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

final class HomeownerAddressFormsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Notification::fake();
    }

    #[DataProvider('panels')]
    public function test_shared_create_and_edit_forms_have_no_house_number(string $panel): void
    {
        $this->signIn($panel);
        $owner = $this->resident();

        Livewire::test(CreateHomeowner::class)
            ->assertFormFieldDoesNotExist('house_number')
            ->assertFormFieldDoesNotExist('street')
            ->assertFormFieldExists('block')
            ->assertFormFieldExists('lot')
            ->assertFormFieldExists('phase_suffix');

        $edit = Livewire::test(EditHomeowner::class, ['record' => $owner->getRouteKey()])
            ->assertFormFieldDoesNotExist('house_number')
            ->assertFormFieldExists('phase_suffix');

        if ($panel === 'staff') {
            $edit->assertFormFieldDoesNotExist('street')->assertFormFieldDisabled('block')->assertFormFieldDisabled('lot');
        }
    }

    #[DataProvider('storedAddresses')]
    public function test_edit_preserves_stored_address_membership_and_financial_snapshot(string $panel, ?string $house, ?string $suffix): void
    {
        $this->signIn($panel);
        $owner = $this->resident(['house_number' => $house, 'phase_suffix' => $suffix]);
        $member = HouseholdMember::where('current_homeowner_id', $owner->id)->sole();
        $membership = $member->getAttributes();
        $address = $owner->only(['id', 'user_id', 'house_number', 'street', 'block', 'lot', 'phase', 'phase_suffix', 'address_key', 'property_key']);
        $display = $owner->full_address;
        $setting = DuesSetting::create(['name' => 'Address preservation', 'amount' => '100.00', 'frequency' => 'Monthly', 'starts_on' => '2026-01-01', 'is_active' => true]);
        $obligation = DuesObligation::create([
            'homeowner_id' => $owner->id, 'dues_setting_id' => $setting->id,
            'billing_year' => now()->year, 'billing_month' => now()->month, 'due_date' => today(),
            'amount_due' => '100.00', 'penalty_amount' => '0.00', 'amount_paid' => '0.00', 'status' => 'Pending',
        ]);
        $snapshot = $obligation->property_snapshot;

        Livewire::test(EditHomeowner::class, ['record' => $owner->getRouteKey()])
            ->fillForm(['emergency_contact_name' => 'Updated Contact'])
            ->set('data.house_number', 'forged replacement')
            ->call('save')->assertHasNoFormErrors()->assertNotified('Homeowner Information Updated Successfully!');

        $owner->refresh();
        $this->assertSame('Updated Contact', $owner->emergency_contact_name);
        $this->assertSame($address, $owner->only(array_keys($address)));
        $this->assertSame($display, $owner->full_address);
        $this->assertSame($membership, $member->fresh()->getAttributes());
        $this->assertSame($snapshot, $obligation->fresh()->property_snapshot);
        $this->assertSame($owner->id, $obligation->fresh()->homeowner_id);
        $this->assertArrayNotHasKey('house_number', PropertySnapshot::from($owner));
        $row = app(HoaReportCatalog::class)->map('homeowners', $owner->load('user'));
        $this->assertSame($display, $row[3]);
        $this->assertDatabaseCount('households', 1);
        $this->assertDatabaseCount('household_members', 1);
    }

    public function test_update_handler_ignores_stale_and_crafted_house_number_values(): void
    {
        $this->signIn();
        $owner = $this->resident();
        $page = Livewire::test(EditHomeowner::class, ['record' => $owner->getRouteKey()])->instance();
        $update = new ReflectionMethod(EditHomeowner::class, 'handleRecordUpdate');

        foreach ([null, '', '99', ['99']] as $value) {
            $update->invoke($page, $owner->fresh(), [
                'house_number' => $value, 'user_email' => $owner->user->email,
                'user_contact_number' => $owner->user->contact_number,
                'emergency_contact_name' => 'Saved Contact',
            ]);
            $this->assertSame('12', $owner->fresh()->house_number);
        }
    }

    public function test_legacy_landlord_remains_selectable_and_linked_without_house_number_input(): void
    {
        $this->signIn();
        $landlord = $this->resident();
        $tenant = $this->resident(['ownership_type' => 'Tenant', 'landlord_homeowner_id' => $landlord->id]);
        $member = HouseholdMember::where('current_homeowner_id', $tenant->id)->sole();

        Livewire::test(EditHomeowner::class, ['record' => $tenant->getRouteKey()])
            ->assertFormFieldExists('landlord_homeowner_id', fn (Select $field): bool => array_key_exists($landlord->id, $field->getOptions()))
            ->fillForm(['emergency_contact_name' => 'Tenant Contact'])
            ->call('save')->assertHasNoFormErrors();

        $this->assertSame($landlord->id, $tenant->fresh()->landlord_homeowner_id);
        $this->assertSame('12', $tenant->fresh()->house_number);
        $this->assertSame($member->household_id, $member->fresh()->household_id);
        $this->assertSame($tenant->id, $member->fresh()->current_homeowner_id);
    }

    public function test_homeowners_create_saves_without_house_number_and_normalizes_address(): void
    {
        $this->signIn();
        $user = User::factory()->create();
        $user->assignRole('homeowner');

        Livewire::test(CreateHomeowner::class)
            ->fillForm(['user_id' => $user->id, ...$this->addressData(), 'street' => '  Test   Street ', 'block' => ' b ', 'lot' => ' l ', 'status' => 'Active'])
            ->set('data.house_number', 'stale value')
            ->call('create')->assertHasNoFormErrors()->assertNotified('Homeowner Created Successfully!');

        $owner = $user->homeowner()->sole();
        $this->assertNull($owner->house_number);
        $this->assertNull($owner->street);
        $this->assertSame('B', $owner->block);
        $this->assertSame('L', $owner->lot);
        $this->assertSame('A', $owner->phase_suffix);
        $this->assertNotNull(HouseholdMember::where('current_homeowner_id', $owner->id)->first());
    }

    #[DataProvider('invitationModes')]
    public function test_users_form_creates_pending_homeowner_without_house_number(bool $invite): void
    {
        $this->signIn();
        $data = $this->userData();
        Livewire::test(CreateUser::class)
            ->fillForm(['send_invitation' => $invite, ...$data])
            ->assertFormFieldDoesNotExist('house_number')
            ->call('create')->assertHasNoFormErrors();

        $user = User::where('email', $data['email'])->sole();
        $this->assertSame('Pending', $user->account_status);
        $this->assertTrue($user->hasRole('homeowner'));
        $this->assertNull($user->homeowner->house_number);
        $this->assertSame('Inactive', $user->homeowner->status);
        $this->assertSame('Southville Phase I', $user->homeowner->phase);
        $this->assertSame('A', $user->homeowner->phase_suffix);
    }

    public function test_users_action_rejects_nonempty_house_number_before_creating_records(): void
    {
        $admin = $this->signIn();
        foreach (['99', ['99']] as $value) {
            $data = [...$this->userData(), 'house_number' => $value];
            try {
                app(CreatePendingUser::class)->handle($data, $admin);
                $this->fail('A supplied House Number must be rejected.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('house_number', $exception->errors());
            }
            $this->assertDatabaseMissing('users', ['email' => $data['email']]);
        }
        $this->assertDatabaseCount('homeowners', 0);
    }

    public function test_archived_household_error_is_visible_and_creation_rolls_back(): void
    {
        $this->signIn();
        $this->resident(['house_number' => null, 'ownership_type' => 'Co-owner']);
        $household = Household::sole();
        $household->delete();
        $user = User::factory()->create();
        $user->assignRole('homeowner');

        Livewire::test(CreateHomeowner::class)
            ->fillForm(['user_id' => $user->id, ...$this->addressData(), 'ownership_type' => 'Co-owner', 'status' => 'Active'])
            ->call('create')->assertHasFormErrors(['block']);

        $this->assertDatabaseMissing('homeowners', ['user_id' => $user->id]);
        $this->assertDatabaseCount('homeowners', 1);
        $this->assertDatabaseCount('household_members', 1);
        $this->assertTrue($household->fresh()->trashed());
    }

    public function test_archived_household_error_on_edit_preserves_account_and_membership(): void
    {
        $this->signIn();
        $this->resident(['ownership_type' => 'Co-owner']);
        Household::sole()->delete();
        $owner = $this->resident(['ownership_type' => 'Co-owner', 'block' => '9']);
        $member = HouseholdMember::where('current_homeowner_id', $owner->id)->sole();
        $membership = $member->getAttributes();
        $email = $owner->user->email;

        Livewire::test(EditHomeowner::class, ['record' => $owner->getRouteKey()])
            ->fillForm(['block' => '7', 'user_email' => 'not-saved@example.test'])
            ->call('save')->assertHasFormErrors(['block']);

        $this->assertSame('9', $owner->fresh()->block);
        $this->assertSame($email, $owner->user->fresh()->email);
        $this->assertSame($membership, $member->fresh()->getAttributes());
    }

    public function test_users_action_accepts_empty_legacy_values_and_stores_null(): void
    {
        $admin = $this->signIn();
        foreach ([null, ''] as $index => $value) {
            $user = app(CreatePendingUser::class)->handle([
                ...$this->userData(), 'email' => 'empty-house-'.$index.'@example.test', 'house_number' => $value,
            ], $admin);
            $this->assertNull($user->homeowner->house_number);
            $this->assertSame('Pending', $user->account_status);
        }
    }

    public function test_homeowners_form_still_requires_phase_block_and_lot(): void
    {
        $this->signIn();
        Livewire::test(CreateHomeowner::class)
            ->fillForm(['street' => '', 'block' => '', 'lot' => ''])
            ->call('create')->assertHasFormErrors(['phase_suffix' => 'required', 'block' => 'required', 'lot' => 'required']);
    }

    public function test_unprivileged_homeowner_cannot_create_homeowners_or_users(): void
    {
        $owner = $this->resident();
        $this->actingAs($owner->user, 'admin')->get('/admin/homeowners/create')->assertForbidden();
        $this->actingAs($owner->user, 'admin')->get('/admin/users/create')->assertForbidden();
    }

    public static function panels(): array
    {
        return [['admin'], ['staff']];
    }

    public static function storedAddresses(): array
    {
        return [
            'admin legacy' => ['admin', '12', null],
            'admin confirmed null' => ['admin', null, 'A'],
            'staff legacy' => ['staff', '12', null],
            'staff confirmed null' => ['staff', null, 'A'],
        ];
    }

    public static function invitationModes(): array
    {
        return ['manual password' => [false], 'invitation' => [true]];
    }

    private function signIn(string $panel = 'admin'): User
    {
        $user = User::factory()->create();
        $user->assignRole($panel === 'staff' ? 'hoa_staff' : 'hoa_admin');
        Filament::setCurrentPanel(Filament::getPanel($panel));
        $this->actingAs($user, $panel);

        return $user;
    }

    private function resident(array $overrides = []): Homeowner
    {
        $user = User::factory()->create();
        $user->assignRole('homeowner');

        return $user->homeowner()->create(['house_number' => '12', ...$this->addressData(), 'status' => 'Active', ...$overrides]);
    }

    private function addressData(): array
    {
        return [
            'street' => 'Test Street', 'block' => '7', 'lot' => '8', 'phase' => 'Southville Phase I', 'phase_suffix' => 'A',
            'ownership_type' => 'Owner', 'residency_date' => '2020-01-01',
            'emergency_contact_name' => 'Contact Person', 'emergency_contact_number' => '09171234567',
        ];
    }

    private function userData(): array
    {
        return [
            'first_name' => 'Test', 'last_name' => 'Resident', 'sex' => 'Female',
            'date_of_birth' => '1990-01-01', 'contact_number' => '09171234567',
            'email' => 'new-homeowner@example.test', 'password' => 'ResidentPass!2026',
            'role' => 'homeowner', ...$this->addressData(),
        ];
    }
}
