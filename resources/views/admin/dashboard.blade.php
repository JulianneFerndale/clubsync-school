@extends('layouts.app-admin')
@section('title', 'Dashboard — Admin')

@section('content')
{{-- Page title bar --}}
<div class="bg-[#07873e] px-5 py-4">
    <h1 class="text-white font-bold text-xl">Dashboard</h1>
    <p class="text-white/60 text-xs mt-0.5">System Administrator</p>
</div>

<div class="px-4 py-5 space-y-5">

    {{-- Overview cards --}}
    <div class="grid grid-cols-2 gap-3">
        <div class="bg-[#07873e] rounded-xl p-4 shadow-sm">
            <p class="text-[#F9A825] text-xs font-semibold uppercase tracking-wide">Total Clubs</p>
            <p class="text-white font-bold text-3xl mt-1">{{ $totalClubs }}</p>
        </div>
        <div class="bg-[#07873e] rounded-xl p-4 shadow-sm">
            <p class="text-[#F9A825] text-xs font-semibold uppercase tracking-wide">Total Activities</p>
            <p class="text-white font-bold text-3xl mt-1">{{ $totalActivities }}</p>
        </div>
        <div class="bg-[#07873e] rounded-xl p-4 shadow-sm">
            <p class="text-[#F9A825] text-xs font-semibold uppercase tracking-wide">ACLE This Semester</p>
            <p class="text-white font-bold text-3xl mt-1">{{ $acleThisSemester }}</p>
        </div>
        <div class="bg-{{ $acleSummary['pending'] > 0 ? '[#F9A825]' : '[#07873e]' }} rounded-xl p-4 shadow-sm">
            <p class="{{ $acleSummary['pending'] > 0 ? 'text-[#07873e]' : 'text-[#F9A825]' }} text-xs font-semibold uppercase tracking-wide">Pending Approvals</p>
            <p class="{{ $acleSummary['pending'] > 0 ? 'text-[#07873e]' : 'text-white' }} font-bold text-3xl mt-1">{{ $acleSummary['pending'] }}</p>
        </div>
    </div>

    {{-- ACLE Activity Monitor --}}
    <div>
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-2">ACLE Monitor</h2>

        {{-- Filter bar --}}
        <form method="GET" action="{{ route('admin.dashboard') }}" class="bg-white rounded-xl border border-gray-100 shadow-sm p-3 mb-3 space-y-2">
            <div class="grid grid-cols-2 gap-2">
                <select name="club_id" class="border border-gray-300 rounded-lg text-xs px-2.5 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-[#07873e]/30">
                    <option value="">All Clubs</option>
                    @foreach($clubs as $club)
                        <option value="{{ $club->id }}" {{ request('club_id') == $club->id ? 'selected' : '' }}>{{ $club->name }}</option>
                    @endforeach
                </select>
                <select name="semester_id" class="border border-gray-300 rounded-lg text-xs px-2.5 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-[#07873e]/30">
                    <option value="">All Semesters</option>
                    @foreach($semesters as $semester)
                        <option value="{{ $semester->id }}" {{ request('semester_id') == $semester->id ? 'selected' : '' }}>{{ $semester->label }}</option>
                    @endforeach
                </select>
                <input type="date" name="date_from" value="{{ request('date_from') }}"
                       class="border border-gray-300 rounded-lg text-xs px-2.5 py-2 focus:outline-none focus:ring-2 focus:ring-[#07873e]/30">
                <input type="date" name="date_to" value="{{ request('date_to') }}"
                       class="border border-gray-300 rounded-lg text-xs px-2.5 py-2 focus:outline-none focus:ring-2 focus:ring-[#07873e]/30">
            </div>
            <div class="flex items-center gap-2">
                <button type="submit" class="bg-[#07873e] text-white text-xs font-semibold rounded-lg px-4 py-2 hover:opacity-90 transition-opacity">
                    Apply Filters
                </button>
                <a href="{{ route('admin.dashboard') }}" class="text-xs text-gray-400 font-medium hover:text-gray-600">Clear</a>
            </div>
        </form>

        {{-- Activity list --}}
        @forelse($upcomingAcleActivities as $activity)
            <div class="flex items-center justify-between gap-3 bg-white rounded-xl px-4 py-3 border border-gray-100 shadow-sm mb-2">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-1.5">
                        @if($activity->has_conflict)
                            <svg class="w-4 h-4 text-[#F9A825] flex-shrink-0" fill="currentColor" viewBox="0 0 24 24" title="Venue/time conflict with another ACLE activity">
                                <path fill-rule="evenodd" d="M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.748c1.154 2-.29 4.5-2.598 4.5H4.645c-2.309 0-3.752-2.5-2.598-4.5L9.4 3.003ZM12 8.25a.75.75 0 0 1 .75.75v3.75a.75.75 0 0 1-1.5 0V9a.75.75 0 0 1 .75-.75Zm0 8.25a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z" clip-rule="evenodd"/>
                            </svg>
                        @endif
                        <p class="text-sm font-semibold text-gray-800 truncate">{{ $activity->title }}</p>
                    </div>
                    <p class="text-xs text-gray-500 mt-0.5">
                        {{ $activity->club->acronym ?? $activity->club->name }} ·
                        {{ $activity->date->format('M j, Y') }} ·
                        {{ \Carbon\Carbon::parse($activity->time_start)->format('g:i A') }}–{{ \Carbon\Carbon::parse($activity->time_end)->format('g:i A') }} ·
                        {{ $activity->venue }}
                    </p>
                </div>
                <x-status-badge :status="$activity->approval_status" />
            </div>
        @empty
            <p class="text-gray-400 text-sm text-center py-4">No ACLE activities scheduled.</p>
        @endforelse
    </div>

</div>
@endsection
