{{--
    "Messages" entry for every app layout. The badge carries .js-chat-badge so the
    Reverb listener in resources/js/app.js can update it live.
    variant: sidebar (desktop), drawer (mobile slide-out), bottom (mobile bottom nav)
--}}
@props(['variant' => 'sidebar'])

@php
    $active = request()->routeIs('chat.*');
    $unread = \App\Models\ChatMessage::unreadCountFor(auth_user_id());
    $label  = $unread > 9 ? '9+' : $unread;
    $icon   = 'M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z';
@endphp

@if($variant === 'bottom')
    <a href="{{ route('chat.index') }}"
       class="flex flex-col items-center gap-0.5 flex-1 py-2 {{ $active ? 'text-[#07873e]' : 'text-gray-400' }}">
        <div class="relative">
            <svg class="w-6 h-6" fill="{{ $active ? '#07873e' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="{{ $icon }}"/>
            </svg>
            <span class="js-chat-badge absolute -top-1 -right-1 w-4 h-4 bg-[#F9A825] text-white text-[9px] font-bold rounded-full flex items-center justify-center"
                  style="{{ $unread > 0 ? '' : 'display:none' }}">{{ $label }}</span>
        </div>
        <span class="text-[10px] font-medium {{ $active ? 'border-b-2 border-[#F9A825]' : '' }}">Messages</span>
    </a>
@else
    <a href="{{ route('chat.index') }}"
       class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
              {{ $variant === 'drawer'
                    ? 'text-white hover:bg-white/10'
                    : ($active ? 'bg-white/15 text-white' : 'text-white/70 hover:bg-white/10 hover:text-white') }}">
        <div class="relative flex-shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}"/>
            </svg>
            <span class="js-chat-badge absolute -top-1 -right-1 w-4 h-4 bg-[#F9A825] text-white text-[9px] font-bold rounded-full flex items-center justify-center"
                  style="{{ $unread > 0 ? '' : 'display:none' }}">{{ $label }}</span>
        </div>
        Messages
    </a>
@endif
