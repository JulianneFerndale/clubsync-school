@extends('layouts.app-dsa')
@section('title', ($club->acronym ?? $club->name) . ' Performance — DSA')
@section('page-title', 'Club Performance')
@section('live-refresh', 'any')

@use('App\Models\ClubActivity')

@php
    $percent = fn (?float $share) => $share === null ? '—' : round($share * 100) . '%';
    $labels  = [
        'activities' => 'Activities held',
        'turnout'    => 'Turnout',
        'presence'   => 'Presence update',
        'fees'       => 'Fee collection',
        'reports'    => 'Semester reports',
    ];
    $maxHeld = max(1, collect($row['monthly'])->max('held'));
@endphp

@section('content')

<div class="bg-[#07873e] px-5 py-4">
    <a href="{{ route('dsa.club-performance.index', ['semester' => $semester->id]) }}" class="inline-flex items-center gap-1 text-white/70 hover:text-white text-xs mb-1.5">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
        All clubs
    </a>
    <h1 class="text-white font-bold text-xl">{{ $club->name }}</h1>
    <p class="text-white/60 text-xs mt-0.5">
        {{ $club->acronym }} · {{ $club->isAcademicType() ? 'Academic' : 'Non-academic' }} club
        @if($club->adviserUser) · Adviser: {{ $club->adviserUser->name }} @endif
    </p>
</div>

<div class="px-4 py-5 space-y-4 max-w-4xl">

    <form method="GET" action="{{ route('dsa.club-performance.show', $club) }}" class="flex flex-wrap items-center gap-2">
        <label for="semester" class="text-xs text-gray-500">Semester</label>
        <select id="semester" name="semester" onchange="this.form.submit()"
                class="flex-1 sm:flex-none min-w-0 border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#07873e]/30">
            @foreach($semesters as $option)
                <option value="{{ $option->id }}" @selected($option->id === $semester->id)>{{ $option->label }}{{ $option->is_current ? ' (current)' : '' }}</option>
            @endforeach
        </select>
        <a href="{{ route('dsa.clubs.show', $club) }}" class="ml-auto text-xs font-semibold text-[#07873e] hover:underline">Club profile →</a>
    </form>

    {{-- Headline: the score and what it is made of --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Performance score</p>
                <p class="text-5xl font-bold text-gray-800 tabular-nums leading-none mt-1">{{ $row['score'] ?? '—' }}<span class="text-lg text-gray-400 font-semibold">/100</span></p>
            </div>
            @include('dsa.club-performance._standing', ['standing' => $row['standing']])
        </div>

        <div class="mt-5 space-y-3">
            @foreach($row['components'] as $key => $component)
                <div class="{{ $component['applies'] ? '' : 'opacity-60' }}">
                    <div class="flex items-baseline justify-between gap-3 text-xs">
                        <span class="font-semibold text-gray-700">{{ $labels[$key] }} <span class="text-gray-400 font-normal">· weight {{ $component['weight'] }}</span></span>
                        <span class="font-bold text-gray-700 tabular-nums">{{ $component['applies'] ? round($component['value'] * 100) . '%' : 'Not counted yet' }}</span>
                    </div>
                    <div class="mt-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                        <div class="h-full bg-[#07873e] rounded-full" style="width: {{ $component['applies'] ? round($component['value'] * 100) : 0 }}%"></div>
                    </div>
                    <p class="text-[11px] text-gray-400 mt-1">{{ $component['detail'] }}</p>
                </div>
            @endforeach
            @if($row['open_notices'])
                <p class="text-xs text-red-600 font-semibold">
                    −{{ $row['open_notices'] * \App\Services\ClubPerformanceService::NOTICE_PENALTY }} points for {{ $row['open_notices'] }} unresolved compliance {{ Str::plural('notice', $row['open_notices']) }}
                </p>
            @endif
        </div>
    </div>

    {{-- Throughout the semester: two charts, one measure each (never a shared axis) --}}
    <div class="grid md:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
            <p class="text-sm font-semibold text-gray-800">Activities held each month</p>
            <div class="mt-4 flex items-end gap-3 h-32 border-b border-gray-200" role="img" aria-label="Activities held each month">
                @foreach($row['monthly'] as $month)
                    @php $tip = $month['future'] ? $month['label'] . ': still ahead' : $month['label'] . ': ' . $month['held'] . ' held'; @endphp
                    <div class="flex-1 h-full flex flex-col justify-end items-center gap-1" title="{{ $tip }}">
                        <span class="text-[11px] font-semibold tabular-nums {{ $month['future'] ? 'text-gray-300' : 'text-gray-600' }}">{{ $month['future'] ? '' : $month['held'] }}</span>
                        @if($month['future'])
                            <div class="w-full max-w-10 h-1 rounded-t border border-dashed border-gray-200"></div>
                        @else
                            <div class="w-full max-w-10 rounded-t bg-[#07873e]" style="height: {{ $month['held'] ? max(4, round($month['held'] / $maxHeld * 100)) : 0 }}%"></div>
                        @endif
                    </div>
                @endforeach
            </div>
            <div class="flex gap-3 mt-1.5">
                @foreach($row['monthly'] as $month)
                    <span class="flex-1 text-center text-[11px] text-gray-400">{{ $month['label'] }}</span>
                @endforeach
            </div>
        </div>

        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
            <p class="text-sm font-semibold text-gray-800">Turnout each month <span class="text-gray-400 font-normal text-xs">· share of active members</span></p>
            <div class="mt-4 flex items-end gap-3 h-32 border-b border-gray-200" role="img" aria-label="Average turnout each month">
                @foreach($row['monthly'] as $month)
                    @php $tip = $month['label'] . ': ' . ($month['future'] ? 'still ahead' : ($month['turnout'] === null ? 'no activity held' : $percent($month['turnout']) . ' average turnout')); @endphp
                    <div class="flex-1 h-full flex flex-col justify-end items-center gap-1" title="{{ $tip }}">
                        <span class="text-[11px] font-semibold tabular-nums text-gray-600">{{ $month['turnout'] === null ? '' : $percent($month['turnout']) }}</span>
                        @if($month['turnout'] !== null)
                            <div class="w-full max-w-10 rounded-t bg-[#07873e]/70" style="height: {{ max(4, round($month['turnout'] * 100)) }}%"></div>
                        @endif
                    </div>
                @endforeach
            </div>
            <div class="flex gap-3 mt-1.5">
                @foreach($row['monthly'] as $month)
                    <span class="flex-1 text-center text-[11px] text-gray-400">{{ $month['label'] }}</span>
                @endforeach
            </div>
        </div>
    </div>

    {{-- The rest of the picture --}}
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
        @foreach([
            ['Active members', $row['members'], $row['new_members'] ? '+' . $row['new_members'] . ' approved this semester' : 'none approved this semester'],
            ['Applicants waiting', $row['applicants'], 'pending officer or adviser review'],
            ['Announcements', $row['announcements'], 'published this semester'],
            ['Reports filed', $row['reports_filed'] . '/' . count(\App\Models\ClubReportSubmission::TYPES), $row['reports_accepted'] . ' accepted by the DSA'],
            ['Upcoming', $row['upcoming'], 'confirmed activities still ahead'],
            ['Awaiting adviser', $row['pending'], 'activities pending approval'],
            ['Rejected', $row['rejected'], 'activities not approved'],
            ['Open notices', $row['open_notices'], 'compliance notices unresolved'],
        ] as [$label, $value, $note])
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-3.5">
                <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide">{{ $label }}</p>
                <p class="text-xl font-bold text-gray-800 mt-0.5 tabular-nums">{{ $value }}</p>
                <p class="text-[11px] text-gray-400 mt-0.5 leading-snug">{{ $note }}</p>
            </div>
        @endforeach
    </div>

    {{-- Every activity this semester, doubling as the charts' table view --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
        <p class="px-4 py-3 text-sm font-semibold text-gray-800 border-b border-gray-100">Activities this semester</p>
        @forelse($row['activities'] as $item)
            @php
                $activity = $item['activity'];
                $state = match (true) {
                    $activity->status === 'completed'                 => ['Held', 'bg-green-50 text-green-700 border-green-200'],
                    $activity->approval_status === 'pending_approval' => ['Awaiting adviser', 'bg-amber-50 text-amber-700 border-amber-200'],
                    $activity->approval_status === 'rejected'         => ['Rejected', 'bg-red-50 text-red-700 border-red-200'],
                    $activity->status === 'cancelled'                 => ['Cancelled', 'bg-gray-50 text-gray-500 border-gray-200'],
                    default                                           => ['Scheduled', 'bg-blue-50 text-blue-700 border-blue-200'],
                };
            @endphp
            <a href="{{ route('dsa.activities.show', $activity) }}" class="flex items-center gap-3 px-4 py-3 border-b border-gray-50 last:border-b-0 hover:bg-[#07873e]/5 transition-colors">
                <div class="w-11 text-center flex-shrink-0">
                    <p class="text-[10px] font-semibold text-gray-400 uppercase">{{ $activity->date->format('M') }}</p>
                    <p class="text-lg font-bold text-gray-800 leading-none">{{ $activity->date->format('j') }}</p>
                </div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-semibold text-gray-800 truncate">{{ $activity->title }}</p>
                    <p class="text-[11px] text-gray-400 truncate">{{ ClubActivity::TYPE_LABELS[$activity->activity_type] ?? 'Activity' }}</p>
                </div>
                <div class="text-right flex-shrink-0">
                    <span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-semibold border {{ $state[1] }}">{{ $state[0] }}</span>
                    @if($item['attendees'] !== null)
                        <p class="text-[11px] text-gray-500 mt-1 tabular-nums">{{ $item['attendees'] }} attended · {{ $percent($item['turnout']) }}</p>
                    @endif
                </div>
            </a>
        @empty
            <p class="text-sm text-gray-400 text-center py-8">No activities dated within {{ $semester->label }}.</p>
        @endforelse
    </div>
</div>
@endsection
