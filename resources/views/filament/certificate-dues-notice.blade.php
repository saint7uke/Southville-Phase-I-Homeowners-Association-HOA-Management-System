@if ($result)
<div role="status" aria-live="polite">
    <p><strong>{{ __('Certificate dues eligibility') }}</strong></p>
    <p>{{ $result['message'] ? __($result['message']) : __('Your assessed monthly dues are settled. You may request a certificate.') }}</p>
    @if (($homeownerView ?? false))
        <p><a href="{{ route('filament.homeowner.resources.dues.dues-obligations.index') }}">{{ __('Review outstanding dues') }}</a></p>
    @endif
</div>
@endif
