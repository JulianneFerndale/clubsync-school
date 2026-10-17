@props(['club', 'href' => '#', 'pending' => false])

<a href="{{ $href }}" class="relative flex flex-col items-center gap-2 p-3 bg-white rounded-xl shadow-sm border {{ $pending ? 'border-dashed border-amber-300' : 'border-gray-100' }} hover:shadow-md transition-shadow"
   @if($pending) aria-label="{{ $club->acronym ?? $club->name }}, membership pending approval" @endif>
    @if($pending)
        <span class="absolute -top-2 left-1/2 -translate-x-1/2 px-1.5 py-0.5 rounded-full bg-amber-50 border border-amber-300 text-amber-700 text-[9px] font-bold uppercase tracking-wide whitespace-nowrap">Pending</span>
    @endif
    @if($club->profile_photo_url)
        <img src="{{ $club->profile_photo_url }}"
             alt="{{ $club->acronym ?? $club->name }}"
             class="w-14 h-14 rounded-full object-cover border-2 border-[#07873e]/20"
             onerror="this.onerror=null;this.outerHTML='<div class=\'w-14 h-14 rounded-full bg-[#07873e] flex items-center justify-center\'><span class=\'text-white font-bold text-sm\'>{{ strtoupper(substr($club->acronym ?? $club->name, 0, 2)) }}</span></div>'">
    @else
        <div class="w-14 h-14 rounded-full bg-[#07873e] flex items-center justify-center">
            <span class="text-white font-bold text-sm">
                {{ strtoupper(substr($club->acronym ?? $club->name, 0, 2)) }}
            </span>
        </div>
    @endif
    <span class="text-[10px] font-semibold text-gray-700 text-center leading-tight line-clamp-2">
        {{ $club->acronym ?? $club->name }}
    </span>
</a>
