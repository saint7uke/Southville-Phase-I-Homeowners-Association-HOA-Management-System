<x-filament-panels::page>
    <p>{{ __('PROVISIONAL RBI — subject to HOA confirmation') }}</p>
    @if ($summary)
        <h2 class="font-semibold">{{ $summary['address'] }}</h2>
        <p>{{ __('Household ID') }}: {{ $summary['identifier'] }}</p>
        @if ($summary['needs_review'])<p role="status">{{ __('This household needs HOA review. Contact the office to correct membership details.') }}</p>@endif
        <div class="overflow-x-auto" tabindex="0" role="region" aria-label="{{ __('Household members') }}">
            <table class="w-full text-start"><caption>{{ __('Current members') }}: {{ count($summary['members']) }}</caption><thead><tr><th>{{ __('Name') }}</th><th>{{ __('Relationship') }}</th><th>{{ __('Residency status') }}</th></tr></thead><tbody>
            @foreach ($summary['members'] as $member)<tr><td class="p-3">{{ $member['full_name'] }}</td><td class="p-3">{{ __($member['relationship']) }}</td><td class="p-3">{{ __($member['residency_status']) }}</td></tr>@endforeach
            </tbody></table>
        </div>
        <a href="{{ route('households.pdf', $summary['id']) }}">{{ __('Download household summary') }}</a>
    @else
        <p>{{ __('No reviewed household membership is available. Contact the HOA office.') }}</p>
    @endif
    <p>{{ __('Only authorized HOA personnel can edit household or census information.') }}</p>
</x-filament-panels::page>
