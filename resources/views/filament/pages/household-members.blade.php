<x-filament-panels::page>
    <p>{{ __('PROVISIONAL RBI — subject to HOA confirmation') }}</p>
    <p>{{ \App\Models\Household::withTrashed()->find($householdId)?->full_address }}</p>
    {{ $this->table }}
</x-filament-panels::page>
