<div class="space-y-3" role="region" aria-label="{{ __('Completed certificate preview') }}">
    <p role="status" data-i18n="Preview only. Confirm issuance to save the official certificate. Changes require a fresh preview; previews expire after 15 minutes.">{{ __('Preview only. Confirm issuance to save the official certificate. Changes require a fresh preview; previews expire after 15 minutes.') }}</p>
    <a class="underline" data-i18n="Open completed preview" href="{{ route('certificates.residency-preview', ['token' => $getLivewire()->previewToken]) }}" target="_blank" rel="noopener">{{ __('Open completed preview') }}</a>
    <iframe class="w-full rounded-lg border" style="height:65vh;min-height:360px" title="{{ __('Completed certificate preview') }}" src="{{ route('certificates.residency-preview', ['token' => $getLivewire()->previewToken]) }}"></iframe>
</div>
