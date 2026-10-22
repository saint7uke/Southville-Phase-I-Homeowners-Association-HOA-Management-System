<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Account status | Southville Phase I HOA') }}</title>
    <x-favicon />
    @vite(['resources/css/app.css'])
</head>
<body>
    <a class="skip-link" href="#main-content" data-i18n="Skip to main content">{{ __('Skip to main content') }}</a>
    <main id="main-content" class="auth-shell" tabindex="-1">
        <section class="auth-card" aria-labelledby="status-heading">
            <x-locale-toolbar />
            <a class="auth-brand" href="{{ route('home') }}"><span class="brand-mark" aria-hidden="true">S1</span>Southville Phase I</a>
            <h1 id="status-heading" data-i18n="Account status">{{ __('Account status') }}</h1>
            @if (session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif

            @if (auth()->user()->account_status === 'Active' && ! auth()->user()->hasVerifiedEmail())
                <p><span data-i18n="Verify your email address before continuing. Use the verification link sent to">{{ __('Verify your email address before continuing. Use the verification link sent to') }}</span> <strong>{{ auth()->user()->email }}</strong>.</p>
                <form method="POST" action="{{ route('portal.verification.resend') }}">
                    @csrf
                    <button class="button button-secondary" type="submit" data-i18n="Resend verification email">{{ __('Resend verification email') }}</button>
                </form>
            @else
                @switch(auth()->user()->account_status)
                    @case('Pending')
                        <p><span data-i18n="Your registration is awaiting HOA Admin review. You will be able to use the portal after approval.">{{ __('Your registration is awaiting HOA Admin review. You will be able to use the portal after approval.') }}</span></p>
                        @break
                    @case('Rejected')
                        <p><span data-i18n="Your registration was not approved.">{{ __('Your registration was not approved.') }}</span></p>
                        @if(auth()->user()->rejection_reason)<div class="alert alert-error"><strong data-i18n="Reason:">{{ __('Reason:') }}</strong> {{ auth()->user()->rejection_reason }}</div>@endif
                        @break
                    @default
                        <p><span data-i18n="This account is currently inactive. Contact the HOA office for assistance.">{{ __('This account is currently inactive. Contact the HOA office for assistance.') }}</span></p>
                @endswitch
            @endif

            <form method="POST" action="{{ route('portal.logout') }}">@csrf<button class="button button-primary" type="submit" data-i18n="Log out">{{ __('Log out') }}</button></form>
        </section>
    </main>
</body>
</html>
