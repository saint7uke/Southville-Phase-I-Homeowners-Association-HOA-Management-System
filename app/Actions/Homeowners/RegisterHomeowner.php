<?php

declare(strict_types=1);

namespace App\Actions\Homeowners;

use App\Models\User;
use App\Notifications\NewHomeownerRegistration;
use Illuminate\Support\Facades\DB;

final class RegisterHomeowner
{
    /** @param array<string, mixed> $data */
    public function handle(array $data): User
    {
        $user = DB::transaction(function () use ($data): User {
            $user = User::query()->create([
                'first_name' => $data['first_name'],
                'middle_name' => $data['middle_name'] ?? null,
                'last_name' => $data['last_name'],
                'suffix' => $data['suffix'] ?? null,
                'sex' => $data['sex'],
                'contact_number' => $data['contact_number'],
                'date_of_birth' => $data['date_of_birth'],
                'email' => $data['email'],
                'password' => $data['password'],
            ]);

            $user->assignRole('homeowner');
            $user->homeowner()->create([
                'house_number' => $data['house_number'],
                'street' => $data['street'],
                'block' => $data['block'] ?? null,
                'lot' => $data['lot'] ?? null,
                'phase' => 'Southville Phase I',
                'residency_date' => $data['residency_date'],
                'ownership_type' => $data['ownership_type'],
                'status' => 'Inactive',
            ]);

            return $user;
        });

        User::role('hoa_admin')->get()->each->notify(new NewHomeownerRegistration($user));

        return $user;
    }
}
