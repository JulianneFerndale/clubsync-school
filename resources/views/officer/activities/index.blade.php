@extends('layouts.app-officer')
@section('title', 'Calendar — ' . ($club?->name ?? 'ClubSync'))
@section('club-name', $club?->name ?? 'ClubSync')

@section('live-refresh', 'any')
@section('content')

{{-- Month navigation header --}}
<div class="bg-[#07873e] px-5 py-4">
    <div class="flex items-center justify-between">
        <a href="{{ route('officer.activities.index', ['month' => $prevMonth->month, 'year' => $prevMonth->year]) }}"
           class="p-1.5 text-white/70 hover:text-white transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 19.5 8.25 12l7.5-7.5"/>
            </svg>
        </a>
        <div class="text-center">
            <p class="text-white font-bold text-lg">{{ $startOfMonth->format('F Y') }}</p>
        </div>
        <a href="{{ route('officer.activities.index', ['month' => $nextMonth->month, 'year' => $nextMonth->year]) }}"
           class="p-1.5 text-white/70 hover:text-white transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
            </svg>
        </a>
    </div>

    {{-- Calendar grid --}}
    @php
        $dayNames   = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $startDow   = $startOfMonth->dayOfWeek; // 0=Sun
        $daysInMonth = $startOfMonth->daysInMonth;
        $today      = now()->format('Y-m-d');
    @endphp

    {{-- Day name row --}}
    <div class="grid grid-cols-7 mt-4 mb-1">
        @foreach($dayNames as $d)
            <div class="text-center text-[10px] font-semibold text-white/50">{{ $d }}</div>
        @endforeach
    </div>

    {{-- Day cells --}}
    <div class="grid grid-cols-7 gap-y-1">
        {{-- Leading empty cells --}}
        @for($i = 0; $i < $startDow; $i++)
            <div></div>
        @endfor

        {{-- Actual days --}}
        @for($day = 1; $day <= $daysInMonth; $day++)
            @php
                $dateStr   = $startOfMonth->copy()->setDay($day)->format('Y-m-d');
                $hasEvents = isset($monthEvents[$dateStr]) && $monthEvents[$dateStr]->count() > 0;
                $isToday   = $dateStr === $today;
            @endphp
            <div class="flex flex-col items-center py-0.5">
                <span class="w-8 h-8 flex items-center justify-center rounded-full text-sm font-medium
                    {{ $isToday ? 'bg-[#F9A825] text-[#07873e] font-bold' : 'text-white' }}">
                    {{ $day }}
                </span>
                @if($hasEvents)
                    <span class="w-1.5 h-1.5 rounded-full bg-[#F9A825] mt-0.5"></span>
                @else
                    <span class="w-1.5 h-1.5 mt-0.5"></span>
                @endif
            </div>
        @endfor
    </div>
</div>

{{-- Flash messages --}}
<div class="px-4 pt-4 space-y-2">
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 rounded-xl px-4 py-3 text-green-700 text-sm">
            {{ session('success') }}
        </div>
    @endif
</div>

{{-- Upcoming activities grouped by month --}}
<div class="px-4 py-4 space-y-5">

    @if(! $club)
        <p class="text-sm text-gray-400 text-center py-8">No club assigned.</p>
    @elseif($upcomingGrouped->isEmpty())
        <div class="text-center py-10">
            <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
                <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                </svg>
            </div>
            <p class="text-sm text-gray-400 font-medium">No upcoming activities</p>
            <p class="text-xs text-gray-300 mt-1">Tap + to schedule one</p>
        </div>
    @else
        @foreach($upcomingGrouped as $monthLabel => $events)
            {{-- Month group label --}}
            <div>
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest mb-2 px-1">
                    {{ $monthLabel }}
                </p>
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden divide-y divide-gray-50">
                    @foreach($events as $event)
                        <a href="{{ route('officer.activities.show', $event) }}"
                           class="flex items-start gap-3 px-4 py-3.5 hover:bg-gray-50 transition-colors">

                            {{-- Date badge --}}
                            <div class="bg-[#F9A825] rounded-xl px-2.5 py-2 flex flex-col items-center min-w-[44px] flex-shrink-0">
                                <span class="text-white font-bold text-lg leading-none">{{ $event->date->format('d') }}</span>
                                <span class="text-white text-[9px] font-semibold uppercase">{{ $event->date->format('D') }}</span>
                            </div>

                            {{-- Info --}}
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-900 truncate">{{ $event->title }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">
                                    {{ \Carbon\Carbon::parse($event->time_start)->format('g:i A') }}
                                    – {{ \Carbon\Carbon::parse($event->time_end)->format('g:i A') }}
                                </p>
                                <div class="flex items-center gap-2 mt-1.5">
                                    <span class="inline-block px-2 py-0.5 bg-[#07873e]/10 text-[#07873e] text-[10px] font-semibold rounded-full">
                                        {{ $event->club->acronym ?? $event->club->name }}
                                    </span>
                                    <x-status-badge :status="$event->activity_type" />
                                </div>
                            </div>

                            <svg class="w-4 h-4 text-gray-400 mt-1 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                            </svg>
                        </a>
                    @endforeach
                </div>
            </div>
        @endforeach
    @endif
</div>

{{-- FAB: Add activity --}}
<a href="{{ route('officer.activities.create') }}"
   class="fixed bottom-20 right-4 w-14 h-14 bg-[#07873e] rounded-full shadow-lg flex items-center justify-center hover:opacity-90 transition-opacity z-30">
    <svg class="w-7 h-7 text-[#F9A825]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4.5v15m7.5-7.5h-15"/>
    </svg>
</a>

@endsection
