<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Status link expired | Southville Phase I HOA</title>
    <x-favicon />
    @vite(['resources/css/landing.css'])
</head>
<body class="hoa-landing">
    <main class="hoa-auth-shell">
        <div class="hoa-auth-overlay"></div>
        <section class="hoa-auth-card hoa-auth-card-confirmation" aria-labelledby="status-link-title">
            <a href="{{ route('home') }}" class="hoa-auth-brand" aria-label="Go to homepage">
                <span class="hoa-brand-mark" aria-hidden="true">S1</span>
                <span class="hoa-auth-brand-title">Southville Phase I</span>
            </a>
            <div class="hoa-status-icon" aria-hidden="true">!</div>
            <p class="hoa-eyebrow"><span></span>Application status</p>
            <h1 id="status-link-title">This status link has expired</h1>
            <p class="hoa-auth-lead">For your security, registration status links are temporary and can no longer be used.</p>
            <p class="hoa-auth-lead">Sign in with your homeowner account to view the current application status securely.</p>
            <a class="hoa-button hoa-button-primary hoa-button-block" href="{{ $loginUrl }}">Continue to homeowner login</a>
            <a class="hoa-button hoa-button-secondary hoa-button-block" href="{{ route('home') }}">Return to homepage</a>
        </section>
    </main>
</body>
</html>
