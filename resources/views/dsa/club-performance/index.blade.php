@extends('layouts.app-dsa')
@section('title', 'Club Performance — DSA')
@section('page-title', 'Club Performance')
@section('live-refresh', 'any')

@use('App\Services\ClubPerformanceService', 'Performance')

@php
    $filterUrl = fn (array $change) => route('dsa.club-performance.index', array_filter(array_merge(
        ['semester' => $semester?->id, 'type' => $type, 'standing' => $standing], $change,
    )));
    $percent = fn (?float $share) => $share === null ? '—' : round($share * 100) . '%';

    // Days of the semester gone by, and how many it has.
    $length   = $semester ? (int) $semester->start_date->diffInDays($semester->end_date) + 1 : 1;
    $elapsed  = $semester ? max(0, min($length, (int) $semester->start_date->diffInDays(today()) + 1)) : 0;
    $progress = (int) round($elapsed / $length * 100);
@endphp

@section('content')

<div class="bg-[#07873e] px-5 py-4">
    <h1 class="text-white font-bold text-xl">Club Performance</h1>
    <p class="text-white/60 text-xs mt-0.5">How each club has been doing this semester — activities, turnout, upkeep and reports</p>
</div>

<div class="px-4 py-5 space-y-4 max-w-5xl">

    @if(! $semester)
        <p class="text-sm text-gray-400 text-center py-8">No semesters have been set up yet.</p>
    @else

        {{-- Filters: semester and club type in one row --}}
        <div class="flex flex-wrap items-center gap-2">
            <form method="GET" action="{{ route('dsa.club-performance.index') }}" class="flex items-center gap-2 w-full sm:w-auto">
                <label for="semester" class="text-xs text-gray-500">Semester</label>
                <select id="semester" name="semester" onchange="this.form.submit()"
                        class="flex-1 sm:flex-none min-w-0 border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#07873e]/30">
                    @foreach($semesters as $option)
                        <option value="{{ $option->id }}" @selected($option->id === $semester->id)>{{ $option->label }}{{ $option->is_current ? ' (current)' : '' }}</option>
                    @endforeach
                </select>
                @if($type)<input type="hidden" name="type" value="{{ $type }}">@endif
            </form>
            <div class="flex gap-1.5">
                @foreach(['' => 'All clubs', 'academic' => 'Academic', 'non_academic' => 'Non-academic'] as $key => $label)
                    <a href="{{ $filterUrl(['type' => $key, 'standing' => null]) }}"
                       class="px-3 py-1.5 rounded-full text-xs font-semibold border transition-colors
                              {{ ($type ?? '') === $key ? 'bg-[#07873e] text-[#F9A825] border-[#07873e]' : 'bg-white text-gray-600 border-gray-200 hover:border-[#07873e]/40' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
        </div>

        {{-- Semester headline figures --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Semester progress</p>
                <p class="text-2xl font-bold text-gray-800 mt-1 tabular-nums">{{ $progress }}%</p>
                <div class="mt-2 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full bg-[#07873e] rounded-full" style="width: {{ $progress }}%"></div>
                </div>
                <p class="text-[11px] text-gray-400 mt-1.5">{{ $semester->start_date->format('M j') }} – {{ $semester->end_date->format('M j, Y') }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Activities held</p>
                <p class="text-2xl font-bold text-gray-800 mt-1 tabular-nums">{{ $summary['held'] }}</p>
                <p class="text-[11px] text-gray-400 mt-1.5">across {{ $summary['clubs'] }} {{ Str::plural('club', $summary['clubs']) }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Average turnout</p>
                <p class="text-2xl font-bold text-gray-800 mt-1 tabular-nums">{{ $percent($summary['turnout']) }}</p>
                <p class="text-[11px] text-gray-400 mt-1.5">of active members, clubs that held activities</p>
            </div>
            <a href="{{ route('dsa.violations.index') }}" class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 hover:shadow-md transition-shadow">
                <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Open compliance notices</p>
                <p class="text-2xl font-bold text-gray-800 mt-1 tabular-nums">{{ $summary['notices'] }}</p>
                <p class="text-[11px] text-gray-400 mt-1.5">sent and not yet resolved</p>
            </a>
        </div>

        {{-- Standing chips double as the filter --}}
        <div class="flex gap-2 overflow-x-auto pb-1 -mx-1 px-1">
            <a href="{{ $filterUrl(['standing' => null]) }}"
               class="flex-shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold border transition-colors
                      {{ ! $standing ? 'bg-[#07873e] text-[#F9A825] border-[#07873e]' : 'bg-white text-gray-600 border-gray-200 hover:border-[#07873e]/40' }}">
                Every standing
                <span class="{{ ! $standing ? 'bg-[#F9A825]/20 text-[#F9A825]' : 'bg-gray-100 text-gray-500' }} rounded-full px-1.5 py-0.5 text-[10px] leading-none">{{ $summary['clubs'] }}</span>
            </a>
            @foreach(Performance::STANDINGS as $key => $label)
                @continue($key === 'not_started' && ! $summary['standings'][$key])
                <a href="{{ $filterUrl(['standing' => $key]) }}"
                   class="flex-shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold border transition-colors
                          {{ $standing === $key ? 'bg-[#07873e] text-[#F9A825] border-[#07873e]' : 'bg-white text-gray-600 border-gray-200 hover:border-[#07873e]/40' }}">
                    {{ $label }}
                    <span class="{{ $standing === $key ? 'bg-[#F9A825]/20 text-[#F9A825]' : 'bg-gray-100 text-gray-500' }} rounded-full px-1.5 py-0.5 text-[10px] leading-none">{{ $summary['standings'][$key] }}</span>
                </a>
            @endforeach
        </div>

        {{-- One row per club, best first --}}
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
            <div class="hidden md:grid grid-cols-[minmax(0,1fr)_9rem_4.5rem_4.5rem_5.5rem_auto] gap-4 px-4 py-2.5 bg-gray-50 border-b border-gray-100 text-[11px] font-semibold text-gray-400 uppercase tracking-wide">
                <span>Club</span>
                <span>Score</span>
                <span class="text-right">Held</span>
                <span class="text-right">Turnout</span>
                <span class="text-right">Members</span>
                <span>By month</span>
            </div>

            @forelse($rows as $row)
                @php $club = $row['club']; @endphp
                <a href="{{ route('dsa.club-performance.show', ['club' => $club, 'semester' => $semester->id]) }}"
                   class="grid grid-cols-3 md:grid-cols-[minmax(0,1fr)_9rem_4.5rem_4.5rem_5.5rem_auto] gap-x-4 gap-y-2.5 items-center px-4 py-3.5 border-b border-gray-50 last:border-b-0 hover:bg-[#07873e]/5 transition-colors">

                    <div class="col-span-3 md:col-span-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <p class="text-sm font-semibold text-gray-800 truncate">{{ $club->name }}</p>
                            @if($row['open_notices'])
                                <span class="inline-flex items-center gap-0.5 text-[10px] font-semibold text-red-600" title="{{ $row['open_notices'] }} unresolved compliance {{ Str::plural('notice', $row['open_notices']) }}">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z"/></svg>
                                    {{ $row['open_notices'] }} open {{ Str::plural('notice', $row['open_notices']) }}
                                </span>
                            @endif
                        </div>
                        <div class="flex items-center gap-2 mt-1">
                            @include('dsa.club-performance._standing', ['standing' => $row['standing']])
                            <span class="text-[11px] text-gray-400 truncate">{{ $club->acronym }} · {{ $club->isAcademicType() ? 'Academic' : 'Non-academic' }}</span>
                        </div>
                    </div>

                    {{-- Score meter: one ratio against 100 --}}
                    <div class="col-span-3 md:col-span-1 flex items-center gap-2" title="Performance score {{ $row['score'] ?? 'not available' }} out of 100">
                        <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden">
                            <div class="h-full bg-[#07873e] rounded-full" style="width: {{ $row['score'] ?? 0 }}%"></div>
                        </div>
                        <span class="text-sm font-bold text-gray-700 tabular-nums w-8 text-right">{{ $row['score'] ?? '—' }}</span>
                    </div>

                    <div class="md:text-right">
                        <p class="text-sm font-semibold text-gray-800 tabular-nums">{{ $row['held'] }}</p>
                        <p class="text-[10px] text-gray-400">{{ $row['upcoming'] ? $row['upcoming'] . ' upcoming' : 'held' }}</p>
                    </div>
                    <div class="text-center md:text-right">
                        <p class="text-sm font-semibold text-gray-800 tabular-nums">{{ $percent($row['turnout']) }}</p>
                        <p class="text-[10px] text-gray-400">turnout</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold text-gray-800 tabular-nums">{{ $row['members'] }}</p>
                        <p class="text-[10px] text-gray-400">{{ $row['new_members'] ? '+' . $row['new_members'] . ' this sem.' : 'active' }}</p>
                    </div>

                    <div class="col-span-3 md:col-span-1 flex md:block">
                        @include('dsa.club-performance._months', ['monthly' => $row['monthly']])
                    </div>
                </a>
            @empty
                <p class="text-sm text-gray-400 text-center py-8">
                    {{ $standing ? 'No club has this standing.' : 'No active clubs to show.' }}
                </p>
            @endforelse
        </div>

        {{-- The score, explained --}}
        <details class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3 text-sm text-gray-600">
            <summary class="cursor-pointer font-semibold text-gray-700">How the score is worked out</summary>
            <div class="mt-3 space-y-2 text-xs leading-relaxed">
                <p>Each club gets a score out of 100 for the semester so far, from five measures:</p>
                <ul class="space-y-1 list-disc pl-5">
                    <li><strong>Activities held ({{ Performance::WEIGHTS['activities'] }})</strong> — completed activities against one a month so far.</li>
                    <li><strong>Turnout ({{ Performance::WEIGHTS['turnout'] }})</strong> — the average share of active members recorded at those activities.</li>
                    <li><strong>Presence update ({{ Performance::WEIGHTS['presence'] }})</strong> — members the officers have given a semester status, counted after the first {{ Performance::PRESENCE_GRACE_DAYS }} days.</li>
                    <li><strong>Fee collection ({{ Performance::WEIGHTS['fees'] }})</strong> — payments settled for fees already due.</li>
                    <li><strong>Semester reports ({{ Performance::WEIGHTS['reports'] }})</strong> — report types filed, counted from {{ Performance::REPORT_SEASON_DAYS }} days before the semester ends.</li>
                </ul>
                <p>A measure that does not apply yet is left out and the others are re-weighted. Each compliance notice the club has not resolved takes {{ Performance::NOTICE_PENALTY }} points off.</p>
                <p><strong>Performing well</strong> is {{ Performance::STRONG }} or more, <strong>Fair</strong> is {{ Performance::FAIR }}–{{ Performance::STRONG - 1 }}, and <strong>Needs support</strong> is below {{ Performance::FAIR }}. A club that has not held an activity yet is shown as <strong>No activities held</strong> whatever its score.</p>
                <p class="text-gray-400">These are club-level figures only. No member is scored or named here.</p>
            </div>
        </details>

    @endif
</div>
@endsection
