<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 28px; }
        body { color: #13293d; font-family: DejaVu Sans, sans-serif; font-size: 9px; }
        h1 { color: #0b5f8a; font-size: 18px; margin: 0 0 4px; }
        .meta { color: #4c6575; margin-bottom: 14px; }
        .summary { display: table; margin: 0 0 14px; width: 100%; }
        .summary-item { background: #eef6fa; border: 1px solid #c6d9e3; display: table-cell; padding: 7px; }
        .summary-label { color: #4c6575; display: block; font-size: 8px; }
        .summary-value { color: #13293d; display: block; font-size: 11px; font-weight: bold; margin-top: 2px; }
        table { border-collapse: collapse; table-layout: fixed; width: 100%; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th { background: #0b5f8a; color: #fff; font-weight: bold; padding: 6px 4px; text-align: left; }
        td { border-bottom: 1px solid #c6d9e3; overflow-wrap: anywhere; padding: 5px 4px; vertical-align: top; }
        tr:nth-child(even) td { background: #f5f9fb; }
        .empty { padding: 28px; text-align: center; }
        footer { bottom: -15px; color: #5b6f7d; font-size: 8px; position: fixed; right: 0; }
    </style>
</head>
<body>
    @if(!empty($letterhead))
        @include('pdf.partials.export-letterhead')
        <h1>{{ $title }}</h1>
    @else
        <h1>Southville Phase I Homeowners Association</h1>
    @endif
    <div class="meta">
        {{ $title }} | Generated {{ now()->format('Y-m-d H:i') }} by {{ $generatedBy }}
        @if(!empty($filters['from']) || !empty($filters['to']))
            | Period: {{ $filters['from'] ?? 'Beginning' }} to {{ $filters['to'] ?? 'Present' }}
        @endif
        @if(!empty($filters['status'])) | Status: {{ $filters['status'] }} @endif
        @if(!empty($filters['q'])) | Search: {{ $filters['q'] }} @endif
        @if(($filters['scope'] ?? 'current') !== 'current') | Scope: {{ ucfirst($filters['scope']) }} @endif
        | Sort: {{ $filters['sort'] ?? 'default' }} {{ strtoupper($filters['direction'] ?? 'asc') }}
    </div>
    @if(!empty($summary))
        <div class="summary">
            @foreach($summary as $label => $value)
                <div class="summary-item"><span class="summary-label">{{ $label }}</span><span class="summary-value">{{ $value }}</span></div>
            @endforeach
        </div>
    @endif
    <table>
        <thead><tr>@foreach($headings as $heading)<th>{{ $heading }}</th>@endforeach</tr></thead>
        <tbody>
        @forelse($rows as $row)
            <tr>@foreach($row as $value)<td>{{ $value ?? '-' }}</td>@endforeach</tr>
        @empty
            <tr><td class="empty" colspan="{{ count($headings) }}">No records matched the selected filters.</td></tr>
        @endforelse
        </tbody>
    </table>
    <footer>Confidential HOA record</footer>
</body>
</html>
