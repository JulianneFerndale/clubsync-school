{{--
    "Resources" entry for the adviser, officer and DSA layouts: the club handbook,
    report templates and CHED memorandums (ResourceLibraryController). Not in the
    member layout — student members have no access to the library.
    variant: sidebar (desktop), drawer (mobile slide-out)
--}}
@props(['variant' => 'sidebar'])

@php($active = request()->routeIs('resources.*'))

<a href="{{ route('resources.index') }}"
   class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors
          {{ $variant === 'drawer'
                ? 'text-white hover:bg-white/10'
                : ($active ? 'bg-white/15 text-white' : 'text-white/70 hover:bg-white/10 hover:text-white') }}">
    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 12.75V12A2.25 2.25 0 0 1 4.5 9.75h15A2.25 2.25 0 0 1 21.75 12v.75m-8.69-6.44-2.12-2.12a1.5 1.5 0 0 0-1.061-.44H4.5A2.25 2.25 0 0 0 2.25 6v12a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9a2.25 2.25 0 0 0-2.25-2.25h-5.379a1.5 1.5 0 0 1-1.06-.44Z"/>
    </svg>
    Resources
</a>
