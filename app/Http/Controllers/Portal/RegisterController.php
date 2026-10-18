<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Actions\Homeowners\RegisterHomeowner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\RegisterHomeownerRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

final class RegisterController extends Controller
{
    public function create(): View
    {
        return view('portal.auth.register');
    }

    public function store(RegisterHomeownerRequest $request, RegisterHomeowner $action): RedirectResponse
    {
        $user = $action->handle($request->validated());

        if ($request->routeIs('portal.register.store')) {
            return redirect()->route('portal.login')
                ->with('success', __('Your application was submitted for HOA Admin review.'))
                ->with('registered_email', $user->email);
        }

        $statusUrl = URL::temporarySignedRoute(
            'homeowner.register.submitted',
            now()->addHours(24),
            ['user' => $user->id],
            false,
        );

        if (Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return redirect()->to($statusUrl)
            ->with('success', __('Your application was submitted for HOA Admin review.'))
            ->with('registered_email', $user->email);
    }

    public function submitted(): View
    {
        abort(404);
    }
}
