{{-- One room booking letter in a VPA list. --}}
<a href="{{ route('vpa.letters.show', $event) }}" class="block px-4 py-3.5 hover:bg-gray-50 transition-colors">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <p class="text-sm font-semibold text-gray-800 truncate">{{ $event->title }}</p>
            <p class="text-xs text-gray-500 mt-0.5 truncate">{{ $event->club?->name }}</p>
        </div>
        <x-status-badge :status="$event->approval_status" />
    </div>
    <p class="text-xs text-gray-400 mt-1">
        <span class="font-medium text-gray-600">{{ $event->venue }}</span>
        · {{ $event->date->format('D, M j, Y') }}
        · {{ \Carbon\Carbon::parse($event->time_start)->format('g:i A') }}–{{ \Carbon\Carbon::parse($event->time_end)->format('g:i A') }}
    </p>
    <p class="text-[11px] text-gray-400 mt-1">
        Sent by {{ $event->letterSignature('officer')['name'] ?? $event->creator?->name ?? 'an officer' }}
        · {{ $event->activity_type_label }}
    </p>
</a>
