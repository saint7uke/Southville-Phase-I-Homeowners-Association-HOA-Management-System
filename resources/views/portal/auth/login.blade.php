<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Sign in') }} | Southville Phase I HOA</title>
    <x-favicon />
    @vite(['resources/css/landing.css', 'resources/js/app.js'])
</head>
<body class="hoa-landing hoa-auth-page">
    <main class="hoa-portal-auth" aria-labelledby="homeowner-login-title">
        <aside class="hoa-portal-auth-intro">
            <a class="hoa-portal-auth-brand" href="{{ route('home') }}" aria-label="Go to Southville Phase I HOA homepage">
                <span class="hoa-portal-auth-logo"><img src="{{ asset('images/HOA.png') }}" alt=""></span>
                <span>Southville Phase I HOA</span>
            </a>
            <p class="hoa-portal-auth-portal" data-i18n="Community portal">{{ __('Community portal') }}</p>
            <h1><span data-i18n="Stay connected to your home and HOA community.">{{ __('Stay connected to your home and HOA community.') }}</span></h1>
            <p class="hoa-portal-auth-support"><span data-i18n="Use your secure account to view dues, requests, announcements, and community services.">{{ __('Use your secure account to view dues, requests, announcements, and community services.') }}</span></p>
            <div class="hoa-portal-auth-rule" aria-hidden="true"></div>
            <p class="hoa-portal-auth-note"><span data-i18n="Secure access for Southville Phase I HOA residents, staff, and administrators.">{{ __('Secure access for Southville Phase I HOA residents, staff, and administrators.') }}</span></p>
        </aside>

        <section class="hoa-portal-auth-form">
            <x-locale-toolbar />
            <div class="hoa-portal-auth-heading">
                <p class="hoa-portal-auth-eyebrow" data-i18n="SHOAI portal">{{ __('SHOAI portal') }}</p>
                <h2 id="homeowner-login-title" data-i18n="Sign in">{{ __('Sign in') }}</h2>
                <p>{{ __('Sign in to your HOA account. Your role determines your dashboard.') }}</p>
            </div>

            @if(session('success'))
                <div class="hoa-contact-success hoa-portal-auth-alert" role="status">{{ session('success') }}</div>
            @endif

            @if($errors->any())
                <div class="hoa-contact-error hoa-portal-auth-alert" role="alert">
                    <strong data-i18n="Please correct the following:">{{ __('Please correct the following:') }}</strong>
                    <ul>
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form class="hoa-portal-auth-fields" method="POST" action="{{ route('login.store') }}" data-auth-submit>
                @csrf
                <div class="hoa-field">
                    <label for="email" data-i18n="Email address">{{ __('Email address') }}</label>
                    <input id="email" name="email" type="email" value="{{ old('email', session('registered_email')) }}" required autocomplete="email" @error('email') aria-describedby="email-error" aria-invalid="true" @enderror>
                    @error('email')<p id="email-error" class="hoa-error-text">{{ $message }}</p>@enderror
                </div>

                <div class="hoa-field">
                    <label for="password" data-i18n="Password">{{ __('Password') }}</label>
                    <x-auth.password-toggle input-id="password">
                        <input id="password" name="password" type="password" required autocomplete="current-password" @error('password') aria-describedby="password-error" aria-invalid="true" @enderror>
                    </x-auth.password-toggle>
                    @error('password')<p id="password-error" class="hoa-error-text">{{ $message }}</p>@enderror
                </div>

                <div class="hoa-portal-auth-options">
                    <x-auth.checkbox name="remember"><span data-i18n="Remember me">{{ __('Remember me') }}</span></x-auth.checkbox>
                    <a href="{{ route('homeowner.password.request') }}" data-i18n="Forgot password?">{{ __('Forgot password?') }}</a>
                </div>

                <button class="hoa-button hoa-button-primary hoa-portal-auth-submit" type="submit" data-i18n="Sign in">{{ __('Sign in') }}</button>
            </form>

            <nav class="hoa-portal-auth-links" aria-label="Login navigation">
                <span data-i18n="Need an account?">{{ __('Need an account?') }}</span>
                <a href="{{ route('homeowner.register') }}" data-i18n="Register">{{ __('Register') }}</a>
                <span aria-hidden="true">·</span>
                <a href="{{ route('home') }}">{{ __('Home') }}</a>
                <span aria-hidden="true">·</span>
                <span>{{ __('For homeowners, staff, and administrators') }}</span>
            </nav>
        </section>
    </main>
</body>
</html>
