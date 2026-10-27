<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Homeowners\UpdateOwnProfile;
use App\Filament\Homeowner\Pages\MyProfile;
use App\Models\Homeowner;
use App\Models\User;
use App\Models\UserEmailReservation;
use App\Validation\HomeownerProfileValidator;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

final class HomeownerProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_new_resident_without_house_number_can_save_and_refresh_profile(): void
    {
        [$resident, $homeowner] = $this->homeowner();
        $homeowner->update(['house_number' => null, 'phase_suffix' => 'A']);
        Filament::setCurrentPanel(Filament::getPanel('homeowner'));
        Livewire::actingAs($resident->fresh(), 'homeowner')->test(MyProfile::class)
            ->fillForm($this->profilePayload(['email' => $resident->email]), 'profileForm')
            ->call('saveProfile')->assertHasNoFormErrors()->assertNotified('Profile updated successfully!');
        $this->assertSame('A', $homeowner->fresh()->phase_suffix);
        $this->assertEmpty($homeowner->fresh()->house_number);
    }

    public function test_filament_profile_saves_completed_phone_fields_and_persists_after_remount(): void
    {
        [$resident] = $this->homeowner();
        Filament::setCurrentPanel(Filament::getPanel('homeowner'));
        Livewire::actingAs($resident, 'homeowner')
            ->test(MyProfile::class)
            ->fillForm($this->profilePayload([
                'email' => $resident->email,
                'contact_number' => '0917-123-4567',
                'emergency_contact_number' => '0998 765 4321',
            ]), 'profileForm')
            ->call('saveProfile')
            ->assertHasNoFormErrors()
            ->assertNotified('Profile updated successfully!');

        $this->assertSame('09171234567', $resident->fresh()->contact_number);
        $this->assertSame('09987654321', $resident->homeowner()->first()->emergency_contact_number);
        Livewire::actingAs($resident->fresh(), 'homeowner')->test(MyProfile::class)
            ->assertFormSet(['contact_number' => '09171234567', 'emergency_contact_number' => '09987654321'], 'profileForm');
    }

    public function test_filament_missing_phone_keeps_entered_values_and_does_not_write_partial_changes(): void
    {
        [$resident] = $this->homeowner();
        $originalName = $resident->first_name;
        Filament::setCurrentPanel(Filament::getPanel('homeowner'));
        Livewire::actingAs($resident, 'homeowner')->test(MyProfile::class)
            ->fillForm($this->profilePayload([
                'email' => $resident->email, 'first_name' => 'Retained',
                'contact_number' => '', 'emergency_contact_name' => 'Retained Contact',
            ]), 'profileForm')
            ->call('saveProfile')
            ->assertHasFormErrors(['contact_number' => 'required'], 'profileForm')
            ->assertFormSet(['first_name' => 'Retained', 'emergency_contact_name' => 'Retained Contact'], 'profileForm');
        $this->assertSame($originalName, $resident->fresh()->first_name);
    }

    public function test_legacy_missing_phase_does_not_block_personal_details_or_rewrite_the_phase(): void
    {
        [$resident, $homeowner] = $this->homeowner([], ['phase' => null]);
        Filament::setCurrentPanel(Filament::getPanel('homeowner'));
        Livewire::actingAs($resident, 'homeowner')->test(MyProfile::class)
            ->fillForm(['contact_number' => '09171234567'], 'profileForm')
            ->call('saveProfile')
            ->assertHasNoFormErrors()
            ->assertNotified('Profile updated successfully!');
        $this->assertNull($homeowner->fresh()->phase);
        $this->assertSame('09171234567', $resident->fresh()->contact_number);
    }

    public function test_portal_owner_updates_a_complete_normalized_profile_and_reverifies_changed_email(): void
    {
        Notification::fake();
        [$resident, $homeowner] = $this->homeowner();

        $this->actingAs($resident, 'web')
            ->patch('/portal/profile', $this->profilePayload([
                'first_name' => '  maría   de la cruz ',
                'email' => ' NEW.EMAIL@EXAMPLE.TEST ',
            ]))
            ->assertRedirect('/portal/status');

        $resident->refresh();
        $homeowner->refresh();
        $this->assertSame('María De La Cruz', $resident->first_name);
        $this->assertSame('new.email@example.test', $resident->email);
        $this->assertNull($resident->email_verified_at);
        $this->assertSame('B 1', $homeowner->block);
        $this->assertSame('L 1', $homeowner->lot);
        $this->assertSame('Southville Phase I', $homeowner->phase);
        $this->assertSame(36, $resident->age);
        Notification::assertSentTo($resident, VerifyEmail::class);
    }

    public function test_case_equivalent_email_does_not_clear_verification_or_send_notification(): void
    {
        Notification::fake();
        [$resident] = $this->homeowner(['email' => 'resident@example.test']);
        $verifiedAt = $resident->email_verified_at;

        app(UpdateOwnProfile::class)->handle($resident, $this->profilePayload([
            'email' => ' RESIDENT@EXAMPLE.TEST ',
        ]));

        $this->assertTrue($resident->refresh()->email_verified_at?->equalTo($verifiedAt));
        Notification::assertNothingSent();
    }

    public function test_profile_normalizes_formatted_phones_and_rejects_letters(): void
    {
        [$resident] = $this->homeowner();

        app(UpdateOwnProfile::class)->handle($resident, $this->profilePayload([
            'contact_number' => '0917-123-4567',
            'emergency_contact_number' => '0998 765 4321',
        ]));

        $this->assertSame('09171234567', $resident->refresh()->contact_number);
        $this->assertSame('09987654321', $resident->homeowner->emergency_contact_number);

        try {
            app(UpdateOwnProfile::class)->handle($resident, $this->profilePayload([
                'contact_number' => 'call 09171234567',
            ]));
            $this->fail('Letters in a mobile number should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('contact_number', $exception->errors());
        }
    }

    public function test_email_availability_allows_own_history_and_rejects_other_reservations_privately(): void
    {
        [$resident] = $this->homeowner();
        [$other] = $this->homeowner();
        UserEmailReservation::query()->create(['email' => 'my-old@example.test', 'user_id' => $resident->id]);
        UserEmailReservation::query()->create(['email' => 'other-old@example.test', 'user_id' => $other->id]);

        $this->actingAs($resident, 'homeowner')
            ->postJson(route('homeowner.profile.availability'), ['field' => 'email', 'email' => ' MY-OLD@EXAMPLE.TEST '])
            ->assertExactJson(['valid' => true, 'message' => HomeownerProfileValidator::EMAIL_AVAILABLE_MESSAGE]);

        $response = $this->postJson(route('homeowner.profile.availability'), [
            'field' => 'email',
            'email' => 'other-old@example.test',
        ])->assertOk()->assertExactJson([
            'valid' => false,
            'message' => HomeownerProfileValidator::EMAIL_UNAVAILABLE_MESSAGE,
        ]);

        $this->assertArrayNotHasKey('id', $response->json());
        $this->assertStringNotContainsString($other->full_name, $response->getContent());
    }

    public function test_profile_availability_rejects_address_checks_because_the_address_is_hoa_controlled(): void
    {
        [$existing, $existingHomeowner] = $this->homeowner([], ['block' => 'B 20', 'lot' => 'L 20', 'phase_suffix' => 'A']);
        [$resident] = $this->homeowner([], ['block' => 'B 21', 'lot' => 'L 21', 'phase_suffix' => 'A']);

        $this->actingAs($resident, 'homeowner')->postJson(route('homeowner.profile.availability'), [
            'field' => 'address',
            'street' => $existingHomeowner->street,
            'block' => $existingHomeowner->block,
            'lot' => $existingHomeowner->lot,
        ])->assertUnprocessable()->assertExactJson([
            'valid' => false,
            'message' => 'The selected field is invalid.',
        ]);

        $this->assertSame($existing->id, $existingHomeowner->user_id);
    }

    public function test_profile_availability_requires_homeowner_authentication_and_is_throttled(): void
    {
        $this->postJson(route('homeowner.profile.availability'), ['field' => 'email', 'email' => 'new@example.test'])
            ->assertUnauthorized();

        $middleware = Route::getRoutes()->getByName('homeowner.profile.availability')->gatherMiddleware();
        $this->assertContains('auth:homeowner', $middleware);
        $this->assertContains('throttle:30,1', $middleware);
    }

    public function test_profile_availability_enforces_its_request_limit(): void
    {
        [$resident] = $this->homeowner();
        $this->actingAs($resident, 'homeowner');

        for ($request = 0; $request < 30; $request++) {
            $this->postJson(route('homeowner.profile.availability'), [
                'field' => 'email',
                'email' => "available-{$request}@example.test",
            ])->assertOk();
        }

        $this->postJson(route('homeowner.profile.availability'), [
            'field' => 'email',
            'email' => 'rate-limited@example.test',
        ])->assertTooManyRequests();
    }

    public function test_homeowner_cannot_forge_phase_or_occupancy_changes(): void
    {
        [$resident] = $this->homeowner();

        foreach ([
            ['phase' => 'Another Phase'],
            ['ownership_type' => 'Tenant'],
            ['street' => 'Another Street'],
            ['block' => '99'],
            ['lot' => '99'],
        ] as $override) {
            try {
                app(UpdateOwnProfile::class)->handle($resident, $this->profilePayload($override));
                $this->fail('HOA-controlled profile fields should not be homeowner-editable.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey(array_key_first($override), $exception->errors());
            }
        }
    }

    public function test_homeowner_cannot_change_the_stored_house_number_through_a_crafted_request(): void
    {
        [$resident, $homeowner] = $this->homeowner();

        try {
            app(UpdateOwnProfile::class)->handle($resident, [
                ...$this->profilePayload(),
                'house_number' => '99',
            ]);
            $this->fail('The removed house-number field must not remain writable.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('house_number', $exception->errors());
        }

        $this->assertNotSame('99', $homeowner->fresh()->house_number);
    }

    public function test_move_in_date_cannot_precede_birth_date(): void
    {
        [$resident] = $this->homeowner();

        try {
            app(UpdateOwnProfile::class)->handle($resident, $this->profilePayload([
                'date_of_birth' => '2000-01-01',
                'residency_date' => '1999-12-31',
            ]));
            $this->fail('A move-in date before birth should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('residency_date', $exception->errors());
        }
    }

    public function test_shared_profile_validator_rejects_invalid_values_for_every_editable_text_and_date_field(): void
    {
        [$resident, $homeowner] = $this->homeowner();
        $validator = app(HomeownerProfileValidator::class);
        $invalidValues = [
            'first_name' => '123',
            'middle_name' => '456',
            'last_name' => '<script>',
            'suffix' => 'V',
            'sex' => 'Unknown',
            'date_of_birth' => today()->toDateString(),
            'contact_number' => '08123456789',
            'email' => 'not-an-email',
            'residency_date' => today()->addDay()->toDateString(),
            'emergency_contact_name' => '987',
            'emergency_contact_number' => '09123',
        ];

        foreach ($invalidValues as $field => $value) {
            try {
                $validator->validate($resident, $homeowner, $this->profilePayload([$field => $value]));
                $this->fail("The {$field} field should reject its invalid value.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey($field, $exception->errors());
            }
        }
    }

    public function test_unverified_portal_homeowner_can_resend_and_complete_email_verification(): void
    {
        Notification::fake();
        [$resident] = $this->homeowner(['email_verified_at' => null]);

        $this->actingAs($resident, 'web')
            ->withSession(['auth.password_hash_web' => $resident->password])
            ->from('/portal/status')
            ->post('/portal/status/resend-verification')
            ->assertRedirect('/portal/status');
        Notification::assertSentTo($resident, VerifyEmail::class);

        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(30), [
            'id' => $resident->id,
            'hash' => sha1($resident->getEmailForVerification()),
        ]);

        $this->get($url)->assertRedirect('/portal/dashboard');
        $this->assertNotNull($resident->refresh()->email_verified_at);
    }

    public function test_owner_cannot_move_to_another_current_accounts_full_normalized_address(): void
    {
        [, $existing] = $this->homeowner([], [
            'block' => 'B 1',
            'lot' => 'L 2',
            'phase_suffix' => 'A',
        ]);
        [$resident] = $this->homeowner([], [
            'block' => 'B 9',
            'lot' => 'L 9',
            'phase_suffix' => 'A',
        ]);

        try {
            app(UpdateOwnProfile::class)->handle($resident, $this->profilePayload([
                'street' => ' mahogany st. ',
                'block' => ' b   1 ',
                'lot' => ' l 2 ',
                'phase' => $existing->phase,
            ]));
            $this->fail('A duplicate normalized property address should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('street', $exception->errors());
        }
    }

    public function test_existing_shared_tenant_addresses_remain_valid(): void
    {
        [, $first] = $this->homeowner([], [
            'house_number' => '12', 'street' => 'Narra Street', 'block' => 'B 7', 'lot' => 'L 8', 'ownership_type' => 'Tenant',
        ]);
        [, $second] = $this->homeowner([], [
            'house_number' => 'No. 12', 'street' => 'Narra St.', 'block' => 'Block 7', 'lot' => 'Lot 8', 'ownership_type' => 'Tenant',
        ]);

        $this->assertSame($first->address_key, $second->address_key);
        $this->assertSame(2, Homeowner::query()->where('address_key', $first->address_key)->count());
    }

    public function test_existing_homeowner_cannot_be_reassigned_to_another_user(): void
    {
        [, $homeowner] = $this->homeowner();
        [$otherUser] = $this->homeowner();

        $this->expectException(ValidationException::class);
        $homeowner->update(['user_id' => $otherUser->id]);
    }

    public function test_profile_photo_is_reencoded_privately_and_replacement_deletes_the_old_file(): void
    {
        Storage::fake('local');
        [$resident, $homeowner] = $this->homeowner();

        app(UpdateOwnProfile::class)->handle($resident, $this->profilePayload([
            'profile_photo_upload' => UploadedFile::fake()->image('first.jpg', 240, 240),
        ]));
        $firstPath = (string) $homeowner->refresh()->profile_photo;

        Storage::disk('local')->assertExists($firstPath);
        $this->assertStringNotContainsString('first.jpg', $firstPath);
        $this->assertSame('image/jpeg', $homeowner->profile_photo_mime_type);
        $this->assertSame('local', $homeowner->profile_photo_disk);

        app(UpdateOwnProfile::class)->handle($resident->refresh(), $this->profilePayload([
            'profile_photo_upload' => UploadedFile::fake()->image('replacement.png', 260, 260),
        ]));
        $secondPath = (string) $homeowner->refresh()->profile_photo;

        Storage::disk('local')->assertMissing($firstPath);
        Storage::disk('local')->assertExists($secondPath);
        $this->assertNotSame($firstPath, $secondPath);
    }

    public function test_invalid_profile_photo_is_rejected_without_mutating_the_record(): void
    {
        Storage::fake('local');
        [$resident, $homeowner] = $this->homeowner();

        try {
            app(UpdateOwnProfile::class)->handle($resident, $this->profilePayload([
                'profile_photo_upload' => UploadedFile::fake()->createWithContent('shell.jpg', '<?php echo 1;'),
            ]));
            $this->fail('A non-image payload should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('profile_photo_upload', $exception->errors());
        }

        $this->assertNull($homeowner->refresh()->profile_photo);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_private_photo_delivery_is_owner_and_operational_role_scoped(): void
    {
        Storage::fake('local');
        [$owner, $homeowner] = $this->homeowner();
        [$otherOwner] = $this->homeowner();
        $admin = $this->roleUser('hoa_admin');
        $staff = $this->roleUser('hoa_staff');

        app(UpdateOwnProfile::class)->handle($owner, $this->profilePayload([
            'profile_photo_upload' => UploadedFile::fake()->image('avatar.webp', 200, 200),
        ]));
        $url = route('homeowner.profile-photo', $homeowner->refresh());

        $this->get($url)->assertRedirect();
        $this->actingAs($otherOwner, 'web')->get($url)->assertNotFound();
        $ownerResponse = $this->actingAs($owner, 'web')->get($url)->assertOk();
        $this->assertStringContainsString('private', (string) $ownerResponse->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $ownerResponse->headers->get('Cache-Control'));
        $this->actingAs($admin, 'admin')->get($url)->assertOk();
        $this->actingAs($staff, 'staff')->get($url)->assertOk();
    }

    public function test_password_change_requires_current_password_revokes_sessions_and_logs_out(): void
    {
        [$resident] = $this->homeowner();
        DB::table('sessions')->insert([
            'id' => 'other-device',
            'user_id' => $resident->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'test',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($resident, 'web')
            ->patch('/portal/profile/password', [
                'current_password' => 'wrong-password',
                'password' => 'New-secure-password!2026',
                'password_confirmation' => 'New-secure-password!2026',
            ])
            ->assertSessionHasErrors('current_password');
        $this->assertAuthenticatedAs($resident, 'web');

        $this->patch('/portal/profile/password', [
            'current_password' => 'password',
            'password' => 'New-secure-password!2026',
            'password_confirmation' => 'New-secure-password!2026',
        ])->assertRedirect('/portal/login');

        $this->assertGuest('web');
        $this->assertTrue(Hash::check('New-secure-password!2026', $resident->refresh()->password));
        $this->assertDatabaseMissing('sessions', ['user_id' => $resident->id]);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $resident->id,
            'action' => 'Auth.password_changed',
        ]);
        $this->assertStringNotContainsString('New-secure-password!2026', DB::table('audit_logs')->where('user_id', $resident->id)->get()->toJson());
    }

    public function test_homeowner_panel_has_a_dedicated_profile_page_and_missing_relation_fails_closed(): void
    {
        [$resident] = $this->homeowner();
        $this->actingAs($resident, 'homeowner')->get('/homeowner/my-profile')->assertOk();
        $this->actingAs($resident, 'web')->get('/portal/profile')->assertOk()
            ->assertDontSee('name="street"', false)
            ->assertDontSee('name="block"', false)
            ->assertDontSee('name="lot"', false)
            ->assertDontSee('name="house_number"', false);

        Livewire::actingAs($resident, 'homeowner')
            ->test(MyProfile::class)
            ->assertFormSet([
                'first_name' => $resident->first_name,
                'email' => $resident->email,
            ], 'profileForm')
            ->assertSchemaComponentDoesNotExist('house_number', 'profileForm')
            ->assertSchemaComponentDoesNotExist('phase', 'profileForm')
            ->assertSchemaComponentDoesNotExist('street', 'profileForm')
            ->assertSchemaComponentDoesNotExist('block', 'profileForm')
            ->assertSchemaComponentDoesNotExist('lot', 'profileForm')
            ->assertFormFieldDisabled('ownership_type', 'profileForm');

        $orphan = $this->roleUser('homeowner');
        $this->actingAs($orphan, 'homeowner')->get('/homeowner/my-profile')->assertNotFound();
    }

    public function test_staff_cannot_address_or_restore_a_soft_deleted_homeowner_record(): void
    {
        [, $homeowner] = $this->homeowner();
        $homeowner->delete();
        $staff = $this->roleUser('hoa_staff');
        $admin = $this->roleUser('hoa_admin');

        $this->actingAs($staff, 'staff')->get("/staff/homeowners/{$homeowner->id}/edit")->assertNotFound();
        $this->actingAs($admin, 'admin')->get("/admin/homeowners/{$homeowner->id}/edit")->assertOk();
        $this->assertFalse($staff->can('restore', $homeowner));
        $this->assertTrue($admin->can('restore', $homeowner));
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function profilePayload(array $overrides = []): array
    {
        return [
            'first_name' => 'Juan',
            'middle_name' => 'Santos',
            'last_name' => 'Dela Cruz',
            'suffix' => null,
            'sex' => 'Male',
            'date_of_birth' => '1990-08-28',
            'contact_number' => '09123456789',
            'email' => 'resident@example.test',
            'residency_date' => '2021-01-01',
            'ownership_type' => 'Owner',
            'emergency_contact_name' => 'Maria Dela Cruz',
            'emergency_contact_number' => '09987654321',
            'confirm_profile_update' => '1',
            ...$overrides,
        ];
    }

    /** @param array<string, mixed> $userOverrides @param array<string, mixed> $homeownerOverrides @return array{User, Homeowner} */
    private function homeowner(array $userOverrides = [], array $homeownerOverrides = []): array
    {
        $sequence = User::query()->count() + 1;
        $user = User::factory()->create([
            'email' => "resident{$sequence}@example.test",
            'account_status' => 'Active',
            'email_verified_at' => now(),
            ...$userOverrides,
        ]);
        $user->assignRole('homeowner');
        $homeowner = $user->homeowner()->create([
            'house_number' => (string) (10 + $sequence),
            'street' => 'Mahogany Street',
            'block' => "B {$sequence}",
            'lot' => "L {$sequence}",
            'phase' => 'Southville Phase I',
            'residency_date' => '2021-01-01',
            'ownership_type' => 'Owner',
            'emergency_contact_name' => 'Emergency Contact',
            'emergency_contact_number' => '09987654321',
            'status' => 'Active',
            ...$homeownerOverrides,
        ]);

        return [$user, $homeowner];
    }

    /** @param array<string, mixed> $attributes */
    private function roleUser(string $role, array $attributes = []): User
    {
        $user = User::factory()->create(['account_status' => 'Active', ...$attributes]);
        $user->assignRole($role);

        return $user;
    }
}
