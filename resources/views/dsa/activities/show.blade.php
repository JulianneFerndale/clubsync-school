@extends('layouts.app-dsa')
@section('title', $event->title)

@section('content')

{{-- Header --}}
<div class="bg-[#07873e] px-5 pt-5 pb-6">
    <a href="{{ route('dsa.activities.monitor') }}" class="text-white/70 hover:text-white mb-4 inline-block">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
        </svg>
    </a>
    <div class="flex items-start justify-between gap-3">
        <div class="flex-1">
            <h1 class="text-white font-bold text-xl leading-snug">{{ $event->title }}</h1>
            <p class="text-white/60 text-xs mt-1">{{ $event->club->name ?? '' }} · {{ $event->date->format('l, F j, Y') }}</p>
        </div>
        <div class="flex flex-col items-end gap-1.5 flex-shrink-0">
            <x-status-badge :status="$event->activity_type" />
            <x-status-badge :status="$event->approval_status" />
        </div>
    </div>
</div>

<div class="px-4 py-5 space-y-5">


    {{-- Key details --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm divide-y divide-gray-50">
        <div class="flex items-center justify-between px-4 py-3">
            <span class="text-xs text-gray-500">Time</span>
            <span class="text-xs font-semibold text-gray-800">
                {{ \Carbon\Carbon::parse($event->time_start)->format('g:i A') }}
                – {{ \Carbon\Carbon::parse($event->time_end)->format('g:i A') }}
            </span>
        </div>
        <div class="flex items-center justify-between px-4 py-3">
            <span class="text-xs text-gray-500">Venue</span>
            <span class="text-xs font-semibold text-gray-800">{{ $event->venue }}</span>
        </div>
        <div class="flex items-center justify-between px-4 py-3">
            <span class="text-xs text-gray-500">Expected</span>
            <span class="text-xs font-semibold text-gray-800">{{ number_format($event->expected_participants) }} participants</span>
        </div>
        <div class="flex items-center justify-between px-4 py-3">
            <span class="text-xs text-gray-500">Created By</span>
            <span class="text-xs font-semibold text-gray-800">{{ $event->creator?->name ?? 'Unknown' }}</span>
        </div>
        @if($event->approvedBy)
        <div class="flex items-center justify-between px-4 py-3">
            <span class="text-xs text-gray-500">Reviewed By</span>
            <span class="text-xs font-semibold text-gray-800">{{ $event->approvedBy->name }}</span>
        </div>
        @endif
    </div>

    {{-- Purpose --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
        <p class="text-xs text-gray-500 mb-1.5 font-medium">Purpose / Objectives</p>
        <p class="text-sm text-gray-700 leading-relaxed">{{ $event->purpose }}</p>
    </div>

    @if($event->approval_letter_path)
        <a href="{{ route('dsa.activities.letter', $event) }}" class="text-sm text-[#07873e] font-semibold underline">
            View uploaded approval letter
        </a>
    @endif

    @if($event->approval_status === 'rejected' && $event->review_remarks)
        <div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-red-700 text-sm">
            Adviser's remarks: {{ $event->review_remarks }}
        </div>
    @endif

    @if($event->approval_status === 'pending_approval')
        <div class="bg-[#F9A825]/10 border border-[#F9A825]/40 rounded-xl px-4 py-3">
            <p class="text-sm text-gray-700">
                Waiting on {{ $event->club->adviserUser?->name ?? 'the club adviser' }} to review.
            </p>
        </div>
    @endif

    {{-- Change history --}}
    @if($event->changeLogs->isNotEmpty())
    <details class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
        <summary class="text-sm font-semibold text-gray-700 cursor-pointer">Change History ({{ $event->changeLogs->count() }})</summary>
        <div class="mt-3 space-y-3 divide-y divide-gray-50">
            @foreach($event->changeLogs as $log)
                <div class="pt-3 first:pt-0">
                    <p class="text-xs text-gray-400">
                        {{ $log->changedBy?->name ?? 'Unknown' }} · {{ $log->created_at->format('M j, Y g:i A') }}
                    </p>
                    <ul class="mt-1 space-y-0.5">
                        @foreach($log->changes as $change)
                            <li class="text-xs text-gray-600">
                                <span class="font-medium">{{ ucfirst(str_replace('_', ' ', $change['field'])) }}:</span>
                                {{ $change['before'] }} → {{ $change['after'] }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </details>
    @endif

</div>
@endsection
