<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ValidateRegistrationStatusSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasValidSignature(false)) {
            return response()->view('portal.auth.status-link-expired', [
                'loginUrl' => route('homeowner.login'),
            ], 403);
        }

        return $next($request);
    }
}
