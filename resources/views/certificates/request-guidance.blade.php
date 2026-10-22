<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Cohabitation certificate preparation') }} | {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/locale.js'])
</head>
<body class="min-h-screen bg-gray-50 text-gray-950 dark:bg-gray-950 dark:text-white">
    <x-locale-toolbar />
    <main class="px-4 py-8 sm:px-6">
        <section class="mx-auto max-w-xl space-y-5 rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900" aria-labelledby="request-guidance-heading">
            <h1 id="request-guidance-heading" class="text-xl font-semibold" data-i18n="Cohabitation certificate preparation">{{ __('Cohabitation certificate preparation') }}</h1>
            <dl class="space-y-2 text-sm">
                <div><dt class="font-semibold" data-i18n="Ticket">{{ __('Ticket') }}</dt><dd class="break-words">{{ $serviceRequest->ticket_number }}</dd></div>
                <div><dt class="font-semibold" data-i18n="Current status">{{ __('Current status') }}</dt><dd data-i18n="{{ $serviceRequest->status }}">{{ __($serviceRequest->status) }}</dd></div>
            </dl>
            @include('filament.resources.service-requests.cohabitation-guidance')
            <a class="inline-flex min-h-11 items-center rounded font-medium underline focus-visible:outline-2 focus-visible:outline-offset-4" href="{{ $returnUrl }}" tabindex="0" data-i18n="Back to requests">{{ __('Back to requests') }}</a>
        </section>
    </main>
</body>
</html>
