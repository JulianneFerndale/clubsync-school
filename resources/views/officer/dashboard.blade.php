@extends('layouts.app-officer')
@section('title', 'Home — Officer')
@section('club-name', $club?->name ?? 'ClubSync')

@section('live-refresh', 'any')
@section('content')

{{-- Header --}}
<div class="bg-[#07873e] px-5 pt-5 pb-6">
    <div class="flex items-center justify-between">
        <div>
            <p class="text-white/60 text-xs font-medium">Welcome back</p>
            <h1 class="text-white font-bold text-xl">SCC ClubSync</h1>
            <p class="text-white/50 text-[11px] capitalize">
                {{ $position ? $position . ' · ' : '' }}{{ $club?->name ?? 'Saint Columban College' }}
            </p>
        </div>
        <img src="/images/scc-crest.png" alt="SCC"
             class="h-10 w-10 object-contain opacity-90" onerror="this.style.display='none'">
    </div>
</div>

<div class="px-4 py-5 space-y-6">

    {{-- Enroll in a non-academic club (officers may join clubs open to all students) --}}
    <a href="{{ route('officer.clubs.index') }}"
       class="flex items-center justify-center gap-2 bg-[#07873e] text-[#F9A825] font-semibold text-sm rounded-xl py-3.5 hover:opacity-90 transition-opacity shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.5v15m7.5-7.5h-15"/>
        </svg>
        Enroll in a Non-Academic Club
    </a>

    {{-- My academic clubs --}}
    <div>
        <h2 class="text-base font-bold text-gray-800 mb-3">My Academic Clubs</h2>
        @if($academicClubs->isEmpty())
            <p class="text-sm text-gray-400 text-center py-4">You have not joined any academic clubs yet.</p>
        @else
            <div class="grid grid-cols-3 gap-2.5">
                @foreach($academicClubs as $academicClub)
                    <x-club-card :club="$academicClub" :href="route('officer.clubs.show', $academicClub)" />
                @endforeach
            </div>
        @endif
    </div>

    {{-- My non-academic clubs --}}
    <div>
        <h2 class="text-base font-bold text-gray-800 mb-3">My Non-Academic Clubs</h2>
        @if($nonAcademicClubs->isEmpty())
            <x-empty-clubs :href="route('officer.clubs.index')" />
        @else
            <div class="grid grid-cols-3 gap-2.5">
                @foreach($nonAcademicClubs as $nonAcademicClub)
                    <x-club-card :club="$nonAcademicClub" :href="route('officer.clubs.show', $nonAcademicClub)" />
                @endforeach
            </div>
        @endif
    </div>

</div>
@endsection
