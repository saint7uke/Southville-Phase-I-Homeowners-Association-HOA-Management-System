<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Enums\UserAccountStatus;
use App\Models\User;
use App\Support\PersonName;
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

        $data = $this->normalize($data);
        $name = ['required', 'string', 'max:100', "regex:/^[\pL\s'.-]+$/u"];

        $validated = Validator::make($data, [
            'first_name' => $name,
            'middle_name' => ['nullable', 'string', 'max:100', "regex:/^[\pL\s'.-]+$/u"],
            'last_name' => $name,
            'suffix' => ['nullable', Rule::in(['Jr.', 'Sr.', 'II', 'III', 'IV', 'N/A'])],
            'sex' => ['required', Rule::in(['Male', 'Female', 'Prefer not to say'])],
            'contact_number' => ['required', 'regex:/^09\d{9}$/'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'email' => ['required', 'email:rfc', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', Password::defaults()],
            'role' => ['required', Rule::in(['hoa_admin', 'hoa_staff', 'homeowner'])],
            'house_number' => ['required_if:role,homeowner', 'nullable', 'string', 'max:50'],
            'street' => ['required_if:role,homeowner', 'nullable', 'string', 'max:100'],
            'block' => ['required_if:role,homeowner', 'nullable', 'string', 'max:20'],
            'lot' => ['required_if:role,homeowner', 'nullable', 'string', 'max:20'],
            'residency_date' => ['required_if:role,homeowner', 'nullable', 'date', 'before_or_equal:today'],
            'ownership_type' => ['required_if:role,homeowner', 'nullable', Rule::in(['Owner', 'Tenant', 'Co-owner'])],
            'emergency_contact_name' => ['required_if:role,homeowner', 'nullable', 'string', 'max:100', "regex:/^[\pL\s'.-]+$/u"],
            'emergency_contact_number' => ['required_if:role,homeowner', 'nullable', 'regex:/^09\d{9}$/'],
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
            $homeownerFields = ['house_number', 'street', 'block', 'lot', 'residency_date', 'ownership_type', 'emergency_contact_name', 'emergency_contact_number'];
            $homeownerData = collect($validated)->only($homeownerFields)->all();
            unset($validated['role']);
            foreach ($homeownerFields as $field) {
                unset($validated[$field]);
            }

            $user = User::query()->create($validated);
            $user->forceFill(['account_status' => UserAccountStatus::Pending->value])->save();
            $user->assignRole($role);
            if ($role === 'homeowner') {
                $user->homeowner()->create([
                    ...$homeownerData,
                    'phase' => 'Southville Phase I',
                    'status' => 'Inactive',
                ]);
            }

            return $user->refresh();
        });
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function normalize(array $data): array
    {
        foreach (['first_name', 'middle_name', 'last_name', 'emergency_contact_name'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = PersonName::normalize($data[$field]);
            }
        }

        foreach (['house_number', 'street'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $this->collapseWhitespace($data[$field]);
            }
        }

        foreach (['block', 'lot'] as $field) {
            if (array_key_exists($field, $data)) {
                $value = $this->collapseWhitespace($data[$field]);
                $data[$field] = $value === null ? null : mb_strtoupper($value, 'UTF-8');
            }
        }

        if (array_key_exists('suffix', $data)) {
            $data['suffix'] = $this->collapseWhitespace($data['suffix']);
        }

        if (array_key_exists('email', $data)) {
            $data['email'] = mb_strtolower(trim((string) $data['email']), 'UTF-8');
        }

        return $data;
    }

    private function collapseWhitespace(mixed $value): ?string
    {
        $collapsed = preg_replace('/\s+/u', ' ', trim((string) $value));

        return filled($collapsed) ? $collapsed : null;
    }
}
