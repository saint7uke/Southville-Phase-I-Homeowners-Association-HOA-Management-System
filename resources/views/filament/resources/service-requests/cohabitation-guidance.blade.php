@php
    $guidance = match ($serviceRequest->status) {
        'Processing' => 'The HOA is reviewing your Cohabitation request. Certificate preparation starts after approval.',
        'Approved' => 'Your Cohabitation request is approved. Authorized HOA staff will complete the required details and prepare your certificate.',
        'Rejected' => 'This Cohabitation request was rejected. Review the HOA remarks or contact the HOA for guidance.',
        'Completed' => 'This Cohabitation request is completed. Use the saved certificate when available; a new draft is not required.',
        default => 'Your Cohabitation request is awaiting HOA review. Certificate preparation starts after approval.',
    };
@endphp
<div class="space-y-2 text-sm leading-relaxed">
    <p data-i18n="{{ $guidance }}">{{ __($guidance) }}</p>
    @if (in_array($serviceRequest->status, ['Pending', 'Processing', 'Approved'], true))
        <p data-i18n="The original certificate requires verified birth dates, completed years living together, and the requesting person. These details are completed in Certificates after approval.">{{ __('The original certificate requires verified birth dates, completed years living together, and the requesting person. These details are completed in Certificates after approval.') }}</p>
    @endif
</div>
