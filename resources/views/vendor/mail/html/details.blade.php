@props(['rows'])
<table class="email-details" width="100%" cellpadding="0" cellspacing="0" role="presentation">
@foreach ($rows as $label => $value)
<tr><td class="email-detail-label">{{ $label }}</td><td class="email-detail-value">{!! nl2br(e((string) $value), false) !!}</td></tr>
@endforeach
</table>
