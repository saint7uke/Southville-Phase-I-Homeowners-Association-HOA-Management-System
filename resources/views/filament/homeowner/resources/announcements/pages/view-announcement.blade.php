<x-filament-panels::page>
    @php
        $announcement = $this->getRecord();
        $categoryKey = str($announcement->category)->lower()->slug();
    @endphp

    <div class="hoa-announcement-detail">
        <a class="hoa-announcement-detail__back" href="{{ \App\Filament\Homeowner\Resources\Announcements\AnnouncementResource::getUrl('index') }}">
            <svg aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" />
            </svg>
            <span data-i18n="Back to announcements">{{ __('Back to announcements') }}</span>
        </a>

        <article class="hoa-announcement-detail__article" data-category="{{ $categoryKey }}" aria-labelledby="announcement-title">
            @if ($bannerUrl = $this->getBannerUrl())
                <img
                    class="hoa-announcement-detail__banner"
                    src="{{ $bannerUrl }}"
                    alt=""
                    loading="eager"
                >
            @endif

            <div class="hoa-announcement-detail__inner">
                <div class="hoa-announcement-detail__meta">
                    <span class="hoa-announcement-detail__category">{{ $announcement->category }}</span>
                    @if ($announcement->published_at)
                        <time class="hoa-announcement-detail__date" datetime="{{ $announcement->published_at->toIso8601String() }}">
                            <svg aria-hidden="true" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3.75 9h16.5M5.25 5.25h13.5A1.5 1.5 0 0120.25 6.75v12a1.5 1.5 0 01-1.5 1.5H5.25a1.5 1.5 0 01-1.5-1.5v-12a1.5 1.5 0 011.5-1.5z" />
                            </svg>
                            <span><span data-i18n="Published">{{ __('Published') }}</span> {{ $announcement->published_at->timezone(config('app.timezone'))->format('F j, Y \a\t g:i A') }}</span>
                        </time>
                    @endif
                </div>

                <h2 id="announcement-title" class="hoa-announcement-detail__title">{{ $announcement->title }}</h2>
                <hr class="hoa-announcement-detail__divider" aria-hidden="true">
                <div class="hoa-announcement-detail__content">{{ $announcement->content }}</div>
            </div>
        </article>
    </div>
</x-filament-panels::page>
