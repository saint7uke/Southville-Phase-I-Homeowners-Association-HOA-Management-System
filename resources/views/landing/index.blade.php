<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Official resident services and community updates for Southville Phase I HOA.">
    <title>{{ $branding['hoaName'] }}</title>
    <x-favicon />
    @vite(['resources/css/app.css', 'resources/js/react/main.jsx'])
</head>
<body>
    <div id="landing-root" data-announcements='@json($announcements)' data-branding='@json($branding)' data-contact-success='@json(session('contact_success'))'></div>
</body>
</html>
