<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Support\PersonName;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use RuntimeException;

final class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $input = (array) config('hoa.initial_admin', []);
        foreach (['first_name', 'middle_name', 'last_name'] as $field) {
            $input[$field] = PersonName::normalize($input[$field] ?? null);
        }
        $input['email'] = mb_strtolower(trim((string) ($input['email'] ?? '')), 'UTF-8');
        $input['contact_number'] = trim((string) ($input['contact_number'] ?? ''));

        $name = ['required', 'string', 'max:100', "regex:/^[\pL\s'.-]+$/u"];
        $validated = Validator::make($input, [
            'first_name' => $name,
            'middle_name' => ['nullable', 'string', 'max:100', "regex:/^[\pL\s'.-]+$/u"],
            'last_name' => $name,
            'suffix' => ['nullable', Rule::in(['Jr.', 'Sr.', 'II', 'III', 'IV', 'N/A'])],
            'sex' => ['required', Rule::in(['Male', 'Female', 'Prefer not to say'])],
            'contact_number' => ['required', 'regex:/^09\d{9}$/'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
            'password_confirmation' => ['required', 'string'],
        ], [
            'password.required' => 'Set the temporary initial Admin password through the approved environment configuration.',
            'password.confirmed' => 'The two initial Admin password values do not match.',
        ])->validate();

        $this->call(RolesAndPermissionsSeeder::class);

        $existing = User::query()->withTrashed()->where('email', $validated['email'])->first();
        if ($existing !== null) {
            if ($existing->trashed() || $existing->account_status !== 'Active' || ! $existing->hasRole('hoa_admin')) {
                throw new RuntimeException('The configured initial Admin email already belongs to an account that is not an active HOA Admin; resolve it explicitly instead of elevating it through the seeder.');
            }

            $this->command?->info('The configured active HOA Admin already exists; no credentials or profile fields were changed.');

            return;
        }

        DB::transaction(function () use ($validated): void {
            $admin = User::query()->create([
                'first_name' => $validated['first_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'last_name' => $validated['last_name'],
                'suffix' => $validated['suffix'] ?? null,
                'sex' => $validated['sex'],
                'contact_number' => $validated['contact_number'],
                'date_of_birth' => $validated['date_of_birth'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);
            $admin->forceFill([
                'account_status' => 'Active',
                'email_verified_at' => now(),
                'approved_at' => now(),
            ])->saveQuietly();
            $admin->assignRole('hoa_admin');
        });

        $this->command?->info('Initial HOA Admin created. Remove both password variables from the environment and rebuild the configuration cache.');
    }
}
