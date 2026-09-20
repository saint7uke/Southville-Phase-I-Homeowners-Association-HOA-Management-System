<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class EmailVerificationController extends Controller
{
    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->id === $id && hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect()->route('portal.dashboard')->with('success', __('Email address verified.'));
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->hasRole('homeowner'), 403);

        if ($user->account_status !== 'Active' || $user->hasVerifiedEmail()) {
            return back();
        }

        $user->sendEmailVerificationNotification();

        return back()->with('success', __('A new verification link has been sent.'));
    }
}
