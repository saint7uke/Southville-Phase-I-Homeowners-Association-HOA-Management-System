<dl class="hoa-audit-details">
    @foreach (['auditable_type' => 'Record type', 'auditable_id' => 'Record ID', 'panel' => 'Panel', 'ip_address' => 'IP address', 'created_at' => 'Date and time'] as $key => $label)
        <dt>{{ __($label) }}</dt><dd>{{ $record->{$key} }}</dd>
    @endforeach
    @foreach (['old_values' => 'Old values', 'new_values' => 'New values'] as $key => $label)<dt>{{ __($label) }}</dt><dd><pre>{{ json_encode($record->{$key}, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre></dd>@endforeach
</dl>
