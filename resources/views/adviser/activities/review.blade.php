@extends('layouts.app-adviser')
@section('title', 'Activity Review')
@section('page-title', 'Activity Review')

@section('live-refresh', 'any')
@section('content')

<div class="bg-[#07873e] px-5 pt-5 pb-6">
    <h1 class="text-white font-bold text-xl">Activity Review</h1>
    <p class="text-white/60 text-xs mt-1">{{ $club?->name ?? 'No club assigned' }}</p>
</div>

<div class="px-4 py-5 space-y-5">


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
                    <p class="text-sm text-gray-400">No activities pending review.</p>
                    <p class="text-xs text-gray-400 mt-1">Internal meetings are scheduled without review.</p>
                </div>
            @else
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden divide-y divide-gray-50">
                    @foreach($pending as $event)
                        <a href="{{ route('adviser.activities.show', $event) }}"
                           class="block px-4 py-3.5 hover:bg-gray-50 transition-colors">
                            <p class="text-sm font-semibold text-gray-800 truncate">{{ $event->title }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">
                                {{ $event->date->format('M j, Y') }}
                                · {{ \Carbon\Carbon::parse($event->time_start)->format('g:i A') }}
                                · {{ $event->venue }}
                            </p>
                            <div class="flex items-center gap-2 mt-1.5">
                                <x-status-badge :status="$event->activity_type" />
                                <span class="text-xs text-gray-400">
                                    by {{ $event->creator?->name ?? 'Unknown' }}
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Already decided --}}
        <div>
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-2 px-1">Recently Reviewed</p>

            @if($reviewed->isEmpty())
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-5 text-center">
                    <p class="text-sm text-gray-400">Nothing reviewed yet.</p>
                </div>
            @else
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden divide-y divide-gray-50">
                    @foreach($reviewed as $event)
                        <a href="{{ route('adviser.activities.show', $event) }}"
                           class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-gray-50 transition-colors">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-700 truncate">{{ $event->title }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">{{ $event->date->format('M j, Y') }}</p>
                            </div>
                            <x-status-badge :status="$event->approval_status" />
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

    @endif
</div>

@endsection
