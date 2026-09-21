@extends('layouts.app-dsa')
@section('title', 'Dashboard — DSA')

@section('live-refresh', 'any')
@section('content')
{{-- Page title bar --}}
<div class="bg-[#07873e] px-5 py-4">
    <h1 class="text-white font-bold text-xl">Dashboard</h1>
    <p class="text-white/60 text-xs mt-0.5">Director of Student Affairs</p>
</div>

<div class="px-4 py-5 space-y-5">

    {{-- Officer turnover: who has not appointed this school year's officers yet.
         Nothing has been revoked from them — this is the list to chase. --}}
    @if($turnoverPending->isNotEmpty())
        <div class="bg-[#F9A825]/10 border border-[#F9A825]/40 rounded-xl px-4 py-3.5">
            <p class="text-sm font-semibold text-gray-800">
                {{ $turnoverPending->count() }} club(s) have not appointed officers for A.Y. {{ $academicYear }}
            </p>
            <ul class="mt-2 space-y-1">
                @foreach($turnoverPending->take(8) as $pendingClub)
                    <li class="text-xs text-gray-600 flex items-center justify-between gap-3">
                        <a href="{{ route('dsa.clubs.show', $pendingClub) }}" class="truncate hover:underline">{{ $pendingClub->name }}</a>
                        <span class="text-gray-400 flex-shrink-0">{{ $pendingClub->adviserUser?->name ?? 'No adviser' }}</span>
                    </li>
                @endforeach
            </ul>
            @if($turnoverPending->count() > 8)
                <p class="text-xs text-gray-400 mt-1.5">and {{ $turnoverPending->count() - 8 }} more</p>
            @endif
            <p class="text-xs text-gray-500 mt-2">
                Their current officers keep working until the adviser replaces them.
            </p>
        </div>
    @endif

    {{-- Session error --}}
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-red-700 text-sm">
            {{ session('error') }}
        </div>
    @endif

    {{-- Create new club (top action) --}}
    <a href="{{ route('dsa.clubs.create') }}"
       class="flex items-center justify-between w-full bg-[#07873e] text-[#F9A825] rounded-xl px-4 py-3.5 font-semibold text-sm hover:opacity-90 transition-opacity shadow-sm">
        Create a new club
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15"/>
        </svg>
    </a>

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
        <a href="{{ route('dsa.activities.monitor') }}" class="bg-{{ $acleSummary['pending'] > 0 ? '[#F9A825]' : '[#07873e]' }} rounded-xl p-4 shadow-sm">
            <p class="{{ $acleSummary['pending'] > 0 ? 'text-[#07873e]' : 'text-[#F9A825]' }} text-xs font-semibold uppercase tracking-wide">Pending Approvals</p>
            <p class="{{ $acleSummary['pending'] > 0 ? 'text-[#07873e]' : 'text-white' }} font-bold text-3xl mt-1">{{ $acleSummary['pending'] }}</p>
        </a>
    </div>

    {{-- Club type overview --}}
    <div>
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-2">Club Overview</h2>
        <div class="space-y-2">
            <a href="{{ route('dsa.clubs.index', ['type' => 'academic']) }}" class="flex items-center justify-between bg-white rounded-xl p-4 shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-[#07873e]/10 flex items-center justify-center">
                        <svg class="w-5 h-5 text-[#07873e]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.26 10.147a60.438 60.438 0 0 0-.491 6.347A48.62 48.62 0 0 1 12 20.904a48.62 48.62 0 0 1 8.232-4.41 60.46 60.46 0 0 0-.491-6.347m-15.482 0a50.636 50.636 0 0 0-2.658-.813A59.906 59.906 0 0 1 12 3.493a59.903 59.903 0 0 1 10.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0 1 12 13.489a50.702 50.702 0 0 1 3.741-3.342M6.75 15a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Zm0 0v-3.675A55.378 55.378 0 0 1 12 8.443m-7.007 11.55A5.981 5.981 0 0 0 6.75 15.75v-1.5"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-800">Academic Clubs</p>
                        <p class="text-xs text-gray-400">View all academic clubs</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xl font-bold text-[#07873e]">{{ $academicClubs }}</span>
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                    </svg>
                </div>
            </a>

            <a href="{{ route('dsa.clubs.index', ['type' => 'non_academic']) }}" class="flex items-center justify-between bg-white rounded-xl p-4 shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-lg bg-[#F9A825]/10 flex items-center justify-center">
                        <svg class="w-5 h-5 text-[#F9A825]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-800">Non-Academic Clubs</p>
                        <p class="text-xs text-gray-400">View all non-academic clubs</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xl font-bold text-[#07873e]">{{ $nonAcademicClubs }}</span>
                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                    </svg>
                </div>
            </a>
        </div>
    </div>

    {{-- ACLE Activity Monitor --}}
    <div>
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-2">ACLE Monitor</h2>

        {{-- Filter bar --}}
        <form method="GET" action="{{ route('dsa.dashboard') }}" class="bg-white rounded-xl border border-gray-100 shadow-sm p-3 mb-3 space-y-2">
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
                <a href="{{ route('dsa.dashboard') }}" class="text-xs text-gray-400 font-medium hover:text-gray-600">Clear</a>
            </div>
        </form>

        {{-- Activity list --}}
        @forelse($upcomingAcleActivities as $activity)
            <a href="{{ route('dsa.activities.show', $activity) }}"
               class="flex items-center justify-between gap-3 bg-white rounded-xl px-4 py-3 border border-gray-100 shadow-sm mb-2 hover:shadow-md transition-shadow">
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
            </a>
        @empty
            <p class="text-gray-400 text-sm text-center py-4">No ACLE activities scheduled.</p>
        @endforelse
    </div>


</div>
@endsection
