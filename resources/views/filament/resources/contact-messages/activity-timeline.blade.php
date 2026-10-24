<div class="space-y-3">
    @forelse($activities as $activity)
        @php
            $appearance = match ($activity->type) {
                'outbound_reply' => ['label' => __('Email reply'), 'icon' => 'heroicon-o-paper-airplane', 'class' => 'border-s-sky-500'],
                'reopen_reason' => ['label' => __('Reopen reason'), 'icon' => 'heroicon-o-arrow-uturn-left', 'class' => 'border-s-amber-500'],
                default => ['label' => __('Internal note'), 'icon' => 'heroicon-o-pencil-square', 'class' => 'border-s-gray-400'],
            };
            $deliveryClass = match ($activity->delivery_status) {
                'Sent' => 'bg-green-50 text-green-700 dark:bg-green-950 dark:text-green-300',
                'Failed' => 'bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-300',
                default => 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
            };
        @endphp
        <article class="rounded-lg border border-s-4 border-gray-200 p-4 dark:border-gray-700 {{ $appearance['class'] }}">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div class="flex min-w-0 items-center gap-2">
                    <x-filament::icon :icon="$appearance['icon']" class="size-5 shrink-0 text-gray-500" aria-hidden="true" />
                    <strong class="break-words">{{ $appearance['label'] }}</strong>
                </div>
                <time class="text-sm text-gray-500 dark:text-gray-400" datetime="{{ $activity->created_at?->toIso8601String() }}">{{ $activity->created_at?->format('M d, Y g:i A') }}</time>
            </div>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $activity->author?->full_name ?? __('Archived user') }}</p>
            @if($activity->subject)<p class="mt-3 break-words font-medium">{{ $activity->subject }}</p>@endif
            <p class="mt-2 whitespace-pre-wrap break-words leading-6">{{ $activity->body }}</p>
            @if($activity->type === 'outbound_reply')
                <p class="mt-3 text-sm">{{ __('Delivery') }}: <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $deliveryClass }}">{{ __($activity->delivery_status ?? 'Queued') }}</span></p>
            @endif
        </article>
    @empty
        <p class="text-sm text-gray-600 dark:text-gray-300">{{ __('No internal activity yet.') }}</p>
    @endforelse
</div>
