<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Resident Portal') | Southville Phase I HOA</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="portal-body">
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <header class="portal-header">
        <nav class="portal-nav" aria-label="Resident portal">
            <a class="brand" href="{{ route('portal.dashboard') }}"><span class="brand-mark" aria-hidden="true">S1</span><span>Resident Portal</span></a>
            <div class="portal-links">
                <a href="{{ route('portal.dashboard') }}" @if(request()->routeIs('portal.dashboard')) aria-current="page" @endif>Dashboard</a>
                <a href="{{ route('portal.complaints.index') }}" @if(request()->routeIs('portal.complaints.*')) aria-current="page" @endif>Complaints</a>
                <a href="{{ route('portal.requests.index') }}" @if(request()->routeIs('portal.requests.*')) aria-current="page" @endif>Requests</a>
                <a href="{{ route('portal.announcements.index') }}" @if(request()->routeIs('portal.announcements.*')) aria-current="page" @endif>Announcements</a>
                <a href="{{ route('portal.profile.edit') }}" @if(request()->routeIs('portal.profile.*')) aria-current="page" @endif>Profile</a>
                <form method="POST" action="{{ route('portal.logout') }}">@csrf<button class="link-button" type="submit">Log out</button></form>
            </div>
        </nav>
    </header>
    <main id="main-content" class="portal-main" tabindex="-1">
        @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
        @yield('content')
    </main>
</body>
</html>
