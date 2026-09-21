@extends('layouts.app-member')
@section('title', 'Bulletin Board — ClubSync')

@section('live-refresh', 'any')
@section('content')

{{-- Header --}}
<div class="bg-[#07873e] px-5 pt-12 pb-5">
    <h1 class="text-white font-bold text-xl">Bulletin Board</h1>
    <p class="text-white/60 text-xs mt-1">Published announcements from all clubs</p>
</div>

<div class="px-4 py-5 space-y-4">

    {{-- Club Stories — adviser-approved AI narratives --}}
    @if($narratives->isNotEmpty())
        <div class="space-y-3">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest px-1">Club Stories</p>
            @foreach($narratives as $narrative)
                <div class="bg-white rounded-xl border border-purple-100 shadow-sm overflow-hidden">
                    <div class="flex items-center gap-2.5 px-4 pt-3.5 pb-2.5 border-b border-gray-50">
                        <div class="w-7 h-7 rounded-full bg-purple-100 flex items-center justify-center flex-shrink-0">
                            <span class="text-purple-500 text-[10px] font-bold">
                                {{ strtoupper(substr($narrative->club?->acronym ?? $narrative->club?->name ?? 'C', 0, 2)) }}
                            </span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-semibold text-gray-700 truncate">{{ $narrative->club?->name ?? 'Unknown Club' }}</p>
                            <p class="text-[10px] text-gray-400">{{ $narrative->published_at?->diffForHumans() }}</p>
                        </div>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-purple-50 text-purple-600 text-[10px] font-semibold">✦ Narrative</span>
                    </div>
                    <div class="px-4 py-3">
                        <p class="text-sm font-bold text-gray-900 mb-1">{{ $narrative->title }}</p>
                        <p class="text-sm text-gray-600 leading-relaxed whitespace-pre-line">{{ $narrative->finalContent() }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if($announcements->isEmpty())
        <div class="text-center py-14">
            <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-3">
                <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.34 15.84c-.688-.06-1.386-.09-2.09-.09H7.5a4.5 4.5 0 1 1 0-9h.75c.704 0 1.402-.03 2.09-.09m0 9.18c.253.962.584 1.892.985 2.783.247.55.06 1.21-.463 1.511l-.657.38c-.551.318-1.26.117-1.527-.461a20.845 20.845 0 0 1-1.44-4.282m3.102.069a18.03 18.03 0 0 1-.59-4.59c0-1.586.205-3.124.59-4.59"/>
                </svg>
            </div>
            <p class="text-sm text-gray-400 font-medium">No announcements yet</p>
        </div>
    @else
        @foreach($announcements as $ann)
            <a href="{{ route('member.bulletin.show', $ann) }}"
               class="block bg-white rounded-xl border border-gray-100 shadow-sm hover:shadow-md transition-shadow">

                {{-- Club bar --}}
                <div class="flex items-center gap-2.5 px-4 pt-3.5 pb-2.5 border-b border-gray-50">
                    <div class="w-7 h-7 rounded-full bg-[#07873e]/10 flex items-center justify-center flex-shrink-0">
                        <span class="text-[#07873e] text-[10px] font-bold">
                            {{ strtoupper(substr($ann->club?->acronym ?? $ann->club?->name ?? 'C', 0, 2)) }}
                        </span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-gray-700 truncate">
                            {{ $ann->club?->name ?? 'Unknown Club' }}
                        </p>
                        <p class="text-[10px] text-gray-400">{{ $ann->published_at?->diffForHumans() }}</p>
                    </div>
                    <x-status-badge :status="$ann->type" />
                </div>

                {{-- Content --}}
                <div class="px-4 py-3">
                    @if($ann->title)
                        <p class="text-sm font-bold text-gray-900 mb-1">{{ $ann->title }}</p>
                    @endif
                    <p class="text-sm text-gray-600 leading-relaxed line-clamp-3">{{ $ann->content }}</p>
                </div>

                {{-- Footer --}}
                <div class="flex items-center gap-4 px-4 pb-3.5">
                    {{-- Like count --}}
                    <div class="flex items-center gap-1.5 text-gray-400">
                        <svg class="{{ $likedIds->has($ann->id) ? 'text-red-500 fill-red-500' : '' }} w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z"/>
                        </svg>
                        <span class="text-xs font-medium">{{ $ann->likes->count() }}</span>
                    </div>

                    {{-- Comment count --}}
                    <div class="flex items-center gap-1.5 text-gray-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z"/>
                        </svg>
                        <span class="text-xs font-medium">{{ $ann->comments_count }}</span>
                    </div>

                    <span class="ml-auto text-[10px] text-gray-300">Tap to read</span>
                </div>
            </a>
        @endforeach

        {{-- Pagination --}}
        @if($announcements->hasPages())
            <div class="flex items-center justify-center gap-3 pt-2">
                @if($announcements->onFirstPage())
                    <span class="text-gray-300 text-sm">←</span>
                @else
                    <a href="{{ $announcements->previousPageUrl() }}" class="text-[#07873e] text-sm font-semibold">← Newer</a>
                @endif

                <span class="text-xs text-gray-400">Page {{ $announcements->currentPage() }} of {{ $announcements->lastPage() }}</span>

                @if($announcements->hasMorePages())
                    <a href="{{ $announcements->nextPageUrl() }}" class="text-[#07873e] text-sm font-semibold">Older →</a>
                @else
                    <span class="text-gray-300 text-sm">→</span>
                @endif
            </div>
        @endif
    @endif

</div>
@endsection
