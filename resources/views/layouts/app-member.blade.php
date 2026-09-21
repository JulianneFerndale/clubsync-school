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
    <title>@yield('title', 'ClubSync')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 min-h-screen font-sans antialiased">

    @php($unreadCount = auth_user_id() ? \App\Models\ClubNotification::unreadCountFor(auth_user_id()) : 0)

    {{-- ── Desktop fixed sidebar (md+) ───────────────────────────────────── --}}
    <aside class="hidden md:flex flex-col fixed inset-y-0 left-0 w-64 bg-[var(--cs-chrome)] z-30">
        <div class="flex items-center gap-3 px-6 py-5 border-b border-white/10">
            <img src="/images/clubsync_logo.png" alt="ClubSync"
                 class="w-14 h-14 object-contain flex-shrink-0"
                 onerror="this.onerror=null;this.style.display='none'">
            <div>
                <p class="text-white font-bold text-sm leading-tight">ClubSync</p>
                <p class="text-white/50 text-[11px]">Saint Columban College</p>
            </div>
        </div>
        <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto">
            <a href="{{ route('member.dashboard') }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                      {{ request()->routeIs('member.dashboard') ? 'bg-white/15 text-white' : 'text-white/70 hover:bg-white/10 hover:text-white' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/>
                </svg>
                Home
            </a>
            <a href="{{ route('member.bulletin.index') }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                      {{ request()->routeIs('member.bulletin*') ? 'bg-white/15 text-white' : 'text-white/70 hover:bg-white/10 hover:text-white' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 0 1-1.44-4.282m3.102.069a18.03 18.03 0 0 1-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 0 1 8.835 2.535M10.34 6.66a23.847 23.847 0 0 1 8.835-2.535m0 0A23.74 23.74 0 0 1 18.795 3m.38 1.125a23.91 23.91 0 0 1 1.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 0 0 1.014-5.395m-1.394 0a23.85 23.85 0 0 0 .38-4.59"/>
                </svg>
                Bulletin
            </a>
            <a href="{{ route('member.notifications.index') }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                      {{ request()->routeIs('member.notifications*') ? 'bg-white/15 text-white' : 'text-white/70 hover:bg-white/10 hover:text-white' }}">
                <div class="relative flex-shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/>
                    </svg>
                    <span class="js-notif-badge absolute -top-1 -right-1 w-4 h-4 bg-[#F9A825] text-white text-[9px] font-bold rounded-full flex items-center justify-center"
                          style="{{ $unreadCount > 0 ? '' : 'display:none' }}">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
                </div>
                Notifications
            </a>
            <a href="{{ route('member.clubs.index') }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                      {{ request()->routeIs('member.clubs*') ? 'bg-white/15 text-white' : 'text-white/70 hover:bg-white/10 hover:text-white' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0z"/>
                </svg>
                Clubs
            </a>
            <a href="{{ route('member.activity') }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                      {{ request()->routeIs('member.activity*') ? 'bg-white/15 text-white' : 'text-white/70 hover:bg-white/10 hover:text-white' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
                Activity
            </a>
            <a href="{{ route('profile') }}"
               class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
                      {{ request()->routeIs('profile') ? 'bg-white/15 text-white' : 'text-white/70 hover:bg-white/10 hover:text-white' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                </svg>
                Profile
            </a>
            <x-chat-nav-link />
        </nav>
        <div class="px-4 py-4 border-t border-white/10">
            <a href="{{ route('profile') }}" class="flex items-center gap-3 px-3 py-2 mb-2 rounded-lg hover:bg-white/10 transition-colors">
                <x-avatar size="sm" />
                <div class="min-w-0">
                    <p class="text-white text-sm font-medium truncate">{{ auth()->user()->first_name ?? 'User' }} {{ auth()->user()->last_name ?? '' }}</p>
                    <p class="text-white/50 text-xs truncate">{{ auth()->user()->email ?? '' }}</p>
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
    <div id="member-drawer" class="md:hidden hidden fixed inset-0 z-40 flex">
        <div class="w-64 bg-[var(--cs-chrome)] h-full flex flex-col py-8 px-5 shadow-2xl">
            <div class="flex items-center justify-between mb-8">
                <p class="text-[#F9A825] font-bold text-lg">ClubSync</p>
                <button onclick="document.getElementById('member-drawer').classList.add('hidden')"
                        class="text-white/60 hover:text-white transition-colors p-1 rounded-lg hover:bg-white/10">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <nav class="space-y-1 flex-1">
                <a href="{{ route('member.dashboard') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-white hover:bg-white/10 transition-colors text-sm font-medium">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/>
                    </svg>
                    Home
                </a>
                <a href="{{ route('member.bulletin.index') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-white hover:bg-white/10 transition-colors text-sm font-medium">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 0 1-1.44-4.282m3.102.069a18.03 18.03 0 0 1-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 0 1 8.835 2.535M10.34 6.66a23.847 23.847 0 0 1 8.835-2.535m0 0A23.74 23.74 0 0 1 18.795 3m.38 1.125a23.91 23.91 0 0 1 1.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 0 0 1.014-5.395m-1.394 0a23.85 23.85 0 0 0 .38-4.59"/>
                    </svg>
                    Bulletin
                </a>
                <a href="{{ route('member.notifications.index') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-white hover:bg-white/10 transition-colors text-sm font-medium">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/>
                    </svg>
                    Notifications
                </a>
                <a href="{{ route('member.clubs.index') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-white hover:bg-white/10 transition-colors text-sm font-medium">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0z"/>
                    </svg>
                    Clubs
                </a>
                <a href="{{ route('member.activity') }}"
                   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-white hover:bg-white/10 transition-colors text-sm font-medium">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                    Activity
                </a>
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
        <div class="flex-1 bg-black/40" onclick="document.getElementById('member-drawer').classList.add('hidden')"></div>
    </div>

    {{-- ── Content area ────────────────────────────────────────────────────── --}}
    <div class="md:ml-64 flex flex-col min-h-screen">

        {{-- Mobile top bar --}}
        <header class="md:hidden flex items-center justify-between px-4 py-3 bg-[var(--cs-header)] border-b border-white/10 sticky top-0 z-10">
            <button onclick="document.getElementById('member-drawer').classList.toggle('hidden')"
                    class="p-1.5 rounded-lg hover:bg-white/10 transition-colors">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                </svg>
            </button>
            <span class="text-sm font-semibold text-white">ClubSync</span>
            <div class="w-9"></div>
        </header>

        {{-- Desktop top bar --}}
        <header class="hidden md:flex items-center justify-between px-8 py-4 bg-[var(--cs-header)] border-b border-white/10 sticky top-0 z-10">
            <h1 class="text-lg font-semibold text-white">@yield('page-title', 'Dashboard')</h1>
            <div class="flex items-center gap-3">
                <a href="{{ route('member.notifications.index') }}" class="relative p-2 rounded-lg hover:bg-white/10 transition-colors">
                    <svg class="w-5 h-5 text-white/80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/>
                    </svg>
                    <span class="js-notif-badge absolute top-1 right-1 w-4 h-4 bg-[#F9A825] text-white text-[9px] font-bold rounded-full flex items-center justify-center"
                          style="{{ $unreadCount > 0 ? '' : 'display:none' }}">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
                </a>
                <span class="text-sm text-white/70">{{ auth()->user()->first_name ?? '' }} {{ auth()->user()->last_name ?? '' }}</span>
            </div>
        </header>

        {{-- Page content --}}
        <main class="flex-1 pb-20 md:pb-8 md:bg-gray-50">
            <div id="cs-page" @hasSection('live-refresh') data-live-refresh="@yield('live-refresh')" @endif>
                @yield('content')
            </div>
        </main>
    </div>

    {{-- Bottom nav: mobile only (has md:hidden) --}}
    <x-bottom-nav />

    <x-connection-status />
    <x-loading-overlay />
</body>
</html>
