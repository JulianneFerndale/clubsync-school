<nav class="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 flex items-center justify-around h-16 z-20">
    @php $route = request()->routeIs(...) @endphp

    {{-- Home --}}
    <a href="{{ route('member.dashboard') }}"
       class="flex flex-col items-center gap-0.5 flex-1 py-2 {{ request()->routeIs('member.dashboard') ? 'text-[#07873e]' : 'text-gray-400' }}">
        <svg class="w-6 h-6" fill="{{ request()->routeIs('member.dashboard') ? '#07873e' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75"/>
        </svg>
        <span class="text-[10px] font-medium {{ request()->routeIs('member.dashboard') ? 'border-b-2 border-[#F9A825]' : '' }}">Home</span>
    </a>

    {{-- Bulletin --}}
    <a href="{{ route('member.bulletin.index') }}"
       class="flex flex-col items-center gap-0.5 flex-1 py-2 {{ request()->routeIs('member.bulletin*') ? 'text-[#07873e]' : 'text-gray-400' }}">
        <svg class="w-6 h-6" fill="{{ request()->routeIs('member.bulletin*') ? '#07873e' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 0 1-1.44-4.282m3.102.069a18.03 18.03 0 0 1-.59-4.59c0-1.586.205-3.124.59-4.59m0 9.18a23.848 23.848 0 0 1 8.835 2.535M10.34 6.66a23.847 23.847 0 0 1 8.835-2.535m0 0A23.74 23.74 0 0 1 18.795 3m.38 1.125a23.91 23.91 0 0 1 1.014 5.395m-1.014 8.855c-.118.38-.245.754-.38 1.125m.38-1.125a23.91 23.91 0 0 0 1.014-5.395m-1.394 0a23.85 23.85 0 0 0 .38-4.59"/>
        </svg>
        <span class="text-[10px] font-medium {{ request()->routeIs('member.bulletin*') ? 'border-b-2 border-[#F9A825]' : '' }}">Bulletin</span>
    </a>

    {{-- Notifications --}}
    @php $unreadCount = auth_user_id() ? \App\Models\ClubNotification::unreadCountFor(auth_user_id()) : 0; @endphp
    <a href="{{ route('member.notifications.index') }}"
       class="flex flex-col items-center gap-0.5 flex-1 py-2 {{ request()->routeIs('member.notifications*') ? 'text-[#07873e]' : 'text-gray-400' }}">
        <div class="relative">
            <svg class="w-6 h-6" fill="{{ request()->routeIs('member.notifications*') ? '#07873e' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/>
            </svg>
            {{-- Always rendered (hidden at 0) so the Reverb listener can update it live. --}}
            <span class="js-notif-badge absolute -top-1 -right-1 w-4 h-4 bg-[#F9A825] text-white text-[9px] font-bold rounded-full flex items-center justify-center"
                  style="{{ $unreadCount > 0 ? '' : 'display:none' }}">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
        </div>
        <span class="text-[10px] font-medium {{ request()->routeIs('member.notifications*') ? 'border-b-2 border-[#F9A825]' : '' }}">Notifications</span>
    </a>

    {{-- Messages --}}
    <x-chat-nav-link variant="bottom" />

    {{-- Activity --}}
    <a href="{{ route('member.activity') }}"
       class="flex flex-col items-center gap-0.5 flex-1 py-2 {{ request()->routeIs('member.activity*') ? 'text-[#07873e]' : 'text-gray-400' }}">
        <svg class="w-6 h-6" fill="{{ request()->routeIs('member.activity*') ? '#07873e' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
        </svg>
        <span class="text-[10px] font-medium {{ request()->routeIs('member.activity*') ? 'border-b-2 border-[#F9A825]' : '' }}">Activity</span>
    </a>

    {{-- Profile --}}
    <a href="{{ route('profile') }}"
       class="flex flex-col items-center gap-0.5 flex-1 py-2 {{ request()->routeIs('profile') ? 'text-[#07873e]' : 'text-gray-400' }}">
        <svg class="w-6 h-6" fill="{{ request()->routeIs('profile') ? '#07873e' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
        </svg>
        <span class="text-[10px] font-medium {{ request()->routeIs('profile') ? 'border-b-2 border-[#F9A825]' : '' }}">Profile</span>
    </a>
</nav>
