<x-filament-panels::page>
    <div class="hoa-household">
        <aside class="hoa-household__notice" aria-labelledby="household-provisional-heading">
            <x-filament::icon icon="heroicon-o-information-circle" class="size-5 shrink-0" aria-hidden="true" />
            <p id="household-provisional-heading" data-i18n="PROVISIONAL RBI — subject to HOA confirmation">{{ __('PROVISIONAL RBI — subject to HOA confirmation') }}</p>
        </aside>

        @if ($summary)
            <x-filament::section>
                <x-slot name="heading"><span data-i18n="Household overview">{{ __('Household overview') }}</span></x-slot>
                <div class="hoa-household__overview">
                    <dl class="hoa-household__details">
                        <div>
                            <dt data-i18n="Property address">{{ __('Property address') }}</dt>
                            <dd class="hoa-household__address">{{ $summary['address'] }}</dd>
                        </div>
                        <div>
                            <dt data-i18n="Household ID">{{ __('Household ID') }}</dt>
                            <dd>{{ $summary['identifier'] }}</dd>
                        </div>
                    </dl>
                    <div class="hoa-household__count">
                        <x-filament::icon icon="heroicon-o-users" class="size-6" aria-hidden="true" />
                        <strong>{{ $summary['members_count'] }}</strong>
                        <span data-i18n="Current members">{{ __('Current members') }}</span>
                    </div>
                </div>
                <div class="hoa-household__download">
                    <x-filament::button tag="a" :href="route('households.pdf', $summary['id'])" icon="heroicon-o-arrow-down-tray" color="gray">
                        <span data-i18n="Download household summary">{{ __('Download household summary') }}</span>
                    </x-filament::button>
                    <p data-i18n="The PDF includes all current members, not just this page.">{{ __('The PDF includes all current members, not just this page.') }}</p>
                </div>
            </x-filament::section>

            @if ($summary['needs_review'])
                <aside class="hoa-household__notice hoa-household__notice--review" role="status">
                    <x-filament::icon icon="heroicon-o-exclamation-triangle" class="size-5 shrink-0" aria-hidden="true" />
                    <div>
                        <h2 data-i18n="Awaiting HOA confirmation">{{ __('Awaiting HOA confirmation') }}</h2>
                        <p data-i18n="The HOA has not yet confirmed this household's membership details. You may still view and download this provisional summary. Contact the office if any information is incorrect.">{{ __("The HOA has not yet confirmed this household's membership details. You may still view and download this provisional summary. Contact the office if any information is incorrect.") }}</p>
                    </div>
                </aside>
            @endif

            @php
                $records = $this->getTableRecords();
                $first = $records instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator ? $records->firstItem() : ($records->isNotEmpty() ? 1 : 0);
                $last = $records instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator ? $records->lastItem() : $records->count();
            @endphp
            <section data-household-members data-members-first="{{ $first ?? 0 }}" data-members-last="{{ $last ?? 0 }}" data-members-total="{{ $summary['members_count'] }}" aria-labelledby="household-members-heading">
                <h2 id="household-members-heading" class="hoa-household__members-heading" data-i18n="Household members">{{ __('Household members') }}</h2>
                <p class="hoa-household__loading" wire:loading role="status" data-i18n="Loading household members…">{{ __('Loading household members…') }}</p>
                {{ $this->table }}
            </section>
        @else
            <x-filament::section>
                <div class="hoa-household__empty">
                    <x-filament::icon icon="heroicon-o-home-modern" class="size-10" aria-hidden="true" />
                    <h2 data-i18n="Household information unavailable">{{ __('Household information unavailable') }}</h2>
                    <p data-i18n="No reviewed household membership is available. Contact the HOA office.">{{ __('No reviewed household membership is available. Contact the HOA office.') }}</p>
                </div>
            </x-filament::section>
        @endif

        <p class="hoa-household__read-only">
            <x-filament::icon icon="heroicon-o-lock-closed" class="size-4 shrink-0" aria-hidden="true" />
            <span data-i18n="Only authorized HOA personnel can edit household or census information.">{{ __('Only authorized HOA personnel can edit household or census information.') }}</span>
        </p>
    </div>
</x-filament-panels::page>
