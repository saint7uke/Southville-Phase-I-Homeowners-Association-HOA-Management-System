@php($guidance = app(\App\Services\ServiceRequestWorkflow::class)->guidanceKey($serviceRequest))
<span data-i18n="{{ $guidance }}">{{ __($guidance) }}</span>
