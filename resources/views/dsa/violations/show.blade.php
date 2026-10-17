@extends('layouts.app-dsa')
@section('title', 'Violation — ' . ($violation->club->name ?? ''))

@section('content')

<div class="bg-[#07873e] px-5 py-4">
    <a href="{{ route('dsa.violations.index') }}" class="text-white/70 hover:text-white mb-2 inline-block">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
        </svg>
    </a>
    <h1 class="text-white font-bold text-xl">{{ $violation->club->name ?? 'Unknown club' }}</h1>
    <p class="text-white/60 text-xs mt-0.5">{{ $violation->gap_label }}</p>
</div>

<div class="px-4 py-5 space-y-5">


    <div class="flex items-center justify-between">
        <x-status-badge :status="$violation->is_resolved ? 'resolved' : $violation->status" />
        @if(! $violation->ai_available)
            <span class="text-xs text-[#F9A825] font-semibold">AI Unavailable — Manual Draft Required</span>
        @endif
    </div>

    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
        <p class="text-xs text-gray-500 mb-1.5 font-medium">Detected Issue</p>
        <p class="text-sm text-gray-700 leading-relaxed">{{ $violation->description }}</p>
    </div>

    @if($violation->status === 'pending_review')
        <form method="POST" action="{{ route('dsa.violations.approve', $violation) }}" class="space-y-3">
            @csrf
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Statement to send</label>
                <textarea name="content" rows="6" required
                          placeholder="{{ $violation->ai_available ? '' : 'AI draft unavailable — write the compliance statement manually...' }}"
                          class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30">{{ old('content', $violation->draft_content) }}</textarea>
                <p class="text-xs text-gray-400 mt-1">You may edit the AI draft above before sending — it will be sent exactly as written here.</p>
            </div>
            <div class="flex items-center gap-2">
                <button type="submit" class="bg-[#07873e] text-white text-sm font-semibold rounded-lg px-4 py-2 hover:opacity-90 transition-opacity">
                    Approve &amp; Send
                </button>
            </div>
        </form>

        <form method="POST" action="{{ route('dsa.violations.dismiss', $violation) }}">
            @csrf
            <button type="submit" class="border border-red-300 text-red-500 text-sm font-semibold rounded-lg px-4 py-2 hover:bg-red-50 transition-colors">
                Dismiss
            </button>
        </form>
    @else
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
            <p class="text-xs text-gray-500 mb-1.5 font-medium">Statement Sent</p>
            <p class="text-sm text-gray-700 leading-relaxed">{{ $violation->finalContent() }}</p>
            @if($violation->reviewedBy)
                <p class="text-xs text-gray-400 mt-2">Reviewed by {{ $violation->reviewedBy->name }} on {{ $violation->reviewed_at?->format('M j, Y g:i A') }}</p>
            @endif
        </div>

        @if($violation->is_resolved)
            <div class="bg-green-50 border border-green-200 rounded-xl px-4 py-3 text-green-700 text-sm">
                Resolved by {{ $violation->resolvedBy?->name ?? 'the club' }} on {{ $violation->resolved_at?->format('M j, Y') }}.
                @if($violation->resolution_note)
                    <br>Note: {{ $violation->resolution_note }}
                @endif
            </div>
        @endif
    @endif

</div>
@endsection
