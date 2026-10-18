<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-[0.14em] text-sky-700 dark:text-sky-300">Southville Phase I HOA</p>
                <h2 class="mt-2 text-2xl font-bold tracking-tight text-gray-950 dark:text-white">
                    {{ $greeting }}, {{ $firstName }}
                </h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                    {{ $panel === 'staff' ? "Here's an overview of today's community operations." : "Here's an overview of your HOA community." }}
                </p>
            </div>
            <time class="text-sm font-medium text-gray-600 dark:text-gray-300" datetime="{{ now()->toDateString() }}">{{ now()->format('l, F j, Y') }}</time>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
