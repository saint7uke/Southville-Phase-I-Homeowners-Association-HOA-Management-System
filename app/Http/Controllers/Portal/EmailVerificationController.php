<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ActionFeedback;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class EmailVerificationController extends Controller
{
    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->id === $id && hash_equals(sha1($user->getEmailForVerification()), $hash), 403);

        if (! $user->hasVerifiedEmail() && $user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return redirect()->route('portal.dashboard')->with('success_title', __('Email Address Verified Successfully!'))->with('success', __('Email address verified.'));
    }

    public function resend(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->hasRole('homeowner'), 403);

        if ($user->account_status !== 'Active' || $user->hasVerifiedEmail()) {
            return back();
        }

        try {
            $user->sendEmailVerificationNotification();
        } catch (\Throwable) {
            Log::warning('Verification email delivery failed.', ['user_id' => $user->id, 'failure_code' => 'verification_transport_failure']);

            return back()->with('action_feedback', ActionFeedback::flash('danger', 'Verification email could not be sent', __('Please try again later or contact the system operator.')));
        }

        return back()->with('action_feedback', ActionFeedback::flash('info', 'Email Verification Requested', __('Check your email for the verification link.')));
    }
}
