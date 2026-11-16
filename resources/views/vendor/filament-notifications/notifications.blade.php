<div>
    <div class="fi-no fi-align-right fi-vertical-align-start" role="status" aria-atomic="false">
    @foreach ($notifications as $notification)
        @if (count($notification->getActions()) === 0)
            <div wire:key="hoa-feedback-{{ $notification->getId() }}" data-hoa-notification-id="{{ $notification->getId() }}"
                data-hoa-feedback="{{ json_encode(\App\Support\ActionFeedback::fromNotification($notification)) }}">
                <div data-hoa-feedback-fallback>{{ $notification }}</div>
            </div>
        @else
            {{ $notification }}
        @endif
    @endforeach
    </div>
    @if ($broadcastChannel = $this->getBroadcastChannel())
        @script
            <script>
                const subscribe = () => window.Echo?.private(@js($broadcastChannel)).notification(
                    notification => $wire.handleBroadcastNotification(notification),
                );
                if (window.Echo) subscribe();
                else window.addEventListener('EchoLoaded', subscribe, { once: true });
            </script>
        @endscript
    @endif
</div>
