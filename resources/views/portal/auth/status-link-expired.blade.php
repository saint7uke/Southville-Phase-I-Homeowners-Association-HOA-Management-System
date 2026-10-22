<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Status link expired | Southville Phase I HOA') }}</title>
    <x-favicon />
    @vite(['resources/css/landing.css'])
</head>
<body class="hoa-landing">
    <main class="hoa-auth-shell">
        <div class="hoa-auth-overlay"></div>
        <section class="hoa-auth-card hoa-auth-card-confirmation" aria-labelledby="status-link-title">
            <x-locale-toolbar appearance="light" />
            <a href="{{ route('home') }}" class="hoa-auth-brand" aria-label="Go to homepage">
                <span class="hoa-brand-mark" aria-hidden="true">S1</span>
                <span class="hoa-auth-brand-title">Southville Phase I</span>
            </a>
            <div class="hoa-status-icon" aria-hidden="true">!</div>
            <p class="hoa-eyebrow"><span></span><span data-i18n="Application status">{{ __('Application status') }}</span></p>
            <h1 id="status-link-title"><span data-i18n="This status link has expired">{{ __('This status link has expired') }}</span></h1>
            <p class="hoa-auth-lead"><span data-i18n="For your security, registration status links are temporary and can no longer be used.">{{ __('For your security, registration status links are temporary and can no longer be used.') }}</span></p>
            <p class="hoa-auth-lead"><span data-i18n="Sign in with your homeowner account to view the current application status securely.">{{ __('Sign in with your homeowner account to view the current application status securely.') }}</span></p>
            <a class="hoa-button hoa-button-primary hoa-button-block" href="{{ $loginUrl }}" data-i18n="Continue to homeowner login">{{ __('Continue to homeowner login') }}</a>
            <a class="hoa-button hoa-button-secondary hoa-button-block" href="{{ route('home') }}" data-i18n="Return to homepage">{{ __('Return to homepage') }}</a>
        </section>
    </main>
</body>
</html>
