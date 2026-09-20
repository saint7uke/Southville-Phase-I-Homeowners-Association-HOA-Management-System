<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold">Welcome, {{ $user?->first_name }}.</h2>
                <p class="text-sm text-gray-500">@if($homeowner)Block {{ $homeowner->block }}, Lot {{ $homeowner->lot }} · {{ $homeowner->street }}@elseYour homeowner record is not linked yet.@endif</p>
            </div>
            <div class="flex flex-wrap gap-2" aria-label="Quick actions">
                <x-filament::button tag="a" href="/homeowner/dues/dues-obligations">Upload payment proof</x-filament::button>
                <x-filament::button tag="a" href="/homeowner/complaints/create" color="gray">Submit complaint</x-filament::button>
                <x-filament::button tag="a" href="/homeowner/service-requests/create" color="gray">New request</x-filament::button>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
