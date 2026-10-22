<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class UnifiedAuthentication
{
    public function guardFor(User $user): ?string
    {
        foreach (['hoa_admin' => 'admin', 'hoa_staff' => 'staff', 'homeowner' => 'homeowner'] as $role => $guard) {
            if ($user->hasRole($role)) {
                return $guard;
            }
        }

        return null;
    }

    public function authenticate(array $input): string
    {
        $credentials = ['email' => mb_strtolower(trim((string) ($input['email'] ?? ''))), 'password' => (string) ($input['password'] ?? '')];
        $candidate = User::query()->where('email', $credentials['email'])->first();
        $target = $candidate ? $this->guardFor($candidate) : null;
        $settings = SystemSetting::current();
        $enabled = $target === 'admin' || ($target === 'staff' && $settings->staff_panel_enabled)
            || ($target === 'homeowner' && $settings->homeowner_panel_enabled);
        $guard = Auth::guard($target ?? 'web');
        if (! $guard->attemptWhen($credentials, fn (User $user): bool => $target !== null && $enabled
            && ! $user->trashed() && $user->account_status === 'Active', (bool) ($input['remember'] ?? false))) {
            $message = 'The provided credentials are incorrect.';
            if ($candidate && Hash::check($credentials['password'], $candidate->password)) {
                $message = ! $enabled && $target !== null ? 'This portal is temporarily unavailable.' : match ($candidate->account_status) {
                    'Pending' => 'Your application is still pending approval.',
                    'Rejected' => 'Your homeowner application was rejected.',
                    'Suspended' => 'Your account is suspended.',
                    'Inactive' => 'Your account is inactive.',
                    default => $message,
                };
            }
            throw ValidationException::withMessages(['email' => __($message)]);
        }

        foreach (['web', 'admin', 'staff', 'homeowner'] as $other) {
            if ($other !== $target) {
                Auth::guard($other)->logout();
            }
        }
        Auth::shouldUse($target);
        request()->session()->regenerate();

        return route("filament.{$target}.pages.dashboard");
    }
}
