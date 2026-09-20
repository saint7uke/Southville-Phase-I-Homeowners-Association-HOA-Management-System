<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Homeowners\UpdateOwnProfile;
use App\Filament\Homeowner\Pages\MyProfile;
use App\Models\Homeowner;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
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

    public function test_portal_owner_updates_a_complete_normalized_profile_and_reverifies_changed_email(): void
    {
        Notification::fake();
        [$resident, $homeowner] = $this->homeowner();

        $this->actingAs($resident, 'web')
            ->patch('/portal/profile', $this->profilePayload([
                'first_name' => '  maría   de la cruz ',
                'email' => ' NEW.EMAIL@EXAMPLE.TEST ',
                'block' => ' b  12 ',
                'lot' => ' l  4 ',
            ]))
            ->assertRedirect('/portal/status');

        $resident->refresh();
        $homeowner->refresh();
        $this->assertSame('María De La Cruz', $resident->first_name);
        $this->assertSame('new.email@example.test', $resident->email);
        $this->assertNull($resident->email_verified_at);
        $this->assertSame('B 12', $homeowner->block);
        $this->assertSame('L 4', $homeowner->lot);
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

    public function test_normalized_phase_block_and_lot_must_be_unique(): void
    {
        [, $existing] = $this->homeowner([], [
            'block' => 'B 1',
            'lot' => 'L 2',
        ]);
        [$resident] = $this->homeowner([], [
            'block' => 'B 9',
            'lot' => 'L 9',
        ]);

        try {
            app(UpdateOwnProfile::class)->handle($resident, $this->profilePayload([
                'block' => ' b   1 ',
                'lot' => ' l 2 ',
                'phase' => $existing->phase,
            ]));
            $this->fail('A duplicate normalized property address should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('lot', $exception->errors());
        }
    }

    public function test_database_unique_constraint_protects_property_address_races(): void
    {
        $this->homeowner([], ['block' => 'B 7', 'lot' => 'L 8']);
        [, $second] = $this->homeowner([], ['block' => 'B 9', 'lot' => 'L 9']);

        $this->expectException(QueryException::class);
        $second->update(['block' => 'B 7', 'lot' => 'L 8']);
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

        Livewire::actingAs($resident, 'homeowner')
            ->test(MyProfile::class)
            ->assertFormSet([
                'first_name' => $resident->first_name,
                'email' => $resident->email,
            ], 'profileForm');

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
            'house_number' => '10',
            'street' => 'Mahogany Street',
            'block' => 'B 10',
            'lot' => 'L 10',
            'phase' => 'Southville Phase I',
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
