@vite('resources/js/filament-alerts.js')

@if (session()->has('action_feedback'))
    <template data-hoa-feedback="{{ json_encode(session()->pull('action_feedback')) }}"></template>
@endif
