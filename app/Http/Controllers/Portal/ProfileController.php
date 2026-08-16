<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('portal.profile.edit', ['user' => request()->user()->load('homeowner')]);
    }

    public function update(UpdateProfileRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $request->user()->update($request->safe()->only(['contact_number', 'email']));
            $request->user()->homeowner->update($request->safe()->only(['house_number', 'street', 'block', 'lot']));
        });

        return back()->with('success', __('Profile updated.'));
    }
}
