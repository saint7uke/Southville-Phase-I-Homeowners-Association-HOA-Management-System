<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Homeowner Login | Southville Phase I HOA</title>
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
            <p class="hoa-portal-auth-portal">Homeowner portal</p>
            <h1>Stay connected to your home and HOA community.</h1>
            <p class="hoa-portal-auth-support">Use your secure account to view dues, requests, announcements, and community services.</p>
            <div class="hoa-portal-auth-rule" aria-hidden="true"></div>
            <p class="hoa-portal-auth-note">Secure access for Southville Phase I HOA homeowners.</p>
        </aside>

        <section class="hoa-portal-auth-form">
            <div class="hoa-portal-auth-heading">
                <p class="hoa-portal-auth-eyebrow">Homeowner portal</p>
                <h2 id="homeowner-login-title">Homeowner Login</h2>
                <p>Sign in to access your homeowner account.</p>
            </div>

            @if(session('success'))
                <div class="hoa-contact-success hoa-portal-auth-alert" role="status">{{ session('success') }}</div>
            @endif

            @if($errors->any())
                <div class="hoa-contact-error hoa-portal-auth-alert" role="alert">
                    <strong>Please correct the following:</strong>
                    <ul>
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form class="hoa-portal-auth-fields" method="POST" action="{{ route('homeowner.login.store') }}" data-auth-submit>
                @csrf
                <div class="hoa-field">
                    <label for="email">Email address</label>
                    <input id="email" name="email" type="email" value="{{ old('email', session('registered_email')) }}" required autocomplete="email" @error('email') aria-describedby="email-error" aria-invalid="true" @enderror>
                    @error('email')<p id="email-error" class="hoa-error-text">{{ $message }}</p>@enderror
                </div>

                <div class="hoa-field">
                    <label for="password">Password</label>
                    <x-auth.password-toggle input-id="password">
                        <input id="password" name="password" type="password" required autocomplete="current-password" @error('password') aria-describedby="password-error" aria-invalid="true" @enderror>
                    </x-auth.password-toggle>
                    @error('password')<p id="password-error" class="hoa-error-text">{{ $message }}</p>@enderror
                </div>

                <div class="hoa-portal-auth-options">
                    <x-auth.checkbox name="remember">Remember me</x-auth.checkbox>
                    <a href="{{ route('homeowner.password.request') }}">Forgot password?</a>
                </div>

                <button class="hoa-button hoa-button-primary hoa-portal-auth-submit" type="submit">Homeowner Login</button>
            </form>

            <nav class="hoa-portal-auth-links" aria-label="Login navigation">
                <span>Need an account?</span>
                <a href="{{ route('homeowner.register') }}">Register</a>
                <span aria-hidden="true">·</span>
                <a href="/admin/login">Admin Login</a>
                <span aria-hidden="true">·</span>
                <a href="/staff/login">Staff Login</a>
            </nav>
        </section>
    </main>
</body>
</html>
