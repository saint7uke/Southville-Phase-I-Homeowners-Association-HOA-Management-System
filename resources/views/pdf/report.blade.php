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
    <h1>Southville Phase I Homeowners Association</h1>
    <div class="meta">
        {{ $title }} | Generated {{ now()->format('Y-m-d H:i') }} by {{ $generatedBy }}
        @if(!empty($filters['from']) || !empty($filters['to']))
            | Period: {{ $filters['from'] ?? 'Beginning' }} to {{ $filters['to'] ?? 'Present' }}
        @endif
        @if(!empty($filters['status'])) | Status: {{ $filters['status'] }} @endif
    </div>
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
