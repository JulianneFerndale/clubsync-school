@extends('layouts.app-officer')
@section('title', 'Churn Risk Engine')
@section('page-title', 'Churn Risk Engine')
@section('club-name', 'Churn Risk Engine')

@section('content')

<div class="bg-[#07873e] px-5 pt-5 pb-6">
    <h1 class="text-white font-bold text-xl">Churn Risk Engine</h1>
    <p class="text-white/60 text-xs mt-1">
        Member engagement & disengagement risk{{ $semester ? ' · ' . $semester->label : '' }}
    </p>
</div>

<div class="px-4 py-5 space-y-5">

    {{-- Confidentiality reminder (this data is officer-only) --}}
    <div class="flex items-start gap-2 px-3 py-2 bg-amber-50 rounded-xl border border-amber-100">
        <span class="text-amber-600 text-sm">🔒</span>
        <span class="text-xs text-amber-700">Confidential — engagement scores are for club officers only and must not be shared with members, advisers or the DSA.</span>
    </div>

    @if($clubs->isEmpty())
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-8 text-center">
            <p class="text-sm text-gray-400">No clubs available to assess.</p>
        </div>
    @else

        {{-- Club selector --}}
        <form method="GET" action="{{ route('churn-risk') }}">
            <label class="block text-xs font-semibold text-gray-400 uppercase tracking-widest mb-1.5 px-1">Club</label>
            <select name="club_id" onchange="this.form.submit()"
                    class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#07873e]/30">
                @foreach($clubs as $club)
                    <option value="{{ $club->id }}" {{ $selectedClub && $selectedClub->id === $club->id ? 'selected' : '' }}>
                        {{ $club->name }}
                    </option>
                @endforeach
            </select>
        </form>

        {{-- Summary cards --}}
        <div class="grid grid-cols-3 gap-3">
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-3 py-4 text-center">
                <p class="text-2xl font-bold text-red-600">{{ $summary['high'] }}</p>
                <p class="text-[11px] text-gray-400 font-medium mt-0.5">High risk</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-3 py-4 text-center">
                <p class="text-2xl font-bold text-[#F9A825]">{{ $summary['medium'] }}</p>
                <p class="text-[11px] text-gray-400 font-medium mt-0.5">Medium</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-3 py-4 text-center">
                <p class="text-2xl font-bold text-green-600">{{ $summary['low'] }}</p>
                <p class="text-[11px] text-gray-400 font-medium mt-0.5">Low</p>
            </div>
        </div>

        {{-- Member risk list --}}
        @if($rows->isEmpty())
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-8 text-center">
                <p class="text-sm text-gray-400">No active members in this club yet.</p>
            </div>
        @else
            <div class="space-y-2.5">
                <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest px-1">
                    {{ $summary['total'] }} active {{ $summary['total'] === 1 ? 'member' : 'members' }} · most at risk first
                </p>

                @foreach($rows as $row)
                    @php
                        $badge = match($row['tag']) {
                            'high'   => ['bg-red-50 text-red-600 border-red-200', 'High risk'],
                            'medium' => ['bg-amber-50 text-amber-600 border-amber-200', 'Medium'],
                            default  => ['bg-green-50 text-green-700 border-green-200', 'Low'],
                        };
                        $bar = match($row['tag']) {
                            'high' => 'bg-red-500', 'medium' => 'bg-[#F9A825]', default => 'bg-green-500',
                        };
                    @endphp
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3.5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-gray-800 truncate">
                                    {{ $row['user']?->first_name }} {{ $row['user']?->last_name }}
                                </p>
                                <p class="text-[11px] text-gray-400 truncate">
                                    {{ $row['user']?->edp_number ?? $row['user']?->email }}
                                </p>
                            </div>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold border {{ $badge[0] }}">
                                {{ $badge[1] }}
                            </span>
                        </div>

                        {{-- Score bar --}}
                        <div class="flex items-center gap-2 mt-2.5">
                            <div class="flex-1 h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                <div class="h-full {{ $bar }} rounded-full" style="width: {{ $row['score'] }}%"></div>
                            </div>
                            <span class="text-[11px] font-bold text-gray-500 tabular-nums">{{ $row['score'] }}</span>
                        </div>

                        {{-- Factors --}}
                        <div class="flex flex-wrap gap-1.5 mt-2">
                            @foreach($row['factors'] as $factor)
                                <span class="inline-block px-2 py-0.5 rounded-md bg-gray-50 text-gray-500 text-[10px]">{{ $factor }}</span>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif

    @endif
</div>
@endsection
