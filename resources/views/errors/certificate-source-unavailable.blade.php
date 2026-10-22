<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Original Cohabitation document') }} | {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body>
    <main class="min-h-screen bg-gray-50 p-6 text-gray-950 dark:bg-gray-950 dark:text-white">
        <section class="mx-auto max-w-xl space-y-5 rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900" aria-labelledby="source-heading">
            <h1 id="source-heading" class="text-xl font-semibold">{{ __('Original Cohabitation document') }}</h1>
            @include('filament.resources.certificates.cohabitation-source-status', ['showReviewLink' => false])
            @if ($status['state'] !== 'awaiting')
                <p>{{ $message }}</p>
            @endif
            <a class="inline-block font-medium underline" href="{{ $returnUrl }}">{{ __('Back to Certificates') }}</a>
        </section>
    </main>
</body>
</html>
