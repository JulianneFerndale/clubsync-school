@props(['notification'])

@php
    $read = $notification->is_read;
    $url  = $notification->action_url;
    // The whole card is the link, not just the chevron, so it is an easy target with a mouse too.
    $tag  = $url ? 'a' : 'div';
@endphp

<{{ $tag }} @if($url) href="{{ $url }}" @endif
    class="group flex items-start gap-3 px-4 py-3.5 md:px-5 md:py-4 rounded-xl bg-white border shadow-sm transition-colors
           {{ $read ? 'border-gray-100' : 'border-[#F9A825]/40 ring-1 ring-[#F9A825]/20' }}
           {{ $url ? 'hover:border-[#07873e]/30 hover:bg-[#07873e]/[0.03]' : '' }}">

    {{-- Club avatar, with the unread dot on its corner --}}
    <div class="relative flex-shrink-0">
        <div class="w-9 h-9 md:w-10 md:h-10 rounded-full flex items-center justify-center overflow-hidden
                    {{ $read ? 'bg-gray-100' : 'bg-[#07873e]/10' }}">
            @if($notification->club?->profile_photo_url)
                <img src="{{ $notification->club->profile_photo_url }}"
                     alt="" class="w-full h-full object-cover">
            @else
                <svg class="w-4 h-4 {{ $read ? 'text-gray-400' : 'text-[#07873e]' }}"
                     fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/>
                </svg>
            @endif
        </div>
        @unless($read)
            <span class="absolute -top-0.5 -right-0.5 w-3 h-3 rounded-full bg-[#F9A825] ring-2 ring-white">
                <span class="sr-only">Unread</span>
            </span>
        @endunless
    </div>

    {{-- Text --}}
    <div class="flex-1 min-w-0">
        @if($notification->title)
            <p class="text-sm font-semibold {{ $read ? 'text-gray-700' : 'text-gray-900' }} leading-snug">
                {{ $notification->title }}
            </p>
        @endif
        <p class="text-sm {{ $read ? 'text-gray-500' : 'text-gray-700' }} leading-snug mt-0.5">
            {{ $notification->body }}
        </p>
        <p class="text-[11px] md:text-xs text-gray-400 mt-1.5">
            @if($notification->club)
                <span class="font-medium text-gray-500">{{ $notification->club->acronym ?? $notification->club->name }}</span>
                <span aria-hidden="true">·</span>
            @endif
            <time datetime="{{ $notification->created_at->toIso8601String() }}"
                  title="{{ $notification->created_at->format('F j, Y · g:i A') }}">{{ $notification->created_at->diffForHumans() }}</time>
        </p>
    </div>

    @if($url)
        <svg class="w-4 h-4 flex-shrink-0 self-center text-gray-300 group-hover:text-[#07873e] transition-colors"
             fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
        </svg>
    @endif
</{{ $tag }}>
