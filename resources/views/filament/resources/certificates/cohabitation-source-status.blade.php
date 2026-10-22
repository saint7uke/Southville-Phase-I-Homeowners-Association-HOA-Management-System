<div class="space-y-3 rounded-xl border border-gray-200 p-4 text-sm text-gray-700 dark:border-gray-700 dark:text-gray-200" role="status" aria-live="polite" aria-atomic="true">
    <p class="font-semibold text-gray-950 dark:text-white" data-i18n="{{ $status['label'] }}">{{ __($status['label']) }}</p>
    <p data-i18n="{{ $status['message'] }}">{{ __($status['message']) }}</p>
    @if ($status['requirements'])
        <ul class="list-disc space-y-2 break-words pl-5">
            @foreach ($status['requirements'] as $requirement)
                <li data-i18n="{{ $requirement }}">{{ __($requirement) }}</li>
            @endforeach
        </ul>
    @endif
    @if ($showReviewLink && $status['state'] === 'ready')
        <a class="font-medium underline" href="{{ route('certificates.cohabitation-reference') }}" target="_blank" rel="noopener" data-i18n="Review original Cohabitation document">{{ __('Review original Cohabitation document') }}</a>
    @endif
</div>
