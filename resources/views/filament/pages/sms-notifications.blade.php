<x-filament-panels::page>
    <div class="rounded-xl border p-4 dark:border-white/10">
        <p>{{ __('Gateway acceptance does not confirm handset delivery.') }}</p>
        <p>{{ __('The environment kill switch and test-number allowlist apply independently of these settings.') }}</p>
        @php($stats = $this->statistics())
        <dl class="mt-4 grid gap-3 sm:grid-cols-3">
            <div><dt>{{ __('Total SMS attempts') }}</dt><dd>{{ $stats['attempts'] }}</dd></div>
            <div><dt>{{ __('Segments reserved today') }}</dt><dd>{{ $stats['segments'] }} / {{ \App\Models\SystemSetting::current()->sms_daily_segments }}</dd></div>
            @foreach ($stats['statuses'] as $status => $count)
                <div><dt>{{ __($status) }}</dt><dd>{{ $count }}</dd></div>
            @endforeach
        </dl>
        <h2 class="mt-4 font-semibold">{{ __('Notifications by type') }}</h2>
        <dl class="grid gap-3 sm:grid-cols-3">
            @foreach ($stats['types'] as $type => $count)<div><dt>{{ __($type) }}</dt><dd>{{ $count }}</dd></div>@endforeach
        </dl>
    </div>
    @if ($deviceHealth !== null)
        <section class="rounded-xl border p-4"><h2>{{ __('SMS Device Status') }}</h2>
            <dl>@foreach ($deviceHealth as $field => $value)<div><dt>{{ $field }}</dt><dd>{{ is_bool($value) ? ($value ? __('Yes') : __('No')) : $value }}</dd></div>@endforeach</dl>
        </section>
    @endif
    {{ $this->table }}
</x-filament-panels::page>
