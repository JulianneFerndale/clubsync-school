@extends('layouts.app-officer')
@section('title', $event->title)
@section('club-name', $club?->name ?? 'ClubSync')

@section('content')

{{-- Header --}}
<div class="bg-[#07873e] px-5 pt-5 pb-6">
    <a href="{{ route('officer.activities.index') }}" class="text-white/70 hover:text-white mb-4 inline-block">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
        </svg>
    </a>
    <div class="flex items-start justify-between gap-3">
        <div class="flex-1">
            <h1 class="text-white font-bold text-xl leading-snug">{{ $event->title }}</h1>
            <p class="text-white/60 text-xs mt-1">
                {{ $event->date->format('l, F j, Y') }}
            </p>
        </div>
        <div class="flex flex-col items-end gap-1.5 flex-shrink-0">
            <x-status-badge :status="$event->status" />
            <x-status-badge :status="$event->activity_type" />
            <x-status-badge :status="$event->approval_status" />
        </div>
    </div>
</div>

<div class="px-4 py-5 space-y-5">

    {{-- Key details --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm divide-y divide-gray-50">
        <div class="flex items-center justify-between px-4 py-3">
            <span class="text-xs text-gray-500">Date</span>
            <span class="text-xs font-semibold text-gray-800">{{ $event->date->format('F j, Y') }}</span>
        </div>
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
            <span class="text-xs text-gray-500">Year Levels</span>
            <span class="text-xs font-semibold text-gray-800">{{ $event->target_year_levels_label }}</span>
        </div>
        @if($attendanceCount > 0)
        <div class="flex items-center justify-between px-4 py-3">
            <span class="text-xs text-gray-500">Attendance Recorded</span>
            <span class="text-xs font-semibold text-green-600">{{ $attendanceCount }} checked in</span>
        </div>
        @endif
    </div>

    {{-- Description --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
        <p class="text-xs text-gray-500 mb-1.5 font-medium">Description</p>
        <p class="text-sm text-gray-700 leading-relaxed">{{ $event->description }}</p>
    </div>

    {{-- Purpose --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
        <p class="text-xs text-gray-500 mb-1.5 font-medium">Purpose / Objectives</p>
        <p class="text-sm text-gray-700 leading-relaxed">{{ $event->purpose }}</p>
    </div>

    {{-- Post-event report (if available) --}}
    @if($event->post_report_status)
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
        <div class="flex items-center justify-between">
            <p class="text-sm font-semibold text-gray-700">Post-Activity Report</p>
            <x-status-badge :status="$event->post_report_status" />
        </div>
    </div>
    @endif

    {{-- DSA approval (non-internal activities) --}}
    @if($event->activity_type !== 'internal_meeting')
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 space-y-2">
        <div class="flex items-center justify-between">
            <p class="text-sm font-semibold text-gray-700">DSA Approval</p>
            <x-status-badge :status="$event->approval_status" />
        </div>
        @if($event->approval_status === 'rejected' && $event->review_remarks)
            <p class="text-xs text-red-500">Remarks: {{ $event->review_remarks }}</p>
        @endif
        @if($event->approval_letter_path)
            <a href="{{ route('officer.activities.letter', $event) }}" class="text-xs text-[#07873e] font-semibold underline">
                View uploaded approval letter
            </a>
        @endif
    </div>
    @endif

    {{-- CHED report (ACLE / community involvement activities) --}}
    @if($event->chedReport)
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 space-y-3">
        <div class="flex items-center justify-between">
            <p class="text-sm font-semibold text-gray-700">CHED Report</p>
            <x-status-badge :status="$event->chedReport->is_finalized ? 'approved' : 'pending'" />
        </div>

        <div class="flex items-center gap-3 text-xs">
            <a href="{{ route('officer.reports.pdf', $event->chedReport) }}" class="text-[#07873e] font-semibold underline">Download PDF</a>
            <a href="{{ route('officer.reports.xlsx', $event->chedReport) }}" class="text-[#07873e] font-semibold underline">Download XLSX</a>
        </div>

        @if($event->chedReport->is_finalized)
            <p class="text-xs text-gray-500">{{ $event->chedReport->narrative ?? 'No narrative provided.' }}</p>
        @else
            <form method="POST" action="{{ route('officer.reports.update', $event->chedReport) }}" class="space-y-2">
                @csrf
                @method('PATCH')
                <textarea name="narrative" rows="3" placeholder="Add a narrative summary before finalizing..."
                          class="w-full border border-gray-300 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30">{{ old('narrative', $event->chedReport->narrative) }}</textarea>
                <button type="submit" class="border border-gray-300 text-gray-600 text-xs font-semibold rounded-lg px-3 py-1.5 hover:bg-gray-50 transition-colors">
                    Save Narrative
                </button>
            </form>
            <form method="POST" action="{{ route('officer.reports.finalize', $event->chedReport) }}"
                  onsubmit="return confirm('Finalize this report? It will be sent to the DSA and can no longer be edited.')">
                @csrf
                <button type="submit" class="bg-[#07873e] text-white text-xs font-semibold rounded-lg px-3 py-1.5 hover:opacity-90 transition-opacity">
                    Finalize &amp; Send to DSA
                </button>
            </form>
        @endif
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

    {{-- Action buttons --}}
    @if($event->status === 'scheduled')
        <div class="space-y-3 pt-2">
            {{-- Edit --}}
            <a href="{{ route('officer.activities.edit', $event) }}"
               class="flex items-center justify-between w-full border-2 border-[#07873e] text-[#07873e] font-semibold text-sm rounded-xl px-5 py-4 hover:bg-[#07873e]/5 transition-colors">
                Edit Activity
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/>
                </svg>
            </a>

            {{-- Record Attendance --}}
            <a href="{{ route('officer.activities.attendance', $event) }}"
               class="flex items-center justify-between w-full bg-[#07873e] text-[#F9A825] font-semibold text-sm rounded-xl px-5 py-4 hover:opacity-90 transition-opacity">
                Record Attendance
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                </svg>
            </a>

            {{-- Mark as Completed (Step 7) --}}
            <form method="POST" action="{{ route('officer.activities.complete', $event) }}">
                @csrf
                <button type="submit"
                        class="w-full flex items-center justify-between border-2 border-[#07873e] text-[#07873e] font-semibold text-sm rounded-xl px-5 py-4 hover:bg-[#07873e]/5 transition-colors"
                        onclick="return confirm('Mark this activity as completed? This will generate a post-activity report.')">
                    Mark as Completed
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                </button>
            </form>
        </div>
    @elseif($event->status === 'completed')
        <div class="bg-green-50 border border-green-200 rounded-xl px-4 py-3 text-green-700 text-sm text-center font-medium">
            This activity has been completed.
        </div>
    @endif

</div>
@endsection
