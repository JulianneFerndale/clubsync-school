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
    <title>@yield('title', 'ClubSync — Admin')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 min-h-screen font-sans antialiased">

    @php($currentUser = auth()->user())

    {{-- ── Desktop fixed sidebar (md+) ───────────────────────────────────── --}}
    <aside class="hidden md:flex flex-col fixed inset-y-0 left-0 w-64 bg-[var(--cs-chrome)] z-30">
        <div class="flex items-center gap-3 px-6 py-5 border-b border-white/10">
            <img src="/images/clubsync_logo.png" alt="ClubSync"
                 class="w-14 h-14 object-contain flex-shrink-0"
                 onerror="this.onerror=null;this.style.display='none'">
            <div>
                <p class="text-[#F9A825] font-bold text-sm leading-tight">Admin Panel</p>
                <p class="text-white/50 text-[11px]">System Administrator</p>
            </div>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto">
            <a href="{{ route('admin.dashboard') }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                      {{ request()->routeIs('admin.dashboard') ? 'bg-white/15 text-white' : 'text-white/70 hover:bg-white/10 hover:text-white' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/>
                </svg>
                Dashboard
            </a>
            <a href="{{ route('admin.users.index') }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                      {{ request()->routeIs('admin.users*') ? 'bg-white/15 text-white' : 'text-white/70 hover:bg-white/10 hover:text-white' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/>
                </svg>
                Manage Users
            </a>
            <a href="{{ route('admin.storage.index') }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                      {{ request()->routeIs('admin.storage*') ? 'bg-white/15 text-white' : 'text-white/70 hover:bg-white/10 hover:text-white' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.25 14.25h13.5m-13.5 0a3 3 0 0 1-3-3m3 3a3 3 0 1 0 0 6h13.5a3 3 0 1 0 0-6m-16.5-3a3 3 0 0 1 3-3h13.5a3 3 0 0 1 3 3m-19.5 0a4.5 4.5 0 0 1 .9-2.7L5.737 5.1a3.375 3.375 0 0 1 2.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 0 1 .9 2.7m0 0a3 3 0 0 1-3 3m0 3h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Zm-3 6h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Z"/>
                </svg>
                Storage &amp; Retention
            </a>
            @if(auth_role() === 'dsa')
            <a href="{{ route('dsa.dashboard') }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors text-white/70 hover:bg-white/10 hover:text-white">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
                </svg>
                Back to DSA Panel
            </a>
            @endif
            <x-notification-nav-link />
            <x-chat-nav-link />
        </nav>
        <div class="px-4 py-4 border-t border-white/10">
            <a href="{{ route('profile') }}" class="flex items-center gap-3 px-3 py-2 mb-2 rounded-lg hover:bg-white/10 transition-colors">
                <x-avatar :user="$currentUser" size="sm" />
                <div class="min-w-0">
                    <p class="text-white text-sm font-medium truncate">{{ $currentUser?->first_name ?? 'Admin' }} {{ $currentUser?->last_name ?? '' }}</p>
                    <p class="text-white/50 text-xs truncate">System Administrator</p>
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
    <div id="admin-drawer" class="md:hidden hidden fixed inset-0 z-40 flex">
        <div class="w-64 bg-[var(--cs-chrome)] h-full flex flex-col py-8 px-5 shadow-2xl">
            <p class="text-[#F9A825] font-bold text-lg mb-2">Admin Panel</p>
            <p class="text-white/60 text-xs mb-8">System Administrator</p>
            <nav class="space-y-1 flex-1">
                <a href="{{ route('admin.dashboard') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-white hover:bg-white/10 transition-colors text-sm font-medium">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/>
                    </svg>
                    Dashboard
                </a>
                <a href="{{ route('admin.users.index') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-white hover:bg-white/10 transition-colors text-sm font-medium">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/>
                    </svg>
                    Manage Users
                </a>
                <a href="{{ route('admin.storage.index') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-white hover:bg-white/10 transition-colors text-sm font-medium">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.25 14.25h13.5m-13.5 0a3 3 0 0 1-3-3m3 3a3 3 0 1 0 0 6h13.5a3 3 0 1 0 0-6m-16.5-3a3 3 0 0 1 3-3h13.5a3 3 0 0 1 3 3m-19.5 0a4.5 4.5 0 0 1 .9-2.7L5.737 5.1a3.375 3.375 0 0 1 2.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 0 1 .9 2.7"/>
                    </svg>
                    Storage &amp; Retention
                </a>
                @if(auth_role() === 'dsa')
                <a href="{{ route('dsa.dashboard') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-white hover:bg-white/10 transition-colors text-sm font-medium">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
                    </svg>
                    Back to DSA Panel
                </a>
                @endif
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
        <div class="flex-1 bg-black/40" onclick="document.getElementById('admin-drawer').classList.add('hidden')"></div>
    </div>

    {{-- ── Content area ────────────────────────────────────────────────────── --}}
    <div class="md:ml-64 flex flex-col min-h-screen">

        {{-- Mobile top bar --}}
        <header class="md:hidden flex items-center justify-between px-4 py-3 bg-[var(--cs-header)] border-b border-white/10 sticky top-0 z-10">
            <button onclick="document.getElementById('admin-drawer').classList.toggle('hidden')"
                    class="p-1.5 rounded-lg hover:bg-white/10 transition-colors">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
            <img src="/images/scc-crest.png" alt="SCC" class="h-8 w-8 object-contain"
                 onerror="this.style.display='none'">
            <div class="w-9"></div>
        </header>

        {{-- Desktop top bar --}}
        <header class="hidden md:flex items-center justify-between px-8 py-4 bg-[var(--cs-header)] border-b border-white/10 sticky top-0 z-10">
            <h1 class="text-lg font-semibold text-white">@yield('page-title', 'Admin Dashboard')</h1>
            <span class="text-sm text-white/70">{{ $currentUser?->first_name ?? 'Admin' }} {{ $currentUser?->last_name ?? '' }}</span>
        </header>

        {{-- Page content --}}
        <main class="flex-1 pb-8 md:bg-gray-50">
            <div id="cs-page" @hasSection('live-refresh') data-live-refresh="@yield('live-refresh')" @endif>
                @yield('content')
            </div>
        </main>
    </div>

    <x-connection-status />
    <x-loading-overlay />
</body>
</html>
