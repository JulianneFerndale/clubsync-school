@props(['event'])

<div class="flex items-start gap-3 px-4 py-3">
    {{-- Date badge --}}
    <div class="bg-[#F9A825] rounded-xl px-2.5 py-2 flex flex-col items-center min-w-[44px]">
        <span class="text-white font-bold text-lg leading-none">{{ $event->date->format('d') }}</span>
        <span class="text-white text-[9px] font-semibold uppercase">{{ $event->date->format('D') }}</span>
    </div>

    {{-- Details --}}
    <div class="flex-1 min-w-0">
        <p class="text-sm font-semibold text-gray-900 truncate">{{ $event->title }}</p>
        <p class="text-xs text-gray-500 mt-0.5">
            {{ \Carbon\Carbon::parse($event->time_start)->format('g:i A') }}
            – {{ \Carbon\Carbon::parse($event->time_end)->format('g:i A') }}
        </p>
        @if(isset($event->club) && $event->club)
            <span class="inline-block mt-1 px-2 py-0.5 bg-[#07873e]/10 text-[#07873e] text-[10px] font-semibold rounded-full">
                {{ $event->club->acronym ?? $event->club->name }}
            </span>
        @endif
    </div>
</div>
