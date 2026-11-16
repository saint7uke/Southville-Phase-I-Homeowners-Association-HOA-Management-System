<x-filament-panels::page>
    <div class="hoa-concern-filters">
        @if ($residents->isNotEmpty())
            <label><span data-i18n="Homeowner">{{ __('Homeowner') }}</span><select wire:model.live="homeownerId"><option value="" data-i18n="Select a homeowner">{{ __('Select a homeowner') }}</option>@foreach ($residents as $resident)<option value="{{ $resident->id }}">{{ $resident->user->full_name }} · {{ $resident->full_address }}</option>@endforeach</select></label>
        @endif
        <label><span data-i18n="Year">{{ __('Year') }}</span><select wire:model.live="year">@for ($value = now('Asia/Manila')->year; $value >= now('Asia/Manila')->year - 10; $value--)<option value="{{ $value }}">{{ $value }}</option>@endfor</select></label>
    </div>
    <p data-i18n="Advance monthly dues payments are available through December of the current year. Each payment covers one billing month.">{{ __('Advance monthly dues payments are available through December of the current year. Each payment covers one billing month.') }}</p>
    @if ($summary)
        <div class="hoa-annual-scroll" tabindex="0" role="region" aria-label="{{ __('Annual dues summary') }}">
            <table class="hoa-annual-table"><thead><tr><th data-i18n="Month">{{ __('Month') }}</th>@foreach (['Charges', 'Verified payments', 'Pending payments', 'Penalties', 'Balance'] as $heading)<th>{{ __($heading) }}</th>@endforeach</tr></thead><tbody>
            @foreach ($summary['months'] as $month)
                <tr><th>{{ __(date('F', mktime(0, 0, 0, $month['month'], 1))) }}</th>
                @if (!$month['assessed'])<td colspan="5">{{ __('Not assessed') }}</td>
                @else @foreach (['charges', 'paid', 'pending', 'penalties', 'balance'] as $key)<td>PHP {{ $month[$key] }}</td>@endforeach @endif</tr>
            @endforeach
            </tbody><tfoot><tr><th data-i18n="Yearly totals">{{ __('Yearly totals') }}</th>@foreach ($summary['totals'] as $amount)<td>PHP {{ $amount }}</td>@endforeach</tr></tfoot></table>
        </div>
    @else <p data-i18n="Select a homeowner to view monthly balances.">{{ __('Select a homeowner to view monthly balances.') }}</p> @endif
</x-filament-panels::page>
