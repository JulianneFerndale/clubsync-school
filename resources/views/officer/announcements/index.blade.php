@extends('layouts.app-officer')
@section('title', 'Announcements')
@section('club-name', $club?->name ?? 'ClubSync')

@section('content')

<div class="bg-[#07873e] px-5 pt-5 pb-6">
    <h1 class="text-white font-bold text-xl">Announcements</h1>
    <p class="text-white/60 text-xs mt-1">{{ $club?->name ?? 'No club assigned' }}</p>
</div>

<div class="px-4 py-5 space-y-4">

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 rounded-xl px-4 py-3 text-green-700 text-sm">
            {{ session('success') }}
        </div>
    @endif

    @if(! $club)
        <p class="text-sm text-gray-400 text-center py-8">No club assigned.</p>
    @elseif($announcements->isEmpty())
        <div class="text-center py-14">
            <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
                <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 0 1-1.44-4.282m3.102.069a18.03 18.03 0 0 1-.59-4.59c0-1.586.205-3.124.59-4.59"/>
                </svg>
            </div>
            <p class="text-sm text-gray-400 font-medium">No announcements yet</p>
            <p class="text-xs text-gray-300 mt-1">Tap + to draft one</p>
        </div>
    @else
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden divide-y divide-gray-50">
            @foreach($announcements as $ann)
                <a href="{{ route('officer.announcements.show', $ann) }}"
                   class="flex items-start gap-3 px-4 py-3.5 hover:bg-gray-50 transition-colors">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-800 truncate">
                            {{ $ann->title ?? 'Untitled' }}
                        </p>
                        <p class="text-xs text-gray-400 mt-0.5 line-clamp-1">{{ $ann->content }}</p>
                        <div class="flex items-center gap-2 mt-1.5">
                            <x-status-badge :status="$ann->status" />
                            <x-status-badge :status="$ann->type" />
                            @if($ann->ai_assisted)
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-purple-50 text-purple-600 text-[10px] font-semibold">
                                    ✦ AI
                                </span>
                            @endif
                        </div>
                    </div>
                    <div class="flex-shrink-0 text-right">
                        <p class="text-[10px] text-gray-400">{{ $ann->created_at->diffForHumans() }}</p>
                        <svg class="w-4 h-4 text-gray-400 mt-2 ml-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                        </svg>
                    </div>
                </a>
            @endforeach
        </div>
    @endif

</div>

@if($club)
<a href="{{ route('officer.announcements.create') }}"
   class="fixed bottom-20 right-4 w-14 h-14 bg-[#07873e] rounded-full shadow-lg flex items-center justify-center hover:opacity-90 transition-opacity z-30">
    <svg class="w-7 h-7 text-[#F9A825]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4.5v15m7.5-7.5h-15"/>
    </svg>
</a>
@endif

@endsection
