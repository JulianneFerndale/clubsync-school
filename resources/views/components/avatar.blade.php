@props(['user' => null, 'size' => 'sm'])

@php
$user    = $user ?? auth()->user();
$initial = strtoupper(substr($user->first_name ?? 'U', 0, 1));
$sizeClass = match($size) {
    'md'  => 'w-12 h-12 text-sm',
    'lg'  => 'w-20 h-20 text-xl',
    'xl'  => 'w-24 h-24 text-2xl',
    default => 'w-8 h-8 text-xs',
};
@endphp

@if($user?->profile_photo_url)
    <img src="{{ $user->profile_photo_url }}"
         alt="{{ $user->first_name }}"
         class="{{ $sizeClass }} rounded-full object-cover flex-shrink-0">
@else
    <div class="{{ $sizeClass }} rounded-full bg-white/20 flex items-center justify-center flex-shrink-0">
        <span class="text-white font-semibold">{{ $initial }}</span>
    </div>
@endif
