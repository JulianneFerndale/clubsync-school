{{-- A club's standing: always an icon and a label, never colour alone. --}}
@php
    // Full literal class strings (Tailwind's scanner can't see interpolated names).
    $styles = [
        'strong'      => ['bg-green-50 text-green-700 border-green-200', 'M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
        'fair'        => ['bg-amber-50 text-amber-700 border-amber-200', 'M15 12H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
        'weak'        => ['bg-orange-50 text-orange-700 border-orange-200', 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z'],
        'inactive'    => ['bg-red-50 text-red-700 border-red-200', 'M14.25 9v6m-4.5 0V9M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
        'not_started' => ['bg-gray-50 text-gray-500 border-gray-200', 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z'],
    ];
    [$classes, $icon] = $styles[$standing];
@endphp
<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-semibold border whitespace-nowrap {{ $classes }}">
    <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icon }}"/>
    </svg>
    {{ \App\Services\ClubPerformanceService::STANDINGS[$standing] }}
</span>
