<nav class="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 flex items-center justify-around h-16 z-20">

    {{-- Calendar --}}
    <a href="{{ route('officer.activities.index') }}"
       class="flex flex-col items-center gap-0.5 flex-1 py-2 {{ request()->routeIs('officer.activities*') ? 'text-[#07873e]' : 'text-gray-400' }}">
        <svg class="w-6 h-6" fill="{{ request()->routeIs('officer.activities*') ? '#07873e' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
        </svg>
        <span class="text-[10px] font-medium {{ request()->routeIs('officer.activities*') ? 'border-b-2 border-[#F9A825]' : '' }}">Calendar</span>
    </a>

    {{-- Activities --}}
    <a href="{{ route('officer.dashboard') }}"
       class="flex flex-col items-center gap-0.5 flex-1 py-2 {{ request()->routeIs('officer.dashboard') ? 'text-[#07873e]' : 'text-gray-400' }}">
        <svg class="w-6 h-6" fill="{{ request()->routeIs('officer.dashboard') ? '#07873e' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z"/>
        </svg>
        <span class="text-[10px] font-medium {{ request()->routeIs('officer.dashboard') ? 'border-b-2 border-[#F9A825]' : '' }}">Activities</span>
    </a>

    {{-- Members --}}
    <a href="{{ route('clubs.members.index') }}"
       class="flex flex-col items-center gap-0.5 flex-1 py-2 {{ request()->routeIs('clubs.members*') ? 'text-[#07873e]' : 'text-gray-400' }}">
        <svg class="w-6 h-6" fill="{{ request()->routeIs('clubs.members*') ? '#07873e' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/>
        </svg>
        <span class="text-[10px] font-medium {{ request()->routeIs('clubs.members*') ? 'border-b-2 border-[#F9A825]' : '' }}">Members</span>
    </a>

    {{-- Messages --}}
    <x-notification-nav-link variant="bottom" />
    <x-chat-nav-link variant="bottom" />
</nav>
