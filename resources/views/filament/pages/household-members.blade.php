<x-filament-panels::page>
    <p>{{ __('PROVISIONAL RBI — subject to HOA confirmation') }}</p>
    <p>{{ \App\Models\Household::withTrashed()->find($householdId)?->full_address }}</p>
    @php($headStatus = $this->headStatus())
    <p><strong>{{ __('Household head') }}:</strong> {{ $headStatus['head_name'] ?? __('Not assigned') }}</p>
    @if($headStatus['warning']) <p role="status">{{ $headStatus['warning'] }}</p> @endif
    {{ $this->table }}
</x-filament-panels::page>
