<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Enums\UserAccountStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class CreatePendingUser
{
    /** @param array<string, mixed> $data */
    public function handle(array $data, User $actor): User
    {
        Gate::forUser($actor)->authorize('create', User::class);

        $validated = Validator::make($data, [
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'suffix' => ['nullable', Rule::in(['Jr.', 'Sr.', 'II', 'III', 'IV', 'N/A'])],
            'sex' => ['required', Rule::in(['Male', 'Female', 'Prefer not to say'])],
            'contact_number' => ['required', 'regex:/^09\d{9}$/'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults()],
            'role' => ['required', Rule::in(['hoa_admin', 'hoa_staff'])],
            'account_status' => ['prohibited'],
            'approved_at' => ['prohibited'],
            'approved_by' => ['prohibited'],
            'rejection_reason' => ['prohibited'],
            'suspended_at' => ['prohibited'],
            'suspended_by' => ['prohibited'],
            'suspension_reason' => ['prohibited'],
        ])->validate();

        return DB::transaction(function () use ($validated): User {
            $role = (string) $validated['role'];
            unset($validated['role']);

            $user = User::query()->create($validated);
            $user->forceFill(['account_status' => UserAccountStatus::Pending->value])->save();
            $user->assignRole($role);

            return $user->refresh();
        });
    }
}
