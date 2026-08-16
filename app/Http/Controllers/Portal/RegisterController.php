<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Actions\Homeowners\RegisterHomeowner;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\RegisterHomeownerRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class RegisterController extends Controller
{
    public function create(): View
    {
        return view('portal.auth.register');
    }

    public function store(RegisterHomeownerRequest $request, RegisterHomeowner $action): RedirectResponse
    {
        $user = $action->handle($request->validated());

        return redirect()->route('portal.login')->with('success', __('Your application was submitted for HOA Admin review.'))->with('registered_email', $user->email);
    }
}
