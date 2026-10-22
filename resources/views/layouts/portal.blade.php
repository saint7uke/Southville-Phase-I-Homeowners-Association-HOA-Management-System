<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Resident Portal') | Southville Phase I HOA</title>
    <x-favicon />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="portal-body">
    <a class="skip-link" href="#main-content" data-i18n="Skip to main content">{{ __('Skip to main content') }}</a>
    <header class="portal-header">
        <nav class="portal-nav" aria-label="Resident portal">
            <a class="brand" href="{{ route('portal.dashboard') }}"><span class="brand-mark" aria-hidden="true">S1</span><span data-i18n="Resident Portal">{{ __('Resident Portal') }}</span></a>
            <div class="portal-links">
                <a href="{{ route('portal.dashboard') }}" @if(request()->routeIs('portal.dashboard')) aria-current="page" @endif><span data-i18n="Dashboard">{{ __('Dashboard') }}</span></a>
                <a href="{{ route('portal.complaints.index') }}" @if(request()->routeIs('portal.complaints.*')) aria-current="page" @endif><span data-i18n="Complaints">{{ __('Complaints') }}</span></a>
                <a href="{{ route('portal.requests.index') }}" @if(request()->routeIs('portal.requests.*')) aria-current="page" @endif><span data-i18n="Requests">{{ __('Requests') }}</span></a>
                <a href="{{ route('portal.announcements.index') }}" @if(request()->routeIs('portal.announcements.*')) aria-current="page" @endif><span data-i18n="Announcements">{{ __('Announcements') }}</span></a>
                <a href="{{ route('portal.profile.edit') }}" @if(request()->routeIs('portal.profile.*')) aria-current="page" @endif><span data-i18n="Profile">{{ __('Profile') }}</span></a>
                <form method="POST" action="{{ route('portal.logout') }}">@csrf<button class="link-button" type="submit" data-i18n="Log out">{{ __('Log out') }}</button></form>
            </div>
            <x-locale-switcher />
        </nav>
    </header>
    <main id="main-content" class="portal-main" tabindex="-1">
        @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
        @yield('content')
    </main>
</body>
</html>
