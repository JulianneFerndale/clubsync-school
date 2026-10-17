{{-- Notification list shared by member.notifications.index and notifications.index. --}}
@php
    $summary = $unreadCount > 0
        ? $unreadCount . ' new ' . Str::plural('notification', $unreadCount)
        : 'All caught up';
@endphp

{{-- Mobile: the top bar only says "ClubSync", so the page names itself here.
     On desktop the layout's top bar already carries the page title. --}}
<div class="md:hidden bg-[#07873e] px-5 pt-12 pb-5">
    <h1 class="text-white font-bold text-xl">Notifications</h1>
    <p class="text-white/60 text-xs mt-1">{{ $summary }}</p>
</div>

<div class="max-w-3xl mx-auto px-4 py-5 md:py-8">

    {{-- Desktop summary --}}
    <div class="hidden md:flex items-center justify-between gap-4 mb-4">
        <div class="flex items-center gap-2">
            @if($unreadCount > 0)
                <span class="w-2 h-2 rounded-full bg-[#F9A825]"></span>
            @else
                <svg class="w-4 h-4 text-[#07873e]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
            @endif
            <p class="text-sm font-semibold text-gray-700">{{ $summary }}</p>
        </div>
        @if($notifications->total() > 0)
            <p class="text-xs text-gray-400">
                Showing {{ $notifications->firstItem() }}–{{ $notifications->lastItem() }} of {{ $notifications->total() }}
            </p>
        @endif
    </div>

    @if($notifications->isEmpty())
        <div class="text-center py-14 md:bg-white md:rounded-2xl md:border md:border-gray-100 md:shadow-sm">
            <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
                <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/>
                </svg>
            </div>
            <p class="text-sm text-gray-500 font-medium">No notifications yet</p>
            <p class="text-xs text-gray-400 mt-1">You'll be notified about club activity here</p>
        </div>
    @else
        <div class="space-y-2">
            @foreach($notifications as $notification)
                <x-notification-card :notification="$notification" />
            @endforeach
        </div>

        {{-- Pagination --}}
        @if($notifications->hasPages())
            <nav class="flex items-center justify-between gap-3 pt-5" aria-label="Notification pages">
                @if($notifications->onFirstPage())
                    <span class="px-4 py-2 text-sm font-semibold text-gray-300">← Newer</span>
                @else
                    <a href="{{ $notifications->previousPageUrl() }}"
                       class="px-4 py-2 rounded-full bg-white border border-gray-200 text-sm font-semibold text-[#07873e] hover:bg-[#07873e]/5 transition-colors">← Newer</a>
                @endif

                <span class="text-xs text-gray-400">Page {{ $notifications->currentPage() }} of {{ $notifications->lastPage() }}</span>

                @if($notifications->hasMorePages())
                    <a href="{{ $notifications->nextPageUrl() }}"
                       class="px-4 py-2 rounded-full bg-white border border-gray-200 text-sm font-semibold text-[#07873e] hover:bg-[#07873e]/5 transition-colors">Older →</a>
                @else
                    <span class="px-4 py-2 text-sm font-semibold text-gray-300">Older →</span>
                @endif
            </nav>
        @endif
    @endif

</div>
