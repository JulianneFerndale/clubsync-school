@extends('layouts.app-adviser')
@section('title', 'Dashboard — Adviser')
@section('page-title', $club?->name ?? 'Adviser Panel')

@section('live-refresh', 'any')
@section('content')
{{-- Club header --}}
<div class="bg-[#07873e] px-5 py-4">
    <h1 class="text-white font-bold text-xl">{{ $club?->name ?? 'No Club Assigned' }}</h1>
    <p class="text-white/60 text-xs mt-0.5">Adviser Dashboard</p>
    <x-facebook-button :url="$club?->facebook_url" :club="$club?->name" class="mt-3" />
</div>

<div class="px-4 py-5 space-y-5">

    @if(! $club)
        <div class="bg-yellow-50 border border-yellow-200 rounded-xl px-4 py-4 text-yellow-800 text-sm">
            @if($requestedClub)
                You asked to advise <span class="font-semibold">{{ $requestedClub->name }}</span>. An administrator will confirm it, and your club's pages open once they do.
            @else
                You have not been assigned to a club yet. Please contact the DSA office.
            @endif
        </div>
    @else

    {{-- Quick stats --}}
    <div class="grid grid-cols-2 gap-3">
        <div class="bg-[#07873e] rounded-xl p-4 shadow-sm">
            <p class="text-[#F9A825] text-xs font-semibold uppercase tracking-wide">Members</p>
            <p class="text-white font-bold text-3xl mt-1">{{ $memberCount }}</p>
        </div>
        <div class="bg-[#F9A825] rounded-xl p-4 shadow-sm">
            <p class="text-[#07873e] text-xs font-semibold uppercase tracking-wide">Pending Review</p>
            <p class="text-[#07873e] font-bold text-3xl mt-1">{{ $pendingCount }}</p>
        </div>
    </div>

    {{-- Officers turn over with the school year; this club has not yet. Nothing has
         been taken from the sitting officers — they work on until replaced. --}}
    @if($turnoverPending)
    <a href="{{ route('clubs.officers.index') }}"
       class="block bg-[#F9A825]/10 border border-[#F9A825]/40 rounded-xl px-4 py-3.5 hover:bg-[#F9A825]/20 transition-colors">
        <p class="text-sm font-semibold text-gray-800">New officers needed for A.Y. {{ $academicYear }}</p>
        <p class="text-xs text-gray-600 mt-1">
            No officer has been appointed since the school year began. Tap to record this year's officers.
        </p>
    </a>
    @endif

    {{-- Approval queue shortcut --}}
    @if($pendingCount > 0)
    <a href="{{ route('adviser.activities.review') }}"
       class="flex items-center justify-between bg-[#F9A825]/10 border border-[#F9A825]/30 rounded-xl px-4 py-3.5 hover:bg-[#F9A825]/20 transition-colors">
        <div class="flex items-center gap-3">
            <svg class="w-5 h-5 text-[#F9A825]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.094 1.976 1.057 1.976 2.192V16.5A2.25 2.25 0 0 1 18 18.75h-2.25m-7.5-10.5H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V18.75m-7.5-10.5h6.375c.621 0 1.125.504 1.125 1.125v9.375m-8.25-3 1.5 1.5 3-3.75"/>
            </svg>
            <span class="text-sm font-semibold text-gray-800">Activity Review</span>
        </div>
        <div class="flex items-center gap-2">
            <span class="bg-[#F9A825] text-white text-xs font-bold px-2 py-0.5 rounded-full">{{ $pendingCount }}</span>
            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
            </svg>
        </div>
    </a>
    @endif

    {{-- Upcoming activities --}}
    <div>
        <div class="flex items-center justify-between mb-2">
            <h2 class="text-sm font-semibold text-gray-700">Upcoming Activities</h2>
            <a href="#" class="text-xs text-[#07873e] font-medium">See all</a>
        </div>
        @forelse($upcomingEvents as $event)
            <x-activity-row :event="$event" />
            @if(! $loop->last)<div class="border-b border-gray-100"></div>@endif
        @empty
            <p class="text-sm text-gray-400 text-center py-6">No upcoming activities scheduled.</p>
        @endforelse
    </div>

    {{-- Recent announcements --}}
    <div>
        <div class="flex items-center justify-between mb-2">
            <h2 class="text-sm font-semibold text-gray-700">Recent Announcements</h2>
            <a href="#" class="text-xs text-[#07873e] font-medium">See all</a>
        </div>
        @forelse($recentAnnouncements as $ann)
            <a href="#" class="block bg-white rounded-xl p-3.5 border border-gray-100 shadow-sm mb-2 hover:shadow-md transition-shadow">
                <div class="flex items-start justify-between gap-2">
                    <p class="text-sm font-semibold text-gray-800 truncate flex-1">{{ $ann->title ?? 'Untitled' }}</p>
                    <x-status-badge :status="$ann->status" />
                </div>
                <p class="text-xs text-gray-500 mt-1 line-clamp-2">{{ $ann->content }}</p>
                <p class="text-xs text-gray-400 mt-1.5">{{ $ann->created_at->diffForHumans() }}</p>
            </a>
        @empty
            <p class="text-sm text-gray-400 text-center py-4">No announcements yet.</p>
        @endforelse
    </div>

    @endif
</div>
@endsection
