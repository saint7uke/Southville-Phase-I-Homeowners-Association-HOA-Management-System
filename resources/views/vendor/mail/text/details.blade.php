@props(['rows'])
@foreach ($rows as $label => $value)
{{ $label }}: {{ (string) $value }}

@endforeach
