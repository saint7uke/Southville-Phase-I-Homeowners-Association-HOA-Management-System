<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Too many requests | {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body>
    <main style="min-height: 100vh; display: grid; place-items: center; padding: 2rem; background: var(--hoa-surface, #f5f7fb); color: var(--hoa-ink, #172033);">
        <section class="hoa-auth-card" style="max-width: 34rem; text-align: center;">
            <p class="hoa-portal-auth-portal">Homeowner registration</p>
            <h1>Too many requests</h1>
            <p>Please wait a moment before submitting again. This protection helps keep registration accounts safe.</p>
            <p>You can return to the registration form and try again after the cooldown.</p>
            <a class="hoa-button hoa-button-primary" href="{{ route('homeowner.register') }}">Return to registration</a>
        </section>
    </main>
</body>
</html>
