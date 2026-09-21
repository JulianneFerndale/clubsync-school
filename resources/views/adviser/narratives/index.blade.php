@extends('layouts.app-adviser')
@section('title', 'AI Narratives')
@section('page-title', 'AI Narratives')

@section('content')

<div class="bg-[#07873e] px-5 pt-5 pb-6">
    <h1 class="text-white font-bold text-xl">AI Narratives</h1>
    <p class="text-white/60 text-xs mt-1">{{ $club?->name ?? 'No club assigned' }}</p>
</div>

<div class="px-4 py-5 space-y-5">

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 rounded-xl px-4 py-3 text-green-700 text-sm">
            {{ session('success') }}
        </div>
    @endif

    @if(! $club)
        <p class="text-sm text-gray-400 text-center py-8">No club assigned.</p>
    @else

        {{-- Pending review --}}
        <div>
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-2 px-1">
                Pending Review
                @if($pending->isNotEmpty())
                    <span class="ml-1 bg-[#F9A825] text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full">{{ $pending->count() }}</span>
                @endif
            </p>

            @if($pending->isEmpty())
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-5 text-center">
                    <p class="text-sm text-gray-400">No narratives awaiting review.</p>
                </div>
            @else
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden divide-y divide-gray-50">
                    @foreach($pending as $narrative)
                        <a href="{{ route('adviser.narratives.show', $narrative) }}"
                           class="flex items-start gap-3 px-4 py-3.5 hover:bg-gray-50 transition-colors">
                            <div class="w-9 h-9 rounded-full bg-purple-100 flex items-center justify-center flex-shrink-0">
                                <span class="text-purple-500 text-sm">✦</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-800 truncate">{{ $narrative->title }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">
                                    {{ $narrative->created_at->diffForHumans() }}
                                </p>
                                <div class="flex items-center gap-2 mt-1.5">
                                    @if($narrative->ai_available)
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-purple-50 text-purple-600 text-[10px] font-semibold">✦ AI draft</span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-50 text-amber-600 text-[10px] font-semibold">AI Unavailable — Manual Draft</span>
                                    @endif
                                </div>
                            </div>
                            <svg class="w-4 h-4 text-gray-400 mt-1 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                            </svg>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Recently reviewed --}}
        @if($reviewed->isNotEmpty())
        <div>
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-2 px-1">Recently Reviewed</p>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden divide-y divide-gray-50">
                @foreach($reviewed as $narrative)
                    <a href="{{ route('adviser.narratives.show', $narrative) }}"
                       class="flex items-center justify-between px-4 py-3 hover:bg-gray-50 transition-colors">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-800 truncate">{{ $narrative->title }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">{{ $narrative->reviewed_at?->format('M j, Y') }}</p>
                        </div>
                        <x-status-badge :status="$narrative->status" />
                    </a>
                @endforeach
            </div>
        </div>
        @endif

    @endif
</div>
@endsection
