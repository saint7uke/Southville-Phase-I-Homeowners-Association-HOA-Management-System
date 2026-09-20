<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureHomeownerIsApproved
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user?->hasRole('homeowner'), 403);
        abort_unless($user->homeowner()->exists(), 404);

        if ($user->account_status !== 'Active') {
            return redirect()->route('portal.status');
        }

        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('portal.status');
        }

        return $next($request);
    }
}
