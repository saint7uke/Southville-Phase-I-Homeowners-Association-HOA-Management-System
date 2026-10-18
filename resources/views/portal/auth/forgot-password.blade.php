<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot password | Southville Phase I HOA</title>
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
            <p class="hoa-portal-auth-portal">Homeowner portal</p>
            <h1>Secure account recovery.</h1>
            <p class="hoa-portal-auth-support">Request a secure password reset link using the email address connected to your homeowner account.</p>
        </aside>
        <section class="hoa-portal-auth-form">
            <div class="hoa-portal-auth-heading">
                <p class="hoa-portal-auth-eyebrow">Account recovery</p>
                <h2 id="forgot-title">Forgot your password?</h2>
                <p>Enter your email address and we’ll send a secure reset link.</p>
            </div>
            @if(session('status'))<div class="hoa-contact-success" role="status">{{ session('status') }}</div>@endif
            @if($errors->any())<div class="hoa-contact-error" role="alert">{{ $errors->first() }}</div>@endif
            <form method="POST" action="{{ route('homeowner.password.email') }}" class="hoa-portal-auth-fields">
                @csrf
                <div class="hoa-field">
                    <label for="email">Email address</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" autofocus>
                </div>
                <button class="hoa-button hoa-button-primary hoa-portal-auth-submit" type="submit">Email reset link</button>
            </form>
            <nav class="hoa-portal-auth-links" aria-label="Account recovery navigation"><a href="{{ route('homeowner.login') }}">← Back to login</a><a href="{{ route('homeowner.register') }}">Create an account</a></nav>
        </section>
    </main>
</body>
</html>
