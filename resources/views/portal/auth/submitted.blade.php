<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __($statusTitle) }} | Southville Phase I HOA</title>
    <x-favicon />
    @vite(['resources/css/landing.css', 'resources/js/app.js'])
</head>
<body class="hoa-landing hoa-submitted-page">
    <x-alert-feedback />
    <a class="hoa-submitted-skip" href="#submitted-title">{{ __('Skip to main content') }}</a>
    <main class="hoa-auth-shell hoa-submitted-shell">
        <div class="hoa-auth-overlay" aria-hidden="true"></div>
        <section class="hoa-auth-card hoa-auth-card-confirmation" aria-labelledby="submitted-title">
            <x-locale-toolbar appearance="light" />
            <a href="{{ route('home') }}" class="hoa-auth-brand" aria-label="Go to homepage">
                <img class="hoa-submitted-logo" src="{{ asset('images/HOA.png') }}" alt="{{ __('Southville Phase I HOA logo') }}" width="72" height="72">
                <span class="hoa-auth-brand-title">Southville Phase I</span>
            </a>
            <div class="hoa-status-icon hoa-submitted-icon-{{ $status }}" aria-hidden="true">
                @if ($status === 'approved')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 12 4 4L19 6" /></svg>
                @elseif ($status === 'rejected')
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v9m0 3v2" /></svg>
                @else
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></svg>
                @endif
            </div>
            <p class="hoa-eyebrow"><span></span><span data-i18n="Application status">{{ __('Application status') }}</span></p>
            <h1 id="submitted-title" tabindex="-1" data-i18n="{{ $statusTitle }}">{{ __($statusTitle) }}</h1>
            <p class="hoa-auth-lead" data-i18n="{{ $statusLead }}">{{ __($statusLead) }}</p>
            <div class="hoa-status-pill hoa-status-pill-{{ $status }}" role="status"><span aria-hidden="true"></span><span data-i18n="{{ $statusLabel }}">{{ __($statusLabel) }}</span></div>

            @if($registeredEmail)
                <p class="hoa-hint"><span data-i18n="Application email:">{{ __('Application email:') }}</span> <strong>{{ $registeredEmail }}</strong></p>
            @endif

            <div class="hoa-submitted-details">
            @if($approvedAt)
                <p class="hoa-hint"><span data-i18n="Approved on">{{ __('Approved on') }}</span> <strong>{{ $approvedAt->timezone(config('app.timezone'))->format('F j, Y \a\t g:i A') }}</strong>.</p>
                <p class="hoa-auth-lead"><span data-i18n="You may now sign in using the email and password you registered with.">{{ __('You may now sign in using the email and password you registered with.') }}</span></p>
            @elseif($feedback)
                <div class="hoa-auth-alert" role="alert">
                    <strong data-i18n="Admin feedback">{{ __('Admin feedback') }}</strong>
                    <p>{{ $feedback }}</p>
                </div>
                <p class="hoa-auth-lead"><span data-i18n="Please contact the HOA office for instructions on correcting and resubmitting your application.">{{ __('Please contact the HOA office for instructions on correcting and resubmitting your application.') }}</span></p>
            @else
                <p class="hoa-auth-lead"><span data-i18n="Please check again after the HOA Admin has reviewed your registration and documents.">{{ __('Please check again after the HOA Admin has reviewed your registration and documents.') }}</span></p>
            @endif
            </div>
            <nav class="hoa-submitted-actions" aria-label="{{ __('Application status actions') }}">
            @if($approvedAt)
                <a class="hoa-button hoa-button-primary hoa-button-block" href="{{ route('homeowner.login') }}" data-i18n="Continue to homeowner login">{{ __('Continue to homeowner login') }}</a>
            @elseif(! $feedback)
                <a class="hoa-button hoa-button-primary hoa-button-block" href="{{ route('homeowner.login') }}" data-i18n="Return to login">{{ __('Return to login') }}</a>
            @endif
            <a class="hoa-button hoa-button-secondary hoa-button-block" href="{{ $statusUrl }}" data-i18n="Refresh status">{{ __('Refresh status') }}</a>
            </nav>
        </section>
    </main>
</body>
</html>
