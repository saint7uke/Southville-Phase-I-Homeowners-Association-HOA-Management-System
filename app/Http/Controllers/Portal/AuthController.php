<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\LoginRequest;
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
        if (! Auth::attempt($request->safe()->only(['email', 'password']), $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => __('The provided credentials are incorrect.')]);
        }

        $request->session()->regenerate();
        $user = $request->user();

        if (! $user?->hasRole('homeowner')) {
            Auth::logout();
            throw ValidationException::withMessages(['email' => __('This account must use the staff admin panel.')]);
        }

        if ($user->account_status !== 'Active') {
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
