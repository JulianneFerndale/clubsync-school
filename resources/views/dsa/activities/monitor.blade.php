@extends('layouts.app-dsa')
@section('title', 'Activity Monitor — DSA')

@section('content')

{{-- Page title bar --}}
<div class="bg-[#07873e] px-5 py-4">
    <h1 class="text-white font-bold text-xl">Activity Monitor</h1>
    <p class="text-white/60 text-xs mt-0.5">All club activities across the system</p>
</div>

<div class="px-4 py-5 space-y-5">

    {{-- Filters --}}
    <form method="GET" action="{{ route('dsa.activities.monitor') }}" class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 space-y-3">
        <div class="grid grid-cols-2 gap-3">
            <select name="club_id" class="border border-gray-300 rounded-lg text-xs px-2.5 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-[#07873e]/30">
                <option value="">All Clubs</option>
                @foreach($clubs as $club)
                    <option value="{{ $club->id }}" {{ request('club_id') == $club->id ? 'selected' : '' }}>{{ $club->name }}</option>
                @endforeach
            </select>

            <select name="activity_type" class="border border-gray-300 rounded-lg text-xs px-2.5 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-[#07873e]/30">
                <option value="">All Types</option>
                @foreach(['internal_meeting' => 'Internal Meeting', 'acle' => 'ACLE', 'community_involvement' => 'Community Involvement', 'campus_resource_use' => 'Campus Resource Use', 'other_external' => 'Other External'] as $value => $label)
                    <option value="{{ $value }}" {{ request('activity_type') === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>

            <select name="approval_status" class="border border-gray-300 rounded-lg text-xs px-2.5 py-2 bg-white focus:outline-none focus:ring-2 focus:ring-[#07873e]/30">
                <option value="">All Statuses</option>
                @foreach(\App\Models\ClubActivity::APPROVAL_LABELS as $value => $label)
                    <option value="{{ $value }}" {{ request('approval_status') === $value ? 'selected' : '' }}>{{ $label }}</option>
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
            <a href="{{ route('dsa.activities.monitor') }}" class="text-xs text-gray-400 font-medium hover:text-gray-600">Clear</a>
        </div>
    </form>

    {{-- Results --}}
    @if($activities->isEmpty())
        <p class="text-sm text-gray-400 text-center py-8">No activities match the selected filters.</p>
    @else
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden divide-y divide-gray-50">
            @foreach($activities as $event)
                <a href="{{ route('dsa.activities.show', $event) }}" class="flex items-center justify-between gap-3 px-4 py-3.5 hover:bg-gray-50 transition-colors">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-800 truncate">{{ $event->title }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">
                            {{ $event->club->acronym ?? $event->club->name }} · {{ $event->date->format('M j, Y') }} · {{ $event->venue }}
                        </p>
                        <div class="flex items-center gap-1.5 mt-1.5">
                            <x-status-badge :status="$event->activity_type" />
                        </div>
                    </div>
                    <x-status-badge :status="$event->approval_status" />
                    <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                    </svg>
                </a>
            @endforeach
        </div>

        <div>{{ $activities->links() }}</div>
    @endif

</div>
@endsection
