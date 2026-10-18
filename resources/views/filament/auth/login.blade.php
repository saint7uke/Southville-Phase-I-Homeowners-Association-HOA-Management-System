<x-filament-panels::page.simple>
    <div class="hoa-filament-auth hoa-filament-auth--{{ $this->getPortalAccent() }}">
        <aside class="hoa-filament-auth-intro" aria-labelledby="hoa-auth-intro-title">
            <a class="hoa-filament-auth-brand" href="{{ route('home') }}" aria-label="Go to Southville Phase I HOA homepage">
                <span class="hoa-filament-auth-logo"><img src="{{ asset('images/HOA.png') }}" alt=""></span>
                <span>{{ $this->getPortalEyebrow() }}</span>
            </a>
            <p class="hoa-filament-auth-portal">{{ $this->getPortalLabel() }}</p>
            <h2 id="hoa-auth-intro-title">{{ $this->getPortalWelcome() }}</h2>
            <p class="hoa-filament-auth-support">{{ $this->getPortalSupport() }}</p>
            <div class="hoa-filament-auth-rule" aria-hidden="true"></div>
            <p class="hoa-filament-auth-note">Secure access for Southville Phase I HOA members and teams.</p>
        </aside>

        <section class="hoa-filament-auth-form" aria-labelledby="hoa-auth-form-title">
            <div class="hoa-filament-auth-heading">
                <p class="hoa-filament-auth-eyebrow">{{ $this->getPortalLabel() }}</p>
                <h1 id="hoa-auth-form-title">{{ $this->getTitle() }}</h1>
                <p>{{ $this->getPortalSubtitle() }}</p>
            </div>

            @if($errors->has('data.email'))
                <div class="hoa-filament-auth-error" role="alert" tabindex="-1">
                    {{ $errors->first('data.email') }}
                </div>
            @endif

            {{ $this->content }}

            <nav class="hoa-filament-auth-links" aria-label="Login navigation">
                @foreach($this->getPortalLinks() as $link)
                    @if($loop->first === false)<span aria-hidden="true">·</span>@endif
                    <a href="{{ $link['url'] }}">{{ $link['label'] }}</a>
                @endforeach
            </nav>
        </section>
    </div>
</x-filament-panels::page.simple>
