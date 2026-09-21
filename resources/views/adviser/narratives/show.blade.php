@extends('layouts.app-adviser')
@section('title', $narrative->title)
@section('page-title', 'Review Narrative')

@section('content')

<div class="bg-[#07873e] px-5 pt-5 pb-6">
    <a href="{{ route('adviser.narratives.index') }}" class="text-white/70 hover:text-white mb-4 inline-block">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
        </svg>
    </a>
    <div class="flex items-start justify-between gap-3">
        <h1 class="text-white font-bold text-xl leading-snug flex-1">{{ $narrative->title }}</h1>
        <x-status-badge :status="$narrative->status" />
    </div>
    @if($narrative->activity)
        <p class="text-white/50 text-xs mt-2">
            From activity · {{ $narrative->activity->date?->format('F j, Y') }}
            @if($narrative->activity->venue) · {{ $narrative->activity->venue }} @endif
        </p>
    @endif
</div>

<div class="px-4 py-5 space-y-4">

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 rounded-xl px-4 py-3 text-green-700 text-sm">
            {{ session('success') }}
        </div>
    @endif

    @error('status')
        <div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-red-600 text-sm">{{ $message }}</div>
    @enderror

    @if($narrative->ai_available)
        <div class="flex items-center gap-2 px-3 py-2 bg-purple-50 rounded-xl border border-purple-100">
            <span class="text-purple-500 text-sm">✦</span>
            <span class="text-xs text-purple-600 font-medium">AI-generated draft — review and edit before publishing</span>
        </div>
    @else
        <div class="flex items-center gap-2 px-3 py-2 bg-amber-50 rounded-xl border border-amber-100">
            <span class="text-amber-600 text-sm">⚠</span>
            <span class="text-xs text-amber-700 font-medium">AI Unavailable — Manual Draft Required. Please write the narrative below.</span>
        </div>
    @endif

    @if($narrative->status === 'pending_review')

        {{-- Editable draft + approve --}}
        <form method="POST" action="{{ route('adviser.narratives.approve', $narrative) }}" class="space-y-3">
            @csrf
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                <label class="text-xs text-gray-400 font-medium mb-2 block">Narrative</label>
                @error('content')
                    <p class="text-red-500 text-xs mb-2">{{ $message }}</p>
                @enderror
                <textarea name="content" rows="10" required
                          class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm text-gray-700 leading-relaxed focus:outline-none focus:ring-2 focus:ring-[#07873e]/30 resize-none">{{ old('content', $narrative->finalContent()) }}</textarea>
            </div>

            <button type="submit"
                    class="w-full flex items-center justify-between bg-[#07873e] text-[#F9A825] font-semibold text-sm rounded-xl px-5 py-4 hover:opacity-90 transition-opacity"
                    onclick="return confirm('Publish this narrative to the bulletin?')">
                Approve &amp; Publish
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
            </button>
        </form>

        {{-- Discard --}}
        <form method="POST" action="{{ route('adviser.narratives.discard', $narrative) }}">
            @csrf
            <button type="submit"
                    class="w-full flex items-center justify-between border-2 border-red-300 text-red-500 font-semibold text-sm rounded-xl px-5 py-4 hover:bg-red-50 transition-colors"
                    onclick="return confirm('Discard this narrative? It will not be published.')">
                Discard
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18 18 6M6 6l12 12"/>
                </svg>
            </button>
        </form>

    @else

        {{-- Read-only reviewed state --}}
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
            <p class="text-xs text-gray-400 font-medium mb-2">Narrative</p>
            <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-line">{{ $narrative->finalContent() ?? '—' }}</p>
        </div>
        <div class="bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-500 text-center">
            {{ ucfirst($narrative->status) }} · {{ $narrative->reviewed_at?->format('F j, Y') }}
        </div>

    @endif

</div>
@endsection
