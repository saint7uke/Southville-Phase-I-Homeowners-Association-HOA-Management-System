<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ __('Export unavailable') }}</title></head>
<body style="font-family: system-ui, sans-serif; max-width: 42rem; margin: 3rem auto; padding: 1rem; color: #13293d; background: #fff;">
    <main><h1>{{ __('Export unavailable') }}</h1>
        <p role="alert">{{ __('The export letterhead is unavailable. Please contact the administrator.') }}</p>
        <a href="{{ $returnUrl }}">{{ __('Return to records') }}</a>
    </main>
</body>
</html>
