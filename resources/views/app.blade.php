<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#14532d">

    <title>{{ config('app.name', 'ClubSync') }}</title>

    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="/images/clubsync_logo.png">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body class="antialiased bg-white text-gray-900">
    @inertia
</body>
</html>
