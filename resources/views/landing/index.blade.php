<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="description" content="Official resident services and community updates for Southville Phase I HOA.">
    <title>Southville Phase I HOA</title>
    @vite(['resources/css/app.css', 'resources/js/react/main.jsx'])
</head>
<body>
    <div id="landing-root" data-announcements='@json($announcements)'></div>
</body>
</html>
