@extends('layouts.app-vpa')
@section('title', 'Dashboard — VP for Administration')
@section('page-title', 'VPA Dashboard')

@section('live-refresh', 'any')
@section('content')

<div class="bg-[#07873e] px-5 py-4">
    <h1 class="text-white font-bold text-xl">Vice President for Administration</h1>
    <p class="text-white/60 text-xs mt-0.5">Room booking letters from the clubs, before they go to the club advisers</p>
</div>

<div class="px-4 py-5 space-y-5 max-w-4xl">

    @unless($hasSignature)
        <a href="{{ route('profile') }}#signature"
           class="block bg-[#F9A825]/10 border border-[#F9A825]/40 rounded-xl px-4 py-3.5 hover:bg-[#F9A825]/20 transition-colors">
            <p class="text-sm font-semibold text-gray-800">Add your signature (optional)</p>
            <p class="text-xs text-gray-600 mt-1">
                Letters you approve are signed with it under "Approved by". Without one they say they were signed electronically. Tap to add it.
            </p>
        </a>
    @endunless

    {{-- Quick stats --}}
    <div class="grid grid-cols-3 gap-3">
        <a href="{{ route('vpa.letters.index') }}" class="bg-[#F9A825] rounded-xl p-4 shadow-sm hover:opacity-90">
            <p class="text-[#07873e] text-[11px] font-semibold uppercase tracking-wide">To approve</p>
            <p class="text-[#07873e] font-bold text-3xl mt-1">{{ $pendingCount }}</p>
        </a>
        <a href="{{ route('vpa.letters.index', ['status' => 'approved']) }}" class="bg-[#07873e] rounded-xl p-4 shadow-sm hover:opacity-90">
            <p class="text-[#F9A825] text-[11px] font-semibold uppercase tracking-wide">Approved this month</p>
            <p class="text-white font-bold text-3xl mt-1">{{ $approvedThisMonth }}</p>
        </a>
        <a href="{{ route('vpa.letters.index', ['status' => 'returned']) }}" class="bg-white border border-gray-100 rounded-xl p-4 shadow-sm hover:bg-gray-50">
            <p class="text-gray-500 text-[11px] font-semibold uppercase tracking-wide">Returned this month</p>
            <p class="text-gray-800 font-bold text-3xl mt-1">{{ $returnedThisMonth }}</p>
        </a>
    </div>

    {{-- Waiting --}}
    <div>
        <div class="flex items-center justify-between mb-2">
            <h2 class="text-sm font-semibold text-gray-700">Waiting for your approval</h2>
            @if($pendingCount > $pending->count())
                <a href="{{ route('vpa.letters.index') }}" class="text-xs text-[#07873e] font-medium">See all {{ $pendingCount }}</a>
            @endif
        </div>

        @if($pending->isEmpty())
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-6 text-center">
                <p class="text-sm text-gray-400">No letters are waiting. New ones appear here as clubs send them.</p>
            </div>
        @else
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden divide-y divide-gray-50">
                @foreach($pending as $event)
                    @include('vpa.letters._row', ['event' => $event])
                @endforeach
            </div>
        @endif
    </div>

    {{-- Rooms booked ahead --}}
    <div>
        <h2 class="text-sm font-semibold text-gray-700 mb-2">Rooms you approved, coming up</h2>
        @if($booked->isEmpty())
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-5 text-center">
                <p class="text-sm text-gray-400">No approved bookings ahead.</p>
            </div>
        @else
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden divide-y divide-gray-50">
                @foreach($booked as $event)
                    <a href="{{ route('vpa.letters.show', $event) }}" class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50 transition-colors">
                        <div class="w-11 text-center flex-shrink-0">
                            <p class="text-[10px] font-semibold text-gray-400 uppercase">{{ $event->date->format('M') }}</p>
                            <p class="text-lg font-bold text-gray-800 leading-none">{{ $event->date->format('j') }}</p>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-800 truncate">{{ $event->venue }} · {{ $event->title }}</p>
                            <p class="text-xs text-gray-400 mt-0.5 truncate">
                                {{ \Carbon\Carbon::parse($event->time_start)->format('g:i A') }}–{{ \Carbon\Carbon::parse($event->time_end)->format('g:i A') }}
                                · {{ $event->club?->name }}
                            </p>
                        </div>
                        <x-status-badge :status="$event->approval_status" />
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</div>
@endsection
