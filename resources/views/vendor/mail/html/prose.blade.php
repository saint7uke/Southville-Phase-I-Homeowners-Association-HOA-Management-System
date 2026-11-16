@props(['text'])
<p class="email-prose">{!! nl2br(e((string) $text), false) !!}</p>
