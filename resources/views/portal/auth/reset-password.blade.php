<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Reset password | Southville Phase I HOA') }}</title>
    <x-favicon />
    @vite(['resources/css/landing.css', 'resources/js/app.js'])
</head>
<body class="hoa-landing">
    <main class="hoa-portal-auth" aria-labelledby="reset-title">
        <aside class="hoa-portal-auth-intro">
            <a class="hoa-portal-auth-brand" href="{{ route('home') }}" aria-label="Go to Southville Phase I HOA homepage">
                <span class="hoa-portal-auth-logo"><img src="{{ asset('images/HOA.png') }}" alt=""></span>
                <span>Southville Phase I HOA</span>
            </a>
            <p class="hoa-portal-auth-portal" data-i18n="Homeowner portal">{{ __('Homeowner portal') }}</p>
            <h1 data-i18n="Choose a strong password.">{{ __('Choose a strong password.') }}</h1>
            <p class="hoa-portal-auth-support"><span data-i18n="Use a password you do not reuse elsewhere to keep your HOA account protected.">{{ __('Use a password you do not reuse elsewhere to keep your HOA account protected.') }}</span></p>
        </aside>
        <section class="hoa-portal-auth-form">
            <x-locale-toolbar />
            <div class="hoa-portal-auth-heading">
                <p class="hoa-portal-auth-eyebrow" data-i18n="Account recovery">{{ __('Account recovery') }}</p>
                <h2 id="reset-title" data-i18n="Create a new password">{{ __('Create a new password') }}</h2>
                <p><span data-i18n="Set a new password for your homeowner account.">{{ __('Set a new password for your homeowner account.') }}</span></p>
            </div>
            @if($errors->any())<div class="hoa-contact-error" role="alert">{{ $errors->first() }}</div>@endif
            <form method="POST" action="{{ route('homeowner.password.update') }}" class="hoa-portal-auth-fields">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div class="hoa-field">
                    <label for="email" data-i18n="Email address">{{ __('Email address') }}</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="email">
                </div>
                <div class="hoa-field">
                    <label for="password" data-i18n="New password">{{ __('New password') }}</label>
                    <x-auth.password-toggle input-id="password">
                        <input id="password" name="password" type="password" required autocomplete="new-password" aria-describedby="password-hint">
                    </x-auth.password-toggle>
                    <p id="password-hint" class="hoa-hint"><span data-i18n="At least 12 characters with letters and numbers.">{{ __('At least 12 characters with letters and numbers.') }}</span></p>
                </div>
                <div class="hoa-field">
                    <label for="password_confirmation" data-i18n="Confirm new password">{{ __('Confirm new password') }}</label>
                    <x-auth.password-toggle input-id="password_confirmation">
                        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
                    </x-auth.password-toggle>
                </div>
                <button class="hoa-button hoa-button-primary hoa-portal-auth-submit" type="submit" data-i18n="Reset password">{{ __('Reset password') }}</button>
            </form>
            <nav class="hoa-portal-auth-links" aria-label="Account recovery navigation"><a href="{{ route('homeowner.login') }}">← Back to login</a></nav>
        </section>
    </main>
</body>
</html>
