<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locale = $request->session()->get('locale', 'en');
        foreach (['admin', 'staff', 'homeowner', 'web'] as $guard) {
            if ($user = Auth::guard($guard)->user()) {
                $locale = $user->preferred_locale ?? $locale;
                break;
            }
        }
        app()->setLocale(in_array($locale, ['en', 'fil'], true) ? $locale : 'en');

        return $next($request);
    }
}
