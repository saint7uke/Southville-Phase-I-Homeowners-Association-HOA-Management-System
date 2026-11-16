<x-filament-panels::page>
    <div class="hoa-concern-filters">
        <label><span data-i18n="Type">{{ __('Type') }}</span><select wire:model.live="typeFilter"><option value="" data-i18n="All types">{{ __('All types') }}</option><option value="Complaint" data-i18n="Complaint">{{ __('Complaint') }}</option><option value="Inquiry" data-i18n="Inquiry">{{ __('Inquiry') }}</option></select></label>
        <label><span data-i18n="Status">{{ __('Status') }}</span><select wire:model.live="statusFilter"><option value="" data-i18n="All statuses">{{ __('All statuses') }}</option>@foreach (['Pending', 'Under Review', 'Resolved', 'Rejected', 'New', 'In Progress', 'Awaiting Resident', 'Closed'] as $status)<option value="{{ $status }}">{{ __($status) }}</option>@endforeach</select></label>
    </div>
    <div class="hoa-concern-list">
        @forelse ($concerns as $concern)
            <article wire:key="{{ $concern->kind }}-{{ $concern->id }}">
                <div><strong>{{ $concern->subject }}</strong><p>{{ __($concern->kind) }} · {{ $concern->reference }}</p></div>
                <div><span>{{ __($concern->status) }}</span><p>{{ \Illuminate\Support\Carbon::parse($concern->created_at)->format('M d, Y g:i A') }}</p></div>
                <x-record-icon :label="__('View details')" label-key="View details" wire:click="mountAction('viewConcern', { concern: '{{ $concern->kind }}:{{ $concern->id }}' })" wire:loading.attr="disabled" wire:target="mountAction" />
            </article>
        @empty
            <p data-i18n="No concerns submitted yet.">{{ __('No concerns submitted yet.') }}</p>
        @endforelse
    </div>
    {{ $concerns->links() }}
</x-filament-panels::page>
