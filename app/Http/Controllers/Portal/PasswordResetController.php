<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;
use Throwable;

final class PasswordResetController extends Controller
{
    public function request(): View
    {
        return view('portal.auth.forgot-password');
    }

    public function email(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email:rfc']]);

        try {
            Password::broker('users')->sendResetLink($request->only('email'));
        } catch (Throwable $exception) {
            Log::warning('Password reset mail delivery failed.', [
                'exception' => $exception::class,
                'code' => 'password_reset_transport_failure',
            ]);
        }

        return back()->with('status', __('If an eligible account matches that address, a password reset link will be sent shortly.'));
    }

    public function reset(Request $request, string $token): View
    {
        return view('portal.auth.reset-password', [
            'token' => $token,
            'email' => $request->query('email', ''),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email:rfc'],
            'password' => ['required', 'confirmed', PasswordRule::default()],
        ]);

        $status = Password::reset(
            $validated,
            function ($user, string $password): void {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('success_title', __('Password Reset Successfully!'))->with('success', __('Your password has been reset. Approved accounts can now sign in.'))
            : back()->withErrors(['email' => __($status)]);
    }
}
