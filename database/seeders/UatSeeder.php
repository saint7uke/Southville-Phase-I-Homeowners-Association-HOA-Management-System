<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use LogicException;

final class UatSeeder extends Seeder
{
    private const PASSWORD = 'SouthvilleUat2026!';

    public function run(): void
    {
        if (app()->environment('production') || config('app.env') === 'production') {
            throw new LogicException('UAT fixtures cannot be seeded in production.');
        }

        $this->call(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'admin@southville.test')->firstOrFail();

        $secondStaff = $this->fixtureUser('staff2@southville.test', [
            'first_name' => 'Liza',
            'last_name' => 'Flores',
            'sex' => 'Female',
            'contact_number' => '09181234568',
            'date_of_birth' => '1990-07-14',
            'account_status' => 'Active',
            'email_verified_at' => now(),
            'approved_at' => now(),
            'approved_by' => $admin->id,
        ]);
        $secondStaff->syncRoles(['hoa_staff']);

        $secondHomeowner = $this->fixtureUser('resident2@southville.test', [
            'first_name' => 'Ramon',
            'last_name' => 'Villanueva',
            'sex' => 'Male',
            'contact_number' => '09191234568',
            'date_of_birth' => '1988-11-03',
            'account_status' => 'Active',
            'email_verified_at' => now(),
            'approved_at' => now(),
            'approved_by' => $admin->id,
        ]);
        $secondHomeowner->syncRoles(['homeowner']);
        $secondHomeowner->homeowner()->updateOrCreate([], [
            'house_number' => '27',
            'street' => 'Acacia Street',
            'block' => '7',
            'lot' => '9',
            'phase' => 'Southville Phase I',
            'residency_date' => '2019-02-15',
            'ownership_type' => 'Owner',
            'emergency_contact_name' => 'Elena Villanueva',
            'emergency_contact_number' => '09171234568',
            'status' => 'Active',
        ]);

        $pendingHomeowner = $this->fixtureUser('pending@southville.test', [
            'first_name' => 'Carmen',
            'last_name' => 'Del Rosario',
            'sex' => 'Female',
            'contact_number' => '09191234569',
            'date_of_birth' => '1995-04-12',
            'account_status' => 'Pending',
            'email_verified_at' => null,
            'approved_at' => null,
            'approved_by' => null,
        ]);
        $pendingHomeowner->syncRoles(['homeowner']);
        $pendingHomeowner->homeowner()->updateOrCreate([], [
            'house_number' => '31',
            'street' => 'Molave Street',
            'block' => '8',
            'lot' => '3',
            'phase' => 'Southville Phase I',
            'residency_date' => '2024-08-01',
            'ownership_type' => 'Owner',
            'emergency_contact_name' => 'Pedro Del Rosario',
            'emergency_contact_number' => '09171234569',
            'status' => 'Inactive',
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function fixtureUser(string $email, array $attributes): User
    {
        $user = User::query()->firstOrNew(['email' => $email]);
        $user->forceFill([
            ...$attributes,
            'email' => $email,
            'password' => Hash::make(self::PASSWORD),
            'rejection_reason' => null,
            'suspended_at' => null,
            'suspension_reason' => null,
            'suspended_by' => null,
            'deleted_at' => null,
        ])->saveQuietly();

        return $user;
    }
}
