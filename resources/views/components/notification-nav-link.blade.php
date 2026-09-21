{{--
    "Notifications" entry for the officer, adviser, DSA and admin layouts (the member
    layout has its own). The badge carries .js-notif-badge so the Reverb listener in
    resources/js/app.js keeps it live.
    variant: sidebar (desktop), drawer (mobile slide-out), bottom (mobile bottom nav)
--}}
@props(['variant' => 'sidebar'])

@php
    $active = request()->routeIs('notifications.*');
    $unread = auth_user_id() ? \App\Models\ClubNotification::unreadCountFor(auth_user_id()) : 0;
    $label  = $unread > 9 ? '9+' : $unread;
    $icon   = 'M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0';
@endphp

@if($variant === 'bottom')
    <a href="{{ route('notifications.index') }}"
       class="flex flex-col items-center gap-0.5 flex-1 py-2 {{ $active ? 'text-[#07873e]' : 'text-gray-400' }}">
        <div class="relative">
            <svg class="w-6 h-6" fill="{{ $active ? '#07873e' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $icon }}"/>
            </svg>
            <span class="js-notif-badge absolute -top-1 -right-1 w-4 h-4 bg-[#F9A825] text-white text-[9px] font-bold rounded-full flex items-center justify-center"
                  style="{{ $unread > 0 ? '' : 'display:none' }}">{{ $label }}</span>
        </div>
        <span class="text-[10px] font-medium {{ $active ? 'border-b-2 border-[#F9A825]' : '' }}">Notifications</span>
    </a>
@else
    <a href="{{ route('notifications.index') }}"
       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
              {{ $variant === 'drawer'
                    ? 'text-white hover:bg-white/10'
                    : ($active ? 'bg-white/15 text-white' : 'text-white/70 hover:bg-white/10 hover:text-white') }}">
        <div class="relative flex-shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}"/>
            </svg>
            <span class="js-notif-badge absolute -top-1 -right-1 w-4 h-4 bg-[#F9A825] text-white text-[9px] font-bold rounded-full flex items-center justify-center"
                  style="{{ $unread > 0 ? '' : 'display:none' }}">{{ $label }}</span>
        </div>
        Notifications
    </a>
@endif
