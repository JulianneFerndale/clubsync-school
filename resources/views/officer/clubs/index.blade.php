@extends('layouts.app-officer')
@section('title', 'Enroll in a Club — Officer')
@section('club-name', 'Browse Clubs')

@section('content')

<div class="bg-[#07873e] px-5 pt-5 pb-6">
    <a href="{{ route('officer.dashboard') }}" class="text-white/70 hover:text-white mb-3 inline-block">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
        </svg>
    </a>
    <h1 class="text-white font-bold text-xl">Non-Academic Clubs</h1>
    <p class="text-white/60 text-xs mt-1">Open to all students — request to enroll</p>
</div>

<div class="px-4 py-5 space-y-3">

    @error('club')
        <div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-red-600 text-sm">{{ $message }}</div>
    @enderror

    @forelse($clubs as $club)
        @php $status = $joinedIds[$club->id] ?? null; @endphp
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm flex items-center gap-3 px-4 py-3">
            <a href="{{ route('officer.clubs.show', $club) }}" class="flex items-center gap-3 flex-1 min-w-0">
                @if($club->profile_photo_url)
                    <img src="{{ $club->profile_photo_url }}" alt="{{ $club->name }}" class="w-11 h-11 rounded-full object-cover flex-shrink-0">
                @else
                    <div class="w-11 h-11 rounded-full bg-[#07873e] flex items-center justify-center flex-shrink-0">
                        <span class="text-white font-bold text-xs">{{ strtoupper(substr($club->acronym ?? $club->name, 0, 2)) }}</span>
                    </div>
                @endif
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-gray-800 truncate">{{ $club->name }}</p>
                    <p class="text-[11px] text-gray-400 truncate">{{ $club->acronym }}</p>
                </div>
            </a>

            @if($status === 'active')
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-green-50 text-green-700 border border-green-200 flex-shrink-0">Enrolled</span>
            @elseif($status === 'pending')
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-600 border border-amber-200 flex-shrink-0">Pending</span>
            @else
                <form method="POST" action="{{ route('officer.clubs.join', $club) }}"
                      data-loading="dialog" data-loading-message="Submitting membership request" class="flex-shrink-0">
                    @csrf
                    <button type="submit" class="bg-[#07873e] text-[#F9A825] font-semibold text-xs rounded-lg px-4 py-2 hover:opacity-90 transition-opacity">
                        Enroll
                    </button>
                </form>
            @endif
        </div>
    @empty
        <p class="text-sm text-gray-400 text-center py-10">No non-academic clubs available.</p>
    @endforelse

    @if($clubs->hasPages())
        <div class="pt-2">{{ $clubs->links() }}</div>
    @endif

</div>
@endsection
