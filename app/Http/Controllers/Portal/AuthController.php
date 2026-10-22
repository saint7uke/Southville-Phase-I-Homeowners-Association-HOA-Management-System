<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\LoginRequest;
use App\Services\UnifiedAuthentication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

final class AuthController extends Controller
{
    public function store(LoginRequest $request): RedirectResponse
    {
        return redirect()->to(app(UnifiedAuthentication::class)->authenticate($request->validated()));
    }

    public function destroy(): RedirectResponse
    {
        foreach (['web', 'admin', 'staff', 'homeowner'] as $guard) {
            Auth::guard($guard)->logout();
        }
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    }
}
