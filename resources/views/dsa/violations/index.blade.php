@extends('layouts.app-dsa')
@section('title', 'AI Notification Queue — DSA')

@section('content')

<div class="bg-[#07873e] px-5 py-4">
    <h1 class="text-white font-bold text-xl">AI Notification Queue</h1>
    <p class="text-white/60 text-xs mt-0.5">AI-drafted compliance violations awaiting review</p>
</div>

<div class="px-4 py-5 space-y-5">


    @if($violations->isEmpty())
        <p class="text-sm text-gray-400 text-center py-8">No violations detected.</p>
    @else
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden divide-y divide-gray-50">
            @foreach($violations as $violation)
                <a href="{{ route('dsa.violations.show', $violation) }}"
                   class="flex items-center justify-between gap-3 px-4 py-3.5 hover:bg-gray-50 transition-colors">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-800 truncate">
                            {{ $violation->club->name ?? 'Unknown club' }} — {{ $violation->gap_label }}
                        </p>
                        <p class="text-xs text-gray-400 mt-0.5 truncate">{{ $violation->description }}</p>
                        @if(! $violation->ai_available)
                            <p class="text-xs text-[#F9A825] font-medium mt-1">AI Unavailable — Manual Draft Required</p>
                        @endif
                    </div>
                    <x-status-badge :status="$violation->is_resolved ? 'resolved' : $violation->status" />
                </a>
            @endforeach
        </div>

        <div>{{ $violations->links() }}</div>
    @endif

</div>
@endsection
