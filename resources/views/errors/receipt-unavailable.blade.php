<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Receipt unavailable') }} | {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body>
    <main class="min-h-screen bg-gray-50 p-6 text-gray-950 dark:bg-gray-950 dark:text-white">
        <section class="mx-auto max-w-xl space-y-4 rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900" aria-labelledby="receipt-error-heading">
            <h1 id="receipt-error-heading" class="text-xl font-semibold">{{ __('Receipt unavailable') }}</h1>
            <p role="status">{{ $message }}</p>
            @if ($generationUrl)
                <a class="font-medium text-primary-700 underline dark:text-primary-400" href="{{ $generationUrl }}">{{ __('Generate monthly receipt') }}</a>
            @endif
        </section>
    </main>
</body>
</html>
