<!doctype html><html lang="{{ app()->getLocale() }}"><head><meta charset="utf-8"><style>
@page { margin: 20mm; } body { font: 11px DejaVu Sans; } table { width: 100%; border-collapse: collapse; } td, th { border: 1px solid #ccc; padding: 6px; text-align: left; } thead { display: table-header-group; }
</style></head><body>
<h1>{{ __('Household summary') }}</h1><p><strong>{{ __('PROVISIONAL RBI — subject to HOA confirmation') }}</strong></p>
<p>{{ $household->identifier }} — {{ $household->full_address }}</p>
<p>{{ __('Current members') }}: {{ $members->count() }}</p>
<table><thead><tr><th>{{ __('Name') }}</th><th>{{ __('Relationship') }}</th><th>{{ __('Residency status') }}</th>@if($manager)<th>{{ __('Birth date') }}</th><th>{{ __('Sex') }}</th>@endif</tr></thead><tbody>
@foreach($members as $member)<tr><td>{{ $member->full_name }}</td><td>{{ __($member->relationship) }}</td><td>{{ __($member->residency_status) }}</td>@if($manager)<td>{{ $member->date_of_birth?->toDateString() }}</td><td>{{ __($member->sex ?? '') }}</td>@endif</tr>@endforeach
</tbody></table><p>{{ __('Generated') }} {{ now()->format('Y-m-d H:i') }}</p>
</body></html>
