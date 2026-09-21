@props(['club', 'href' => '#'])

<a href="{{ $href }}" class="flex flex-col items-center gap-2 p-3 bg-white rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
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
