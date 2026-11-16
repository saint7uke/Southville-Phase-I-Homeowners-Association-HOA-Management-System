<div>
    <p data-i18n="Certificates must be collected at the HOA office with the required signature and dry seal.">{{ __('Certificates must be collected at the HOA office with the required signature and dry seal.') }}</p>
    @if ($standalone->isNotEmpty())
        <details><summary data-i18n="Previously issued certificates">{{ __('Previously issued certificates') }}</summary>
            @foreach ($standalone as $certificate)
                <p>{{ $certificate->type }} · {{ __($certificate->status) }} · {{ __($certificate->collected_at ? 'Collected' : ($certificate->ready_for_pickup_at ? 'Ready for pickup' : 'Awaiting preparation')) }}</p>
            @endforeach
        </details>
    @endif
</div>
