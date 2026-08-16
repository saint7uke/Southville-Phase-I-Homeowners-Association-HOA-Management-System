<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;

class AuthenticatePortalSession extends AuthenticateSession
{
    protected function redirectTo(Request $request): string
    {
        return route('portal.login');
    }
}
