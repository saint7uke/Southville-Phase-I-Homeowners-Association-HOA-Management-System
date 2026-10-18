<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\UserAccountStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\LoginRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
        $isHomeownerPanelLogin = $request->routeIs('homeowner.login.store');
        $guard = Auth::guard($isHomeownerPanelLogin ? 'homeowner' : 'web');
        $credentials = $request->safe()->only(['email', 'password']);
        $authenticated = $guard->attemptWhen(
            $credentials,
            static function (User $user) use ($isHomeownerPanelLogin): bool {
                $status = UserAccountStatus::tryFrom((string) $user->account_status);

                return $user->hasRole('homeowner')
                    && ($isHomeownerPanelLogin
                        ? $status === UserAccountStatus::Active
                        : in_array($status, [
                            UserAccountStatus::Active,
                            UserAccountStatus::Pending,
                            UserAccountStatus::Rejected,
                        ], true));
            },
            $request->boolean('remember'),
        );

        if (! $authenticated) {
            throw ValidationException::withMessages([
                'email' => $isHomeownerPanelLogin
                    ? $this->homeownerLoginError($request)
                    : __('The provided credentials are incorrect.'),
            ]);
        }

        $request->session()->regenerate();
        /** @var User $user */
        $user = $guard->user();

        if ($isHomeownerPanelLogin) {
            Auth::guard('web')->logout();

            return redirect()->route('filament.homeowner.pages.dashboard');
        }

        $status = UserAccountStatus::tryFrom((string) $user->account_status);

        if (in_array($status, [UserAccountStatus::Pending, UserAccountStatus::Rejected], true)) {
            return redirect()->route('portal.status');
        }

        return redirect()->intended(route('portal.dashboard'));
    }

    private function homeownerLoginError(Request $request): string
    {
        $user = User::query()
            ->where('email', mb_strtolower(trim((string) $request->input('email'))))
            ->first();

        if (! $user instanceof User || ! Hash::check((string) $request->input('password'), $user->password) || ! $user->hasRole('homeowner')) {
            return __('The provided credentials are incorrect.');
        }

        return match (UserAccountStatus::tryFrom((string) $user->account_status)) {
            UserAccountStatus::Pending => __('Your application is still pending approval.'),
            UserAccountStatus::Rejected => __('Your homeowner application was rejected.'),
            UserAccountStatus::Suspended => __('Your homeowner account is suspended.'),
            UserAccountStatus::Inactive => __('Your homeowner account is inactive.'),
            default => __('The provided credentials are incorrect.'),
        };
    }

    public function destroy(): RedirectResponse
    {
        Auth::guard('web')->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('homeowner.login');
    }
}
