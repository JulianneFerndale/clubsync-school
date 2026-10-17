<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @if(session('logged_out'))
        {{-- Just logged out: resources/js/app.js signs the other open tabs out. --}}
        <meta name="cs-logged-out" content="1">
    @endif
    <meta name="theme-color" content="#07873e">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="ClubSync">
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="/icons/icon.svg">
    <title>@yield('title', 'ClubSync') — SCC</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 min-h-screen font-sans antialiased md:flex md:flex-row">

    {{-- ── Desktop: left branding panel ───────────────────────────────────── --}}
    <aside class="hidden md:flex flex-col sticky top-0 h-screen w-[480px] flex-shrink-0 bg-[#07873e] px-14 py-14 overflow-hidden">

        {{-- Logo --}}
        <div class="flex items-center gap-3 mb-auto">
            <img src="/images/clubsync_logo.png" alt="ClubSync"
                 class="w-14 h-14 object-contain flex-shrink-0"
                 onerror="this.onerror=null;this.style.display='none'">
            <div>
                <p class="text-white font-bold text-base leading-tight">ClubSync</p>
                <p class="text-white/50 text-xs">Saint Columban College</p>
            </div>
        </div>

        {{-- Hero text --}}
        <div class="my-auto">
            <h1 class="text-4xl font-extrabold text-white leading-tight mb-5">
                Stay connected.<br>Stay involved.
            </h1>
            <p class="text-white/65 text-base leading-relaxed mb-12">
                Your all-in-one student club management platform for Saint Columban College, Pagadian City.
            </p>

            {{-- Feature list --}}
            <ul class="space-y-4">
                <li class="flex items-center gap-4">
                    <div class="w-9 h-9 rounded-full bg-white/10 flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-[#F9A825]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                        </svg>
                    </div>
                    <span class="text-white/75 text-sm">Track events, attendance, and fees</span>
                </li>
                <li class="flex items-center gap-4">
                    <div class="w-9 h-9 rounded-full bg-white/10 flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-[#F9A825]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 0 1-1.44-4.282m3.102.069a18.03 18.03 0 0 1-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 0 1 8.835 2.535M10.34 6.66a23.847 23.847 0 0 1 8.835-2.535"/>
                        </svg>
                    </div>
                    <span class="text-white/75 text-sm">Read announcements from your clubs</span>
                </li>
                <li class="flex items-center gap-4">
                    <div class="w-9 h-9 rounded-full bg-white/10 flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-[#F9A825]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/>
                        </svg>
                    </div>
                    <span class="text-white/75 text-sm">Get real-time notifications</span>
                </li>
                <li class="flex items-center gap-4">
                    <div class="w-9 h-9 rounded-full bg-white/10 flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-[#F9A825]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                        </svg>
                    </div>
                    <span class="text-white/75 text-sm">Manage your club activity history</span>
                </li>
            </ul>
        </div>

        {{-- Footer --}}
        <p class="text-white/25 text-xs mt-auto">© {{ date('Y') }} Saint Columban College — Pagadian City</p>
    </aside>

    {{-- ── Content panel ───────────────────────────────────────────────────── --}}
    {{-- Mobile: centered white card | Desktop: right-half scrollable white panel --}}
    <div class="flex-1 flex items-center justify-center md:items-start md:bg-white md:overflow-y-auto min-h-screen">
        <div class="w-full max-w-sm min-h-screen bg-white shadow-xl flex flex-col overflow-hidden
                    md:shadow-none md:min-h-0 md:max-w-none md:w-full md:flex md:flex-col">
            @yield('content')
        </div>
    </div>

    <x-connection-status />
    <x-loading-overlay />
    <x-flash-dialog />
    <x-toast />
</body>
</html>
