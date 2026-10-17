{{--
    Where the student is in registration, on every step: the arrival notice closes by
    itself, so the page has to say it too. Nothing is created until step 4 is done.
--}}
@props(['current'])

@php
    $steps = [1 => 'Your details', 2 => 'Department', 3 => 'Course', 4 => 'Proof of enrollment'];
@endphp

<nav aria-label="Registration progress" {{ $attributes->merge(['class' => 'mb-6']) }}>
    <p class="text-xs font-semibold text-green-800 mb-2">
        Step {{ $current }} of {{ count($steps) }} <span class="text-gray-400 font-normal">·</span> {{ $steps[$current] }}
    </p>
    <ol class="grid grid-cols-4 gap-1.5">
        @foreach ($steps as $number => $label)
            <li class="h-1.5 rounded-full {{ $number <= $current ? 'bg-green-700' : 'bg-gray-200' }}"
                @if ($number === $current) aria-current="step" @endif>
                <span class="sr-only">Step {{ $number }}: {{ $label }}{{ $number < $current ? ' (done)' : '' }}</span>
            </li>
        @endforeach
    </ol>
</nav>
