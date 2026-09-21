{{-- Notification list shared by member.notifications.index and notifications.index. --}}
<div class="bg-[#07873e] px-5 pt-12 pb-5">
    <h1 class="text-white font-bold text-xl">Notifications</h1>
    @if($unreadCount > 0)
        <p class="text-white/60 text-xs mt-1">{{ $unreadCount }} new {{ Str::plural('notification', $unreadCount) }}</p>
    @else
        <p class="text-white/60 text-xs mt-1">All caught up</p>
    @endif
</div>

<div class="px-4 py-5 space-y-2">

    @if($notifications->isEmpty())
        <div class="text-center py-14">
            <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
                <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/>
                </svg>
            </div>
            <p class="text-sm text-gray-400 font-medium">No notifications yet</p>
            <p class="text-xs text-gray-300 mt-1">You'll be notified about club activity here</p>
        </div>
    @else
        @foreach($notifications as $notification)
            <x-notification-card :notification="$notification" />
        @endforeach

        {{-- Pagination --}}
        @if($notifications->hasPages())
            <div class="flex items-center justify-center gap-3 pt-3">
                @if($notifications->onFirstPage())
                    <span class="text-gray-300 text-sm">←</span>
                @else
                    <a href="{{ $notifications->previousPageUrl() }}" class="text-[#07873e] text-sm font-semibold">← Newer</a>
                @endif

                <span class="text-xs text-gray-400">Page {{ $notifications->currentPage() }} of {{ $notifications->lastPage() }}</span>

                @if($notifications->hasMorePages())
                    <a href="{{ $notifications->nextPageUrl() }}" class="text-[#07873e] text-sm font-semibold">Older →</a>
                @else
                    <span class="text-gray-300 text-sm">→</span>
                @endif
            </div>
        @endif
    @endif

</div>
