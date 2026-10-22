<div class="space-y-3">
    @if($this->receiptDocumentId)
        <div class="flex flex-wrap gap-4">
            <a class="text-primary-700 underline dark:text-primary-400" href="{{ route('certificates.butaw.download', ['paymentDocument' => $this->receiptDocumentId, 'view' => 1]) }}" target="_blank" rel="noopener">{{ __('View / Print') }}</a>
            <a class="text-primary-700 underline dark:text-primary-400" href="{{ route('certificates.butaw.download', $this->receiptDocumentId) }}">{{ __('Download') }}</a>
        </div>
    @elseif($this->previewToken)
        <p data-i18n="Draft only. Confirm generation after reviewing the completed receipt.">{{ __('Draft only. Confirm generation after reviewing the completed receipt.') }}</p>
        <a class="text-primary-700 underline dark:text-primary-400" href="{{ route('certificates.butaw.preview', $this->previewToken) }}" target="_blank" rel="noopener">{{ __('Open receipt preview') }}</a>
        <iframe title="{{ __('Monthly dues receipt preview') }}" src="{{ route('certificates.butaw.preview', $this->previewToken) }}" class="w-full rounded border" style="height:65vh"></iframe>
    @endif
</div>
