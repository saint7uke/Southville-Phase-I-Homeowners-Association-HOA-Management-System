<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset password | Southville Phase I HOA</title>
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
            <p class="hoa-portal-auth-portal">Homeowner portal</p>
            <h1>Choose a strong password.</h1>
            <p class="hoa-portal-auth-support">Use a password you do not reuse elsewhere to keep your HOA account protected.</p>
        </aside>
        <section class="hoa-portal-auth-form">
            <div class="hoa-portal-auth-heading">
                <p class="hoa-portal-auth-eyebrow">Account recovery</p>
                <h2 id="reset-title">Create a new password</h2>
                <p>Set a new password for your homeowner account.</p>
            </div>
            @if($errors->any())<div class="hoa-contact-error" role="alert">{{ $errors->first() }}</div>@endif
            <form method="POST" action="{{ route('homeowner.password.update') }}" class="hoa-portal-auth-fields">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div class="hoa-field">
                    <label for="email">Email address</label>
                    <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="email">
                </div>
                <div class="hoa-field">
                    <label for="password">New password</label>
                    <x-auth.password-toggle input-id="password">
                        <input id="password" name="password" type="password" required autocomplete="new-password" aria-describedby="password-hint">
                    </x-auth.password-toggle>
                    <p id="password-hint" class="hoa-hint">At least 12 characters with letters and numbers.</p>
                </div>
                <div class="hoa-field">
                    <label for="password_confirmation">Confirm new password</label>
                    <x-auth.password-toggle input-id="password_confirmation">
                        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password">
                    </x-auth.password-toggle>
                </div>
                <button class="hoa-button hoa-button-primary hoa-portal-auth-submit" type="submit">Reset password</button>
            </form>
            <nav class="hoa-portal-auth-links" aria-label="Account recovery navigation"><a href="{{ route('homeowner.login') }}">← Back to login</a></nav>
        </section>
    </main>
</body>
</html>
