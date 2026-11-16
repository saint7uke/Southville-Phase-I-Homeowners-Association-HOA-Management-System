@php($complaint = $detail instanceof \App\Models\Complaint)
<div class="hoa-concern-detail" data-concern-detail>
    <h3 class="text-lg font-semibold">{{ $detail->subject }}</h3>
    <dl class="grid gap-3 sm:grid-cols-2">
        <div><dt data-i18n="Type">{{ __('Type') }}</dt><dd data-i18n="{{ $complaint ? 'Complaint' : 'Inquiry' }}">{{ __($complaint ? 'Complaint' : 'Inquiry') }}</dd></div>
        <div><dt data-i18n="Reference">{{ __('Reference') }}</dt><dd>{{ $complaint ? $detail->ticket_number : $detail->reference }}</dd></div>
        <div><dt data-i18n="Status">{{ __('Status') }}</dt><dd data-i18n="{{ $detail->status }}">{{ __($detail->status) }}</dd></div>
        <div><dt data-i18n="Submitted">{{ __('Submitted') }}</dt><dd>{{ $detail->created_at->format('M d, Y g:i A') }}</dd></div>
    </dl>
    <h3 class="font-semibold" data-i18n="Description">{{ __('Description') }}</h3>
    <p class="hoa-concern-body">{{ $complaint ? $detail->description : $detail->message }}</p>
    @if ($complaint)
        @foreach ($detail->caseAttachments as $attachment)
            <div class="hoa-concern-attachment" wire:key="concern-attachment-{{ $attachment->id }}">
            <p class="hoa-record-file"><span>{{ $attachment->original_name }}</span><x-record-icon :label="__('Download')" label-key="Download" icon="heroicon-o-document-arrow-down" :href="route('case-attachments.download', $attachment)" /></p>
            @if (in_array($attachment->mime_type, ['image/jpeg', 'image/png'], true))
                <div x-data="{ state: 'loading' }" x-init="$nextTick(() => { if ($refs.picture.complete) state = $refs.picture.naturalWidth ? 'loaded' : 'failed' })" data-concern-image>
                    <p x-show="state === 'loading'" role="status" data-i18n="Loading picture…">{{ __('Loading picture…') }}</p>
                    <img x-ref="picture" src="{{ route('case-attachments.preview', $attachment) }}"
                        alt="{{ __('Attached picture') }}: {{ $attachment->original_name }}"
                        data-concern-image-name="{{ $attachment->original_name }}"
                        x-on:load="state = 'loaded'" x-on:error="state = 'failed'"
                        x-show="state !== 'failed'" class="hoa-concern-picture" />
                    <p x-show="state === 'failed'" x-cloak role="status" data-i18n="Picture preview unavailable. You can still try downloading the attachment.">{{ __('Picture preview unavailable. You can still try downloading the attachment.') }}</p>
                </div>
            @endif
            </div>
        @endforeach
        @if ($detail->admin_remarks)<h3 class="font-semibold" data-i18n="Resolution notes">{{ __('Resolution notes') }}</h3><p class="hoa-concern-body">{{ $detail->admin_remarks }}</p>@endif
    @else
        @foreach ($detail->activities as $reply)<h3 class="font-semibold" data-i18n="HOA reply">{{ __('HOA reply') }}</h3><p class="hoa-concern-body">{{ $reply->body }}</p>@endforeach
    @endif
</div>
