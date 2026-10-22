<?php

use App\Http\Middleware\AuthenticatePortalSession;
use App\Http\Middleware\AuthenticateProfilePhoto;
use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsureHomeownerIsApproved;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\ValidateRegistrationStatusSignature;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
        $middleware->web(append: [SetLocale::class]);
        $middleware->alias([
            'account.active' => EnsureActiveAccount::class,
            'profile-photo.auth' => AuthenticateProfilePhoto::class,
            'portal.auth.session' => AuthenticatePortalSession::class,
            'homeowner.approved' => EnsureHomeownerIsApproved::class,
            'registration.status.link' => ValidateRegistrationStatusSignature::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
