@extends('layouts.app-adviser')
@section('title', 'Semester Reports')
@section('page-title', 'Semester Reports')

@section('content')

<div class="bg-[#07873e] px-5 pt-5 pb-6">
    <h1 class="text-white font-bold text-xl">Semester Narrative Reports</h1>
    <p class="text-white/60 text-xs mt-1">Drafted when a semester ends · reviewed by you · submitted to the DSA</p>
</div>

<div class="px-4 py-5 space-y-5">

    @if(! $hasClub)
        <p class="text-sm text-gray-400 text-center py-8">No club assigned.</p>
    @else

        {{-- Awaiting review --}}
        <div>
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-2 px-1">
                Awaiting Your Review
                @if($pending->isNotEmpty())
                    <span class="ml-1 bg-[#F9A825] text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full">{{ $pending->count() }}</span>
                @endif
            </p>

            @if($pending->isEmpty())
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-5 text-center">
                    <p class="text-sm text-gray-400">No reports awaiting review.</p>
                </div>
            @else
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden divide-y divide-gray-50">
                    @foreach($pending as $report)
                        <a href="{{ route('adviser.semester-reports.show', $report) }}"
                           class="flex items-start gap-3 px-4 py-3.5 hover:bg-gray-50 transition-colors">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-800 truncate">{{ $report->semester->label }}</p>
                                <p class="text-xs text-gray-400 mt-0.5">
                                    {{ $report->club->name }} · {{ $report->activity_data['totals']['activities'] }} activities · drafted {{ $report->created_at->diffForHumans() }}
                                </p>
                                <div class="flex items-center gap-2 mt-1.5">
                                    @if($report->ai_available)
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

        {{-- Submitted --}}
        @if($submitted->isNotEmpty())
        <div>
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-2 px-1">Submitted to the DSA</p>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden divide-y divide-gray-50">
                @foreach($submitted as $report)
                    <a href="{{ route('adviser.semester-reports.show', $report) }}"
                       class="flex items-center justify-between gap-3 px-4 py-3 hover:bg-gray-50 transition-colors">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-800 truncate">{{ $report->semester->label }}</p>
                            <p class="text-xs text-gray-400 mt-0.5">{{ $report->club->name }} · {{ $report->submitted_at?->format('M j, Y') }}</p>
                        </div>
                        <x-status-badge status="submitted" />
                    </a>
                @endforeach
            </div>
        </div>
        @endif

    @endif
</div>
@endsection
