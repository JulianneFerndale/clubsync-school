<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="cs-user-id" content="{{ auth_user_id() }}">
    <meta name="theme-color" content="#07873e">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="ClubSync">
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="/icons/icon.svg">
    <title>@yield('title', 'ClubSync — VP for Administration')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 min-h-screen font-sans antialiased">

    @php
        $vpaNav = [
            ['route' => 'vpa.dashboard', 'match' => 'vpa.dashboard', 'label' => 'Dashboard',
             'icon' => 'm2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25'],
            ['route' => 'vpa.letters.index', 'match' => 'vpa.letters*', 'label' => 'Booking Letters',
             'icon' => 'M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75'],
        ];
    @endphp

    {{-- ── Desktop fixed sidebar (md+) ───────────────────────────────────── --}}
    <aside class="hidden md:flex flex-col fixed inset-y-0 left-0 w-64 bg-[var(--cs-chrome)] z-30">
        <div class="flex items-center gap-3 px-6 py-5 border-b border-white/10">
            <img src="/images/clubsync_logo.png" alt="ClubSync"
                 class="w-14 h-14 object-contain flex-shrink-0"
                 onerror="this.onerror=null;this.style.display='none'">
            <div>
                <p class="text-white font-bold text-sm leading-tight">VPA Panel</p>
                <p class="text-white/50 text-[11px]">VP for Administration</p>
            </div>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto">
            @foreach($vpaNav as $item)
                <a href="{{ route($item['route']) }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                          {{ request()->routeIs($item['match']) ? 'bg-white/15 text-white' : 'text-white/70 hover:bg-white/10 hover:text-white' }}">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $item['icon'] }}"/>
                    </svg>
                    {{ $item['label'] }}
                </a>
            @endforeach
            <x-notification-nav-link />
            <x-chat-nav-link />
        </nav>
        <div class="px-4 py-4 border-t border-white/10">
            <a href="{{ route('profile') }}" class="flex items-center gap-3 px-3 py-2 mb-2 rounded-lg hover:bg-white/10 transition-colors">
                <x-avatar size="sm" />
                <div class="min-w-0">
                    <p class="text-white text-sm font-medium truncate">{{ auth()->user()->first_name ?? 'VPA' }} {{ auth()->user()->last_name ?? '' }}</p>
                    <p class="text-white/50 text-xs truncate">Profile &amp; signature</p>
                </div>
            </a>
            <form method="POST" action="{{ route('logout') }}" data-turbo="false">
                @csrf
                <button type="submit"
                        class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-red-300 hover:bg-white/10 transition-colors text-sm font-medium">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15M12 9l3 3m0 0-3 3m3-3H2.25"/>
                    </svg>
                    Logout
                </button>
            </form>
        </div>
    </aside>

    {{-- ── Mobile slide-out drawer ─────────────────────────────────────────── --}}
    <div id="vpa-drawer" class="md:hidden hidden fixed inset-0 z-40 flex">
        <div class="w-64 bg-[var(--cs-chrome)] h-full flex flex-col py-8 px-5 shadow-2xl">
            <p class="text-[#F9A825] font-bold text-lg mb-8">VPA Panel</p>
            <nav class="space-y-1 flex-1">
                @foreach($vpaNav as $item)
                    <a href="{{ route($item['route']) }}"
                       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-white hover:bg-white/10 transition-colors text-sm font-medium">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $item['icon'] }}"/>
                        </svg>
                        {{ $item['label'] }}
                    </a>
                @endforeach
                <a href="{{ route('profile') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-white hover:bg-white/10 transition-colors text-sm font-medium">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                    </svg>
                    Profile &amp; Signature
                </a>
                <x-notification-nav-link variant="drawer" />
                <x-chat-nav-link variant="drawer" />
            </nav>
            <form method="POST" action="{{ route('logout') }}" data-turbo="false">
                @csrf
                <button type="submit"
                        class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-red-300 hover:bg-white/10 transition-colors text-sm font-medium">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.25 9V5.25A2.25 2.25 0 0 1 10.5 3h6a2.25 2.25 0 0 1 2.25 2.25v13.5A2.25 2.25 0 0 1 16.5 21h-6a2.25 2.25 0 0 1-2.25-2.25V15M12 9l3 3m0 0-3 3m3-3H2.25"/>
                    </svg>
                    Logout
                </button>
            </form>
        </div>
        <div class="flex-1 bg-black/40" onclick="document.getElementById('vpa-drawer').classList.add('hidden')"></div>
    </div>

    {{-- ── Content area ────────────────────────────────────────────────────── --}}
    <div class="md:ml-64 flex flex-col min-h-screen">

        {{-- Mobile top bar --}}
        <header class="md:hidden flex items-center justify-between px-4 py-3 bg-[var(--cs-header)] border-b border-white/10 sticky top-0 z-10">
            <button onclick="document.getElementById('vpa-drawer').classList.toggle('hidden')"
                    class="p-1.5 rounded-lg hover:bg-white/10 transition-colors" aria-label="Menu">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
            <span class="text-sm font-semibold text-white">@yield('page-title', 'VPA Panel')</span>
            <div class="w-9"></div>
        </header>

        {{-- Desktop top bar --}}
        <header class="hidden md:flex items-center justify-between px-8 py-4 bg-[var(--cs-header)] border-b border-white/10 sticky top-0 z-10">
            <h1 class="text-lg font-semibold text-white">@yield('page-title', 'VPA Panel')</h1>
            <span class="text-sm text-white/70">{{ auth()->user()->first_name ?? '' }} {{ auth()->user()->last_name ?? '' }}</span>
        </header>

        {{-- Page content --}}
        <main class="flex-1 pb-6 md:bg-gray-50">
            <div id="cs-page" @hasSection('live-refresh') data-live-refresh="@yield('live-refresh')" @endif>
                @yield('content')
            </div>
        </main>
    </div>

    <x-connection-status />
    <x-loading-overlay />
    <x-flash-dialog />
    <x-toast />
</body>
</html>
