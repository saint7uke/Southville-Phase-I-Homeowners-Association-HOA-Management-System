<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\UserAccountStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class AuthController extends Controller
{
    public function create(): View
    {
        return view('portal.auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $guard = Auth::guard('web');
        $credentials = $request->safe()->only(['email', 'password']);
        $authenticated = $guard->attemptWhen(
            $credentials,
            static function (User $user): bool {
                $status = UserAccountStatus::tryFrom((string) $user->account_status);

                return $user->hasRole('homeowner') && in_array($status, [
                    UserAccountStatus::Active,
                    UserAccountStatus::Pending,
                    UserAccountStatus::Rejected,
                ], true);
            },
            $request->boolean('remember'),
        );

        if (! $authenticated) {
            throw ValidationException::withMessages(['email' => __('The provided credentials are incorrect.')]);
        }

        $request->session()->regenerate();
        /** @var User $user */
        $user = $guard->user();

        $status = UserAccountStatus::tryFrom((string) $user->account_status);

        if (in_array($status, [UserAccountStatus::Pending, UserAccountStatus::Rejected], true)) {
            return redirect()->route('portal.status');
        }

        return redirect()->intended(route('portal.dashboard'));
    }

    public function destroy(): RedirectResponse
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('portal.login');
    }
}
