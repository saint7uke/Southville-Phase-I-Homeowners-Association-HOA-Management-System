<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

final class LocaleController extends Controller
{
    public function __invoke(Request $request)
    {
        $locale = $request->validate(['locale' => ['required', Rule::in(['en', 'fil'])]])['locale'];
        foreach (['admin', 'staff', 'homeowner', 'web'] as $guard) {
            if ($user = Auth::guard($guard)->user()) {
                abort_if($user->trashed() || $user->account_status !== 'Active', 403);
                $user->forceFill(['preferred_locale' => $locale])->save();
                break;
            }
        }
        $request->session()->put('locale', $locale);
        app()->setLocale($locale);

        return response()->json(['locale' => $locale]);
    }
}
