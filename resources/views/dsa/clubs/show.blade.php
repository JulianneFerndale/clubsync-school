@extends('layouts.app-dsa')
@section('title', $club->name . ' — DSA')
@section('page-title', 'Club Profile')

@php
    $initials = fn ($user) => mb_strtoupper(
        mb_substr($user->first_name ?? '', 0, 1) . mb_substr($user->last_name ?? '', 0, 1)
    ) ?: 'U';
    $clubType = $club->type ?? ($club->club_type === 'Academic' ? 'academic' : 'non_academic');
    $adviser  = $club->adviserUser;
@endphp

@section('content')

{{-- Club header --}}
<header class="bg-[#07873e] text-white shadow-md">
    <div class="max-w-6xl mx-auto px-5 md:px-8 pt-5 pb-8">
        <div class="flex items-center justify-between mb-6">
            <a href="{{ route('dsa.clubs.index') }}" aria-label="Back to clubs"
               class="inline-flex items-center justify-center p-2 -ml-2 rounded-full text-white/90 hover:text-white hover:bg-white/10 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
                </svg>
            </a>
            <a href="{{ route('dsa.clubs.edit', $club) }}"
               class="inline-flex items-center gap-2 px-4 py-1.5 rounded-lg border border-white/40 hover:border-white text-sm font-medium text-white hover:bg-white/10 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10"/>
                </svg>
                Edit
            </a>
        </div>

        <div class="flex items-center gap-5">
            <div class="w-20 h-20 flex-shrink-0 rounded-full bg-white p-1 shadow-lg border-2 border-white/80 overflow-hidden flex items-center justify-center">
                @if($club->profile_photo_url)
                    <img src="{{ $club->profile_photo_url }}" alt="{{ $club->name }} logo"
                         class="w-full h-full rounded-full object-cover">
                @else
                    <span class="text-[#07873e] font-extrabold text-2xl">{{ strtoupper(mb_substr($club->acronym ?? $club->name, 0, 2)) }}</span>
                @endif
            </div>
            <div class="min-w-0">
                <h1 class="text-2xl sm:text-3xl font-bold tracking-tight leading-tight">{{ $club->name }}</h1>
                <p class="text-sm font-medium text-emerald-100 mt-0.5">
                    {{ $club->acronym }}@if($club->college)<span class="text-emerald-100/60"> · </span>{{ $club->college->name }}@endif
                </p>
                <div class="flex flex-wrap items-center gap-2 mt-2">
                    <x-status-badge :status="$clubType" />
                    @if(! $club->is_active)
                        <x-status-badge status="inactive" />
                    @endif
                </div>
                <x-facebook-button :url="$club->facebook_url" :club="$club->name" class="mt-3" />

            </div>
        </div>
    </div>
</header>

<div class="max-w-6xl mx-auto px-4 md:px-8 py-6 space-y-6">

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 rounded-xl px-4 py-3 text-green-700 text-sm">
            {{ session('success') }}
        </div>
    @endif

    {{-- Stats --}}
    <section aria-label="Club statistics" class="grid grid-cols-3 gap-2.5 md:gap-5">
        <a href="{{ route('dsa.clubs.members.index', $club) }}"
           class="bg-[#07873e] text-white rounded-xl p-3 md:p-6 shadow-sm border border-emerald-700 flex items-center justify-between transition-transform hover:-translate-y-0.5">
            <div>
                <p class="text-2xl md:text-4xl font-extrabold leading-none">{{ $stats['members'] }}</p>
                <p class="mt-2 text-[10px] md:text-xs font-bold tracking-wider uppercase text-emerald-200">Members</p>
            </div>
            <div class="hidden sm:flex w-12 h-12 rounded-xl bg-white/10 items-center justify-center flex-shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/>
                </svg>
            </div>
        </a>

        <div class="bg-[#07873e] text-white rounded-xl p-3 md:p-6 shadow-sm border border-emerald-700 flex items-center justify-between">
            <div>
                <p class="text-2xl md:text-4xl font-extrabold leading-none">{{ $stats['activities'] }}</p>
                <p class="mt-2 text-[10px] md:text-xs font-bold tracking-wider uppercase text-emerald-200">Activities</p>
            </div>
            <div class="hidden sm:flex w-12 h-12 rounded-xl bg-white/10 items-center justify-center flex-shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/>
                </svg>
            </div>
        </div>

        {{-- Amber only when something is waiting, so the card reads as a call to action --}}
        <a href="{{ route('dsa.clubs.members.index', $club) }}"
           class="{{ $stats['pending'] > 0 ? 'bg-[#F9A825] border-amber-500 text-gray-900' : 'bg-[#07873e] border-emerald-700 text-white' }} rounded-xl p-3 md:p-6 shadow-sm border flex items-center justify-between transition-transform hover:-translate-y-0.5">
            <div>
                <p class="text-2xl md:text-4xl font-extrabold leading-none">{{ $stats['pending'] }}</p>
                <p class="mt-2 text-[10px] md:text-xs font-bold tracking-wider uppercase {{ $stats['pending'] > 0 ? 'text-gray-900/70' : 'text-emerald-200' }}">Pending</p>
            </div>
            <div class="hidden sm:flex w-12 h-12 rounded-xl {{ $stats['pending'] > 0 ? 'bg-white/30' : 'bg-white/10' }} items-center justify-center flex-shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
            </div>
        </a>
    </section>

    {{-- Adviser + About --}}
    <section class="grid grid-cols-1 md:grid-cols-2 gap-5">
        <div class="bg-white rounded-xl p-5 sm:p-6 border border-gray-200 shadow-sm">
            <div class="flex items-center gap-2 mb-4">
                <svg class="w-4 h-4 text-[#07873e]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/>
                </svg>
                <h2 class="text-xs font-bold uppercase tracking-wider text-gray-500">Faculty Adviser</h2>
            </div>
            @if($adviser)
                <div class="flex items-center gap-3.5">
                    <div class="w-11 h-11 rounded-full bg-emerald-50 text-emerald-800 font-bold text-sm border border-emerald-200 flex items-center justify-center flex-shrink-0">
                        {{ $initials($adviser) }}
                    </div>
                    <div class="min-w-0">
                        <p class="text-base font-bold text-gray-900 leading-snug truncate">{{ $adviser->name }}</p>
                        <p class="text-xs text-gray-500 mt-0.5 truncate">{{ $adviser->email }}</p>
                    </div>
                    @if($adviser->is_suspended)
                        <span class="ml-auto"><x-status-badge status="suspended" /></span>
                    @endif
                </div>
            @else
                <div class="flex items-center justify-between gap-3">
                    <p class="text-sm text-gray-500">No adviser assigned yet.</p>
                    <a href="{{ route('dsa.clubs.edit', $club) }}" class="text-sm font-semibold text-[#07873e] hover:underline">Assign</a>
                </div>
            @endif
        </div>

        <div class="bg-white rounded-xl p-5 sm:p-6 border border-gray-200 shadow-sm">
            <div class="flex items-center gap-2 mb-3">
                <svg class="w-4 h-4 text-[#07873e]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"/>
                </svg>
                <h2 class="text-xs font-bold uppercase tracking-wider text-gray-500">About the Club</h2>
            </div>
            @if($club->description)
                <p class="text-sm text-gray-700 leading-relaxed">{{ $club->description }}</p>
            @else
                <p class="text-sm text-gray-400">No description provided.</p>
            @endif
        </div>
    </section>

    {{-- Officers --}}
    <section>
        <div class="flex items-center justify-between mb-3 px-1">
            <h2 class="text-base font-bold text-gray-800 tracking-tight">Officers</h2>
            <a href="{{ route('dsa.clubs.officers.index', $club) }}" class="text-sm font-semibold text-[#07873e] hover:text-[#056d32] hover:underline">Officer Records</a>
        </div>
        <div class="space-y-2">
            @forelse($club->officers as $officer)
                <div class="bg-white rounded-xl p-4 sm:p-5 border border-gray-200 shadow-sm flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3.5 min-w-0">
                        <div class="w-10 h-10 rounded-full bg-emerald-100/70 text-emerald-800 font-semibold text-sm border border-emerald-200/60 flex items-center justify-center flex-shrink-0">
                            {{ $initials($officer->user) }}
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-900 leading-tight truncate">{{ $officer->user->name ?? 'Unknown' }}</p>
                            <p class="text-xs text-gray-500 mt-0.5 truncate">{{ $officer->user->email ?? '' }}</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center px-3.5 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200/70 capitalize flex-shrink-0">
                        {{ $officer->position }}
                    </span>
                </div>
            @empty
                <p class="bg-white rounded-xl border border-gray-200 text-sm text-gray-400 text-center py-6">No officers assigned yet.</p>
            @endforelse
        </div>
    </section>

    {{-- Active members --}}
    <section>
        <div class="flex items-center justify-between mb-3 px-1">
            <h2 class="text-base font-bold text-gray-800 tracking-tight">Active Members</h2>
            <span class="text-xs font-medium text-gray-500">{{ $stats['members'] }} total</span>
        </div>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm divide-y divide-gray-100">
            @forelse($activeMembers as $member)
                <div class="flex items-center gap-3.5 px-4 py-3">
                    <div class="w-10 h-10 rounded-full bg-gray-100 text-gray-600 font-semibold text-sm border border-gray-200 flex items-center justify-center flex-shrink-0">
                        {{ $initials($member->user) }}
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-gray-900 leading-tight truncate">{{ $member->user->name ?? 'Unknown' }}</p>
                        <p class="text-xs text-gray-500 mt-0.5 truncate">{{ $member->user->email ?? '' }}</p>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-400 text-center py-6">No active members yet.</p>
            @endforelse
        </div>

        @if($activeMembers->hasPages())
            <div class="pt-3">{{ $activeMembers->links() }}</div>
        @endif
    </section>

    {{-- Recent activities --}}
    @if($recentEvents->count())
    <section>
        <h2 class="text-base font-bold text-gray-800 tracking-tight mb-3 px-1">Recent Activities</h2>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm divide-y divide-gray-100">
            @foreach($recentEvents as $event)
                <x-activity-row :event="$event" />
            @endforeach
        </div>
    </section>
    @endif

</div>
@endsection
