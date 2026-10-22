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
            <p class="hoa-portal-auth-portal" data-i18n="Homeowner registration">{{ __('Homeowner registration') }}</p>
            <h1 data-i18n="Too many requests">{{ __('Too many requests') }}</h1>
            <p><span data-i18n="Please wait a moment before submitting again. This protection helps keep registration accounts safe.">{{ __('Please wait a moment before submitting again. This protection helps keep registration accounts safe.') }}</span></p>
            <p><span data-i18n="You can return to the registration form and try again after the cooldown.">{{ __('You can return to the registration form and try again after the cooldown.') }}</span></p>
            <a class="hoa-button hoa-button-primary" href="{{ route('homeowner.register') }}" data-i18n="Return to registration">{{ __('Return to registration') }}</a>
        </section>
    </main>
</body>
</html>
