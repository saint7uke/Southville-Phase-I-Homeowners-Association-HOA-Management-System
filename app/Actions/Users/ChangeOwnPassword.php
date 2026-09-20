<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

final class ChangeOwnPassword
{
    /** @param array<string, mixed> $input */
    public function handle(User $actor, array $input, string $guard): User
    {
        if (! $actor->hasRole('homeowner') || $actor->homeowner === null || ! in_array($guard, ['web', 'homeowner'], true)) {
            throw new AuthorizationException;
        }

        $validated = Validator::make($input, [
            'current_password' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255', 'confirmed', Password::default()],
        ])->validate();

        return DB::transaction(function () use ($actor, $validated, $guard): User {
            $locked = User::query()->lockForUpdate()->findOrFail($actor->id);

            if (! Hash::check($validated['current_password'], $locked->getAuthPassword())) {
                throw ValidationException::withMessages([
                    'current_password' => __('The current password is incorrect.'),
                ]);
            }

            if (Hash::check($validated['password'], $locked->getAuthPassword())) {
                throw ValidationException::withMessages([
                    'password' => __('The new password must be different from the current password.'),
                ]);
            }

            $locked->forceFill([
                'password' => $validated['password'],
                'password_changed_at' => now(),
                'remember_token' => Str::random(60),
            ])->save();

            DB::table((string) config('session.table', 'sessions'))
                ->where('user_id', $locked->id)
                ->delete();

            AuditLog::query()->create([
                'user_id' => $locked->id,
                'action' => 'Auth.password_changed',
                'auditable_type' => $locked->getMorphClass(),
                'auditable_id' => $locked->id,
                'old_values' => null,
                'new_values' => ['panel' => $guard],
                'ip_address' => app()->runningInConsole() ? null : request()->ip(),
                'user_agent' => app()->runningInConsole() ? null : mb_substr((string) request()->userAgent(), 0, 500),
            ]);

            return $locked->refresh();
        });
    }
}
