<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Homeowners\RegisterHomeowner;
use App\Models\Homeowner;
use App\Models\User;
use App\Services\PropertyAddressAvailability;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class RegistrationAddressAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_endpoint_rejects_a_normalized_owner_duplicate_without_exposing_account_data(): void
    {
        $this->homeowner();

        $response = $this->postJson(route('homeowner.register.address-availability'), [
            'house_number' => 'No. 12',
            'street' => ' narra st. ',
            'block' => 'Blk. 7',
            'lot' => 'Lot #8',
            'ownership_type' => 'Owner',
        ])->assertOk()->assertExactJson([
            'available' => false,
            'message' => PropertyAddressAvailability::UNAVAILABLE_MESSAGE,
        ]);

        $this->assertSame(['available', 'message'], array_keys($response->json()));
    }

    public function test_endpoint_allows_unique_owners_and_shared_tenants_or_coowners(): void
    {
        $this->homeowner();
        $shared = $this->addressPayload();

        foreach ([
            'Tenant' => PropertyAddressAvailability::TENANT_ALLOWED_MESSAGE,
            'Co-owner' => PropertyAddressAvailability::CO_OWNER_ALLOWED_MESSAGE,
        ] as $ownershipType => $message) {
            $this->postJson(route('homeowner.register.address-availability'), [
                ...$shared,
                'ownership_type' => $ownershipType,
            ])->assertExactJson([
                'available' => true,
                'message' => $message,
            ]);
        }

        $this->postJson(route('homeowner.register.address-availability'), [
            ...$shared,
            'lot' => '9',
            'ownership_type' => 'Owner',
        ])->assertExactJson([
            'available' => true,
            'message' => PropertyAddressAvailability::AVAILABLE_MESSAGE,
        ]);
    }

    public function test_endpoint_validates_input_and_is_throttled(): void
    {
        $this->postJson(route('homeowner.register.address-availability'), [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['house_number', 'street', 'block', 'lot', 'ownership_type']);

        $middleware = Route::getRoutes()->getByName('homeowner.register.address-availability')->gatherMiddleware();
        $this->assertContains('throttle:30,1', $middleware);
    }

    public function test_endpoint_enforces_its_request_limit(): void
    {
        $payload = [...$this->addressPayload(), 'ownership_type' => 'Tenant'];

        for ($request = 0; $request < 30; $request++) {
            $this->postJson(route('homeowner.register.address-availability'), $payload)->assertOk();
        }

        $this->postJson(route('homeowner.register.address-availability'), $payload)->assertTooManyRequests();
    }

    public function test_direct_owner_registration_is_rejected_without_partial_data_or_document(): void
    {
        Storage::fake('local');
        $this->homeowner();
        $payload = $this->registrationPayload('duplicate@example.test', 'Owner');
        $payload['verification_document'] = UploadedFile::fake()->create('proof.pdf', 50, 'application/pdf');

        try {
            app(RegisterHomeowner::class)->handle($payload);
            $this->fail('The duplicate owner address should have been rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('address', $exception->errors());
        }

        $this->assertDatabaseMissing('users', ['email' => 'duplicate@example.test']);
        $this->assertEmpty(Storage::disk('local')->allFiles('verification_documents'));
    }

    public function test_registration_request_cannot_bypass_the_duplicate_owner_rule(): void
    {
        $this->homeowner();
        $payload = $this->registrationPayload('request-duplicate@example.test', 'Owner') + [
            'password_confirmation' => 'ResidentPass!2026',
            'privacy_consent' => '1',
        ];

        $this->post(route('homeowner.register.store'), $payload)
            ->assertSessionHasErrors('address');

        $this->assertDatabaseMissing('users', ['email' => 'request-duplicate@example.test']);
    }

    public function test_tenant_and_coowner_can_register_at_an_existing_address(): void
    {
        [$landlord] = $this->homeowner();

        foreach (['Tenant', 'Co-owner'] as $index => $ownershipType) {
            $payload = $this->registrationPayload("shared{$index}@example.test", $ownershipType);
            if ($ownershipType === 'Tenant') {
                $payload += [
                    'landlord_name' => $landlord->full_name,
                    'landlord_contact' => '09181234567',
                    'lease_start_date' => '2026-01-01',
                    'lease_end_date' => '2027-01-01',
                ];
            }

            app(RegisterHomeowner::class)->handle($payload);
        }

        $this->assertSame(3, Homeowner::query()->count());
    }

    public function test_sequential_owner_claims_cannot_create_a_duplicate(): void
    {
        app(RegisterHomeowner::class)->handle($this->registrationPayload('first@example.test', 'Owner'));

        $this->expectException(ValidationException::class);
        app(RegisterHomeowner::class)->handle($this->registrationPayload('second@example.test', 'Owner'));
    }

    public function test_deleted_or_moved_records_release_the_old_address(): void
    {
        [, $homeowner] = $this->homeowner();
        $availability = app(PropertyAddressAvailability::class);
        $ownerAddress = [...$this->addressPayload(), 'ownership_type' => 'Owner'];

        $this->assertFalse($availability->available($ownerAddress));
        $homeowner->update(['lot' => '99']);
        $this->assertTrue($availability->available($ownerAddress));

        $homeowner->update(['lot' => '8']);
        $homeowner->delete();
        $this->assertTrue($availability->available($ownerAddress));
    }

    public function test_migration_backfills_existing_duplicate_shared_addresses_without_merging_them(): void
    {
        [, $first] = $this->homeowner('Tenant');
        [, $second] = $this->homeowner('Tenant');
        $migration = require database_path('migrations/2026_10_17_000002_add_property_address_validation.php');

        $migration->down();
        $migration->up();

        $this->assertSame(2, Homeowner::query()->count());
        $this->assertSame(
            $first->fresh()->address_key,
            $second->fresh()->address_key,
        );
    }

    /** @return array{User, Homeowner} */
    private function homeowner(string $ownershipType = 'Owner'): array
    {
        $user = User::factory()->create();
        $user->assignRole('homeowner');
        $homeowner = $user->homeowner()->create([
            ...$this->addressPayload(),
            'phase' => 'Southville Phase I',
            'residency_date' => '2020-01-01',
            'ownership_type' => $ownershipType,
            'status' => 'Active',
        ]);

        return [$user, $homeowner];
    }

    /** @return array<string, string> */
    private function addressPayload(): array
    {
        return ['house_number' => '12', 'street' => 'Narra Street', 'block' => '7', 'lot' => '8'];
    }

    /** @return array<string, mixed> */
    private function registrationPayload(string $email, string $ownershipType): array
    {
        return [
            'first_name' => 'Test',
            'last_name' => 'Resident',
            'sex' => 'Prefer not to say',
            'contact_number' => '09171234567',
            'date_of_birth' => '1990-01-01',
            'email' => $email,
            'password' => 'ResidentPass!2026',
            ...$this->addressPayload(),
            'phase' => 'Southville Phase I',
            'ownership_type' => $ownershipType,
            'residency_date' => '2020-01-01',
        ];
    }
}
