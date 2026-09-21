@props(['notification'])

<div class="flex items-start gap-3 px-4 py-3.5 rounded-xl mb-2
            {{ $notification->is_read
                ? 'bg-white border border-gray-100 shadow-sm'
                : 'bg-white border border-[#F9A825]/40 shadow-sm ring-1 ring-[#F9A825]/20' }}">

    {{-- Unread dot --}}
    @if(! $notification->is_read)
        <div class="w-2 h-2 rounded-full bg-[#F9A825] flex-shrink-0 mt-2"></div>
    @else
        <div class="w-2 h-2 flex-shrink-0"></div>
    @endif

    {{-- Club avatar --}}
    <div class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0
                {{ $notification->is_read ? 'bg-gray-100' : 'bg-[#07873e]/10' }}">
        @if($notification->club?->profile_photo_url)
            <img src="{{ $notification->club->profile_photo_url }}"
                 alt="club" class="w-9 h-9 rounded-full object-cover">
        @else
            <svg class="w-4 h-4 {{ $notification->is_read ? 'text-gray-400' : 'text-[#07873e]' }}"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/>
            </svg>
        @endif
    </div>

    {{-- Text --}}
    <div class="flex-1 min-w-0">
        @if($notification->title)
            <p class="text-sm font-semibold {{ $notification->is_read ? 'text-gray-700' : 'text-gray-900' }} leading-snug">
                {{ $notification->title }}
            </p>
        @endif
        <p class="text-sm {{ $notification->is_read ? 'text-gray-500' : 'text-gray-700' }} leading-snug mt-0.5">
            {{ $notification->body }}
        </p>
        <p class="text-[10px] text-gray-400 mt-1">{{ $notification->created_at->diffForHumans() }}</p>
    </div>

    @if($notification->action_url)
        <a href="{{ $notification->action_url }}"
           class="flex-shrink-0 mt-1">
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
            </svg>
        </a>
    @endif
</div>
