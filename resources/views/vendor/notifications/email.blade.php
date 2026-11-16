<x-mail::message>
<x-mail::title :text="$subject ?: __('Southville Phase I HOA')" />

<x-mail::prose :text="$greeting ?: __('Hello!')" />

@foreach ($introLines as $line)
<x-mail::prose :text="$line" />
@endforeach

@if (! empty($details))
<x-mail::details :rows="$details" />
@endif

@isset($actionText)
<x-mail::button :url="$actionUrl" color="primary">
{{ $actionText }}
</x-mail::button>
@endisset

@foreach ($outroLines as $line)
<x-mail::prose :text="$line" />
@endforeach

<x-mail::prose :text="$salutation ?: __('Southville Phase I HOA Office')" />

@isset($actionText)
<x-slot:subcopy>
<x-mail::prose :text="__('If the button does not work, copy and paste this link into your browser:')" />
<x-mail::link :url="$actionUrl" :label="$displayableActionUrl" />
</x-slot:subcopy>
@endisset
</x-mail::message>
