@extends('layouts.app-member')
@section('title', 'Home — ClubSync')

@section('live-refresh', 'any')
@section('content')

{{-- Green header --}}
<div class="bg-[#07873e] px-5 pt-12 pb-6">
    <div class="flex items-center justify-between mb-1">
        <div>
            <p class="text-white/60 text-xs font-medium">Welcome back</p>
            <h1 class="text-white font-bold text-xl">SCC ClubSync</h1>
            <p class="text-white/50 text-[11px]">Saint Columban College</p>
        </div>
        <img src="/images/scc-crest.png" alt="SCC"
             class="h-10 w-10 object-contain opacity-90"
             onerror="this.style.display='none'">
    </div>
</div>

<div class="px-4 py-5 space-y-6">

    {{-- CTA buttons --}}
    <div class="grid grid-cols-2 gap-3">
        <a href="{{ route('member.bulletin.index') }}"
           class="flex items-center justify-center bg-[#07873e] text-[#F9A825] font-semibold text-sm rounded-xl py-3.5 hover:opacity-90 transition-opacity shadow-sm">
            See Announcement
        </a>
        <a href="{{ route('member.clubs.index') }}"
           class="flex items-center justify-center bg-[#07873e] text-[#F9A825] font-semibold text-sm rounded-xl py-3.5 hover:opacity-90 transition-opacity shadow-sm">
            Browse Non-Acad
        </a>
    </div>

    {{-- Clubs you manage (officers) — entry point to the in-club admin tools --}}
    @if(isset($managedClubs) && $managedClubs->isNotEmpty())
        <div>
            <div class="flex items-center gap-2 mb-3">
                <svg class="w-4 h-4 text-[#07873e]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085"/>
                </svg>
                <h2 class="text-base font-bold text-gray-800">Clubs You Manage</h2>
            </div>
            <div class="space-y-2.5">
                @foreach($managedClubs as $club)
                    <a href="{{ route('member.clubs.show', $club) }}"
                       class="flex items-center gap-3 bg-white rounded-xl p-3 border border-[#07873e]/20 shadow-sm hover:shadow-md transition-shadow">
                        @if($club->profile_photo_url)
                            <img src="{{ $club->profile_photo_url }}" alt="" class="w-11 h-11 rounded-full object-cover flex-shrink-0">
                        @else
                            <div class="w-11 h-11 rounded-full bg-[#07873e] flex items-center justify-center flex-shrink-0">
                                <span class="text-white font-bold text-xs">{{ strtoupper(substr($club->acronym ?? $club->name, 0, 2)) }}</span>
                            </div>
                        @endif
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-800 truncate">{{ $club->name }}</p>
                            <p class="text-[11px] text-[#07873e] font-semibold">Tap to manage</p>
                        </div>
                        <span class="text-[10px] font-bold text-[#07873e] bg-[#07873e]/10 rounded-full px-2 py-0.5 flex-shrink-0">OFFICER</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    {{-- My academic clubs --}}
    <div>
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-base font-bold text-gray-800">My Academic Clubs</h2>
        </div>

        @if($academicClubs->isEmpty())
            <p class="text-sm text-gray-400 text-center py-4">You have not joined any academic clubs yet.</p>
        @else
            <div class="grid grid-cols-3 gap-2.5">
                @foreach($academicClubs as $club)
                    <x-club-card :club="$club" :href="route('member.clubs.show', $club)" :pending="$club->my_status === 'pending'" />
                @endforeach
            </div>
        @endif
    </div>

    {{-- My non-academic clubs --}}
    <div>
        <div class="flex items-center justify-between mb-3">
            <h2 class="text-base font-bold text-gray-800">My Non-Academic Clubs</h2>
        </div>

        @if($nonAcademicClubs->isEmpty())
            <p class="text-sm text-gray-400 text-center py-4">You have not joined any non-academic clubs yet.</p>
        @else
            <div class="grid grid-cols-3 gap-2.5">
                @foreach($nonAcademicClubs as $club)
                    <x-club-card :club="$club" :href="route('member.clubs.show', $club)" :pending="$club->my_status === 'pending'" />
                @endforeach
            </div>
        @endif
    </div>

</div>
@endsection
