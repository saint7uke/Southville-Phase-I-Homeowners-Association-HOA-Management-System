<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.14em] text-sky-700 dark:text-sky-300">Your home and community</p>
                <h2 class="mt-2 text-xl font-semibold text-gray-950 dark:text-white">{{ $greeting }}, {{ $user?->first_name }}.</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">@if($homeowner)Block {{ $homeowner->block }}, Lot {{ $homeowner->lot }} &middot; {{ $homeowner->street }}@elseYour homeowner record is not linked yet.@endif</p>
            </div>
            <div class="flex flex-wrap gap-2" aria-label="Quick actions">
                <x-filament::button tag="a" href="{{ route('filament.homeowner.resources.dues.dues-obligations.index') }}">View dues</x-filament::button>
                <x-filament::button tag="a" href="{{ route('filament.homeowner.resources.complaints.create') }}" color="gray">Submit complaint</x-filament::button>
                <x-filament::button tag="a" href="{{ route('filament.homeowner.resources.service-requests.create') }}" color="gray">New request</x-filament::button>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
