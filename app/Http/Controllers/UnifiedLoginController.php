<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Portal\LoginRequest;
use App\Services\UnifiedAuthentication;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class UnifiedLoginController extends Controller
{
    public function create(): View
    {
        return view('portal.auth.login');
    }

    public function store(LoginRequest $request, UnifiedAuthentication $authentication): RedirectResponse
    {
        return redirect()->to($authentication->authenticate($request->validated()));
    }
}
