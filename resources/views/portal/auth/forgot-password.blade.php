<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Forgot password | Southville Phase I HOA') }}</title>
    <x-favicon />
    @vite(['resources/css/landing.css'])
</head>
<body class="hoa-landing">
    <main class="hoa-portal-auth" aria-labelledby="forgot-title">
        <aside class="hoa-portal-auth-intro">
            <a class="hoa-portal-auth-brand" href="{{ route('home') }}" aria-label="Go to Southville Phase I HOA homepage">
                <span class="hoa-portal-auth-logo"><img src="{{ asset('images/HOA.png') }}" alt=""></span>
                <span>Southville Phase I HOA</span>
            </a>
            <p class="hoa-portal-auth-portal" data-i18n="Homeowner portal">{{ __('Homeowner portal') }}</p>
            <h1><span data-i18n="Secure account recovery.">{{ __('Secure account recovery.') }}</span></h1>
            <p class="hoa-portal-auth-support"><span data-i18n="Request a secure password reset link using the email address connected to your homeowner account.">{{ __('Request a secure password reset link using the email address connected to your homeowner account.') }}</span></p>
        </aside>
        <section class="hoa-portal-auth-form">
            <x-locale-toolbar />
            <div class="hoa-portal-auth-heading">
                <p class="hoa-portal-auth-eyebrow" data-i18n="Account recovery">{{ __('Account recovery') }}</p>
                <h2 id="forgot-title" data-i18n="Forgot your password?">{{ __('Forgot your password?') }}</h2>
                <p><span data-i18n="Enter your email address and we’ll send a secure reset link.">{{ __('Enter your email address and we’ll send a secure reset link.') }}</span></p>
            </div>
            @if(session('status'))<div class="hoa-contact-success" role="status">{{ session('status') }}</div>@endif
            @if($errors->any())<div class="hoa-contact-error" role="alert">{{ $errors->first() }}</div>@endif
            <form method="POST" action="{{ route('homeowner.password.email') }}" class="hoa-portal-auth-fields">
                @csrf
                <div class="hoa-field">
                    <label for="email" data-i18n="Email address">{{ __('Email address') }}</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" autofocus>
                </div>
                <button class="hoa-button hoa-button-primary hoa-portal-auth-submit" type="submit" data-i18n="Email reset link">{{ __('Email reset link') }}</button>
            </form>
            <nav class="hoa-portal-auth-links" aria-label="Account recovery navigation"><a href="{{ route('homeowner.login') }}">← Back to login</a><a href="{{ route('homeowner.register') }}" data-i18n="Create an account">{{ __('Create an account') }}</a></nav>
        </section>
    </main>
</body>
</html>
