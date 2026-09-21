@extends('layouts.app-member')
@section('title', $announcement->title ?? 'Announcement')

@section('content')

{{-- Header --}}
<div class="bg-[#07873e] px-5 pt-12 pb-5">
    <a href="{{ route('member.bulletin.index') }}" class="text-white/70 hover:text-white mb-4 inline-block">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
        </svg>
    </a>
    <div class="flex items-start justify-between gap-3">
        <h1 class="text-white font-bold text-xl leading-snug flex-1">
            {{ $announcement->title ?? 'Announcement' }}
        </h1>
        <x-status-badge :status="$announcement->type" />
    </div>
</div>

<div class="px-4 py-5 space-y-4">

    {{-- Club + date meta --}}
    <div class="flex items-center gap-2.5">
        <div class="w-9 h-9 rounded-full bg-[#07873e]/10 flex items-center justify-center flex-shrink-0">
            <span class="text-[#07873e] text-xs font-bold">
                {{ strtoupper(substr($announcement->club?->acronym ?? $announcement->club?->name ?? 'C', 0, 2)) }}
            </span>
        </div>
        <div>
            <p class="text-sm font-semibold text-gray-800">{{ $announcement->club?->name ?? 'Club' }}</p>
            <p class="text-xs text-gray-400">
                Published {{ $announcement->published_at?->format('F j, Y') }}
                @if($announcement->ai_assisted)
                    · <span class="text-purple-500 font-medium">✦ AI-assisted</span>
                @endif
            </p>
        </div>
    </div>

    {{-- Content --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
        <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-line">{{ $announcement->content }}</p>
    </div>

    {{-- Like / comment bar --}}
    <div class="flex items-center gap-4 bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3">
        {{-- Like button (toggle) --}}
        <form method="POST" action="{{ route('member.bulletin.like', $announcement) }}">
            @csrf
            <button type="submit" class="flex items-center gap-2 group">
                <svg class="w-5 h-5 transition-colors {{ $liked ? 'text-red-500 fill-red-500' : 'text-gray-400 group-hover:text-red-400' }}"
                     fill="{{ $liked ? 'currentColor' : 'none' }}" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z"/>
                </svg>
                <span class="text-sm font-semibold {{ $liked ? 'text-red-500' : 'text-gray-500' }}">
                    {{ $announcement->likes->count() }}
                </span>
            </button>
        </form>

        <div class="flex items-center gap-2 text-gray-400">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z"/>
            </svg>
            <span class="text-sm font-semibold">{{ $announcement->comments->count() }}</span>
        </div>
    </div>

    {{-- Comments --}}
    <div class="space-y-3">
        <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest px-1">Comments</p>

        @forelse($announcement->comments as $comment)
            <div class="flex gap-3">
                <div class="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center flex-shrink-0">
                    <span class="text-gray-500 text-xs font-bold">
                        {{ strtoupper(substr($comment->user?->first_name ?? 'U', 0, 1)) }}
                    </span>
                </div>
                <div class="flex-1 bg-white rounded-xl border border-gray-100 shadow-sm px-3.5 py-3">
                    <div class="flex items-center justify-between mb-1">
                        <p class="text-xs font-semibold text-gray-800">
                            {{ $comment->user?->first_name }} {{ $comment->user?->last_name }}
                        </p>
                        <p class="text-[10px] text-gray-400">{{ $comment->created_at->diffForHumans() }}</p>
                    </div>
                    <p class="text-sm text-gray-700 leading-relaxed">{{ $comment->content }}</p>
                </div>
            </div>
        @empty
            <p class="text-sm text-gray-400 text-center py-3">No comments yet. Be the first!</p>
        @endforelse
    </div>

    {{-- Add comment --}}
    <form method="POST" action="{{ route('member.bulletin.comment', $announcement) }}" class="pb-2">
        @csrf
        <div class="flex gap-2.5">
            <div class="w-8 h-8 rounded-full bg-[#07873e]/10 flex items-center justify-center flex-shrink-0 mt-0.5">
                <span class="text-[#07873e] text-xs font-bold">{{ strtoupper(substr(auth()->user()?->first_name ?? 'Y', 0, 1)) }}</span>
            </div>
            <div class="flex-1 flex flex-col gap-1.5">
                @error('content')
                    <p class="text-red-500 text-xs">{{ $message }}</p>
                @enderror
                <div class="flex items-end gap-2">
                    <textarea name="content" rows="1"
                              placeholder="Write a comment…"
                              class="flex-1 border @error('content') border-red-400 @else border-gray-200 @enderror rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30 resize-none">{{ old('content') }}</textarea>
                    <button type="submit"
                            class="flex-shrink-0 bg-[#07873e] text-[#F9A825] rounded-xl px-4 py-2.5 text-sm font-semibold hover:opacity-90 transition-opacity">
                        Post
                    </button>
                </div>
            </div>
        </div>
    </form>

</div>
@endsection
