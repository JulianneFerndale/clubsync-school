@extends('layouts.app-officer')
@section('title', 'Attendance — ' . $event->title)
@section('club-name', $club?->name ?? 'ClubSync')

@section('content')

{{-- Header --}}
<div class="bg-[#07873e] px-5 pt-5 pb-6">
    <a href="{{ route('officer.activities.show', $event) }}" class="text-white/70 hover:text-white mb-4 inline-block">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
        </svg>
    </a>
    <h1 class="text-white font-bold text-xl leading-snug">Record Attendance</h1>
    <p class="text-white/60 text-xs mt-1">{{ $event->title }} · {{ $event->date->format('F j, Y') }}</p>
</div>

<div class="px-4 py-5 space-y-4">

    {{-- Summary bar --}}
    @php
        $checkedIn  = $attendance->where('time_in', '!=', null)->count();
        $checkedOut = $attendance->where('time_out', '!=', null)->count();
        $total      = $members->count();
    @endphp
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm grid grid-cols-3 divide-x divide-gray-100">
        <div class="flex flex-col items-center py-3">
            <span class="text-lg font-bold text-gray-800">{{ $total }}</span>
            <span class="text-[10px] text-gray-400 mt-0.5">Members</span>
        </div>
        <div class="flex flex-col items-center py-3">
            <span class="text-lg font-bold text-[#07873e]">{{ $checkedIn }}</span>
            <span class="text-[10px] text-gray-400 mt-0.5">Checked In</span>
        </div>
        <div class="flex flex-col items-center py-3">
            <span class="text-lg font-bold text-[#F9A825]">{{ $checkedOut }}</span>
            <span class="text-[10px] text-gray-400 mt-0.5">Checked Out</span>
        </div>
    </div>

    {{-- Member list --}}
    @if($members->isEmpty())
        <div class="text-center py-10">
            <p class="text-sm text-gray-400">No active members found in this club.</p>
        </div>
    @else
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden divide-y divide-gray-50">
            @foreach($members as $member)
                @php
                    $record   = $attendance->get($member->id);
                    $hasIn    = $record && $record->time_in;
                    $hasOut   = $record && $record->time_out;
                @endphp
                <div class="flex items-center gap-3 px-4 py-3">

                    {{-- Avatar --}}
                    <div class="w-9 h-9 rounded-full bg-[#07873e]/10 flex items-center justify-center flex-shrink-0">
                        <span class="text-[#07873e] text-xs font-bold">
                            {{ strtoupper(substr($member->first_name, 0, 1) . substr($member->last_name, 0, 1)) }}
                        </span>
                    </div>

                    {{-- Name + times --}}
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-800 truncate">{{ $member->first_name }} {{ $member->last_name }}</p>
                        @if($hasIn)
                            <p class="text-[10px] text-gray-400 mt-0.5">
                                IN {{ $record->time_in->format('g:i A') }}
                                @if($hasOut)
                                    · OUT {{ $record->time_out->format('g:i A') }}
                                @endif
                            </p>
                        @else
                            <p class="text-[10px] text-gray-400 mt-0.5">Not yet checked in</p>
                        @endif
                    </div>

                    {{-- Action buttons --}}
                    <div class="flex items-center gap-1.5 flex-shrink-0">
                        @if(! $hasIn)
                            {{-- Check In --}}
                            <form method="POST" action="{{ route('officer.activities.attendance.record', [$event, $member]) }}">
                                @csrf
                                <input type="hidden" name="action" value="in">
                                <button type="submit"
                                        class="bg-[#07873e] text-white text-[10px] font-semibold rounded-lg px-3 py-1.5 hover:opacity-90 transition-opacity">
                                    IN
                                </button>
                            </form>
                        @elseif(! $hasOut)
                            {{-- Check Out --}}
                            <form method="POST" action="{{ route('officer.activities.attendance.record', [$event, $member]) }}">
                                @csrf
                                <input type="hidden" name="action" value="out">
                                <button type="submit"
                                        class="bg-[#F9A825] text-white text-[10px] font-semibold rounded-lg px-3 py-1.5 hover:opacity-90 transition-opacity">
                                    OUT
                                </button>
                            </form>
                            {{-- Undo check-in --}}
                            <form method="POST" action="{{ route('officer.activities.attendance.record', [$event, $member]) }}">
                                @csrf
                                <input type="hidden" name="action" value="undo">
                                <button type="submit"
                                        class="border border-gray-200 text-gray-400 text-[10px] font-semibold rounded-lg px-2 py-1.5 hover:border-red-300 hover:text-red-400 transition-colors">
                                    ✕
                                </button>
                            </form>
                        @else
                            {{-- Both recorded --}}
                            <span class="bg-green-100 text-green-700 text-[10px] font-semibold rounded-lg px-3 py-1.5">Done</span>
                            <form method="POST" action="{{ route('officer.activities.attendance.record', [$event, $member]) }}">
                                @csrf
                                <input type="hidden" name="action" value="undo">
                                <button type="submit"
                                        class="border border-gray-200 text-gray-400 text-[10px] font-semibold rounded-lg px-2 py-1.5 hover:border-red-300 hover:text-red-400 transition-colors">
                                    ✕
                                </button>
                            </form>
                        @endif
                    </div>

                </div>
            @endforeach
        </div>
    @endif

</div>
@endsection
