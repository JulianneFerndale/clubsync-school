{{--
    Blue "Facebook" button linking to a club's official page. Renders nothing when the
    club has no page. A plain link, deliberately: no Facebook SDK or embed, so opening a
    club page sends nothing to Facebook, and rel="noreferrer" keeps the ClubSync URL
    from being passed along when the button is clicked.
--}}
@props(['url' => null, 'club' => null])

@if($url)
    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer"
       aria-label="{{ $club ? "Visit {$club} on Facebook" : 'Visit on Facebook' }} (opens in a new tab)"
       {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 rounded-lg bg-[#1877F2] px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-[#166FE5] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#1877F2]/50 focus-visible:ring-offset-2 transition-colors']) }}>
        <svg class="w-4 h-4 flex-shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M24 12.073C24 5.405 18.627 0 12 0S0 5.405 0 12.073C0 18.1 4.388 23.094 10.125 24v-8.437H7.078v-3.49h3.047V9.413c0-3.025 1.792-4.697 4.533-4.697 1.312 0 2.686.236 2.686.236v2.971H15.83c-1.491 0-1.956.93-1.956 1.886v2.264h3.328l-.532 3.49h-2.796V24C19.612 23.094 24 18.1 24 12.073Z"/>
        </svg>
        Facebook
    </a>
@endif
