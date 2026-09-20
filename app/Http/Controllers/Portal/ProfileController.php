<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Actions\Homeowners\UpdateOwnProfile;
use App\Actions\Users\ChangeOwnPassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\ChangePasswordRequest;
use App\Http\Requests\Portal\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

final class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('portal.profile.edit', ['user' => request()->user()->load('homeowner')]);
    }

    public function update(UpdateProfileRequest $request, UpdateOwnProfile $updateProfile): RedirectResponse
    {
        $emailChanged = $request->user()->email !== $request->string('email')->toString();
        $updateProfile->handle($request->user(), $request->validated());

        if ($emailChanged) {
            return redirect()->route('portal.status')->with('success', __('Profile updated. Verify your new email address before continuing.'));
        }

        return back()->with('success', __('Profile updated.'));
    }

    public function changePassword(ChangePasswordRequest $request, ChangeOwnPassword $changePassword): RedirectResponse
    {
        $changePassword->handle($request->user(), $request->validated(), 'web');

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login')->with('success', __('Password changed. Sign in again with your new password.'));
    }
}
