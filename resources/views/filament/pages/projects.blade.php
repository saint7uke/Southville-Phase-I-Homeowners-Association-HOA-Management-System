<x-filament-panels::page>
    <p>{{ __('Planned and In Progress projects are saved privately. Only Completed projects can be published.') }} {{ __('Only published, completed projects appear on the public website. Restored projects require publication again.') }}</p>
    {{ $this->table }}
</x-filament-panels::page>
