<!doctype html><html lang="{{ app()->getLocale() }}"><head><meta charset="utf-8"><style>
@page { margin: 25mm; } body { font-family: DejaVu Sans, sans-serif; line-height: 1.7; }
.watermark { position: fixed; top: 38%; color: #ddd; font-size: 55px; transform: rotate(-30deg); }
</style></head><body>
    <div class="watermark">{{ __('DRAFT — NOT VALID') }}</div>
<h1>{{ $title }}</h1><p><strong>{{ __('DRAFT — not an issued certificate. HOA approval is required.') }}</strong></p>
<p>{!! nl2br(e($body)) !!}</p>
<p>{{ __('No official certificate number has been allocated.') }}</p>
</body></html>
