@extends('layouts.app-officer')
@section('title', 'Draft Announcement')
@section('club-name', $club?->name ?? 'ClubSync')

@section('content')

<div class="flex items-center gap-3 bg-[#07873e] px-5 py-4">
    <a href="{{ route('officer.announcements.index') }}" class="text-white/70 hover:text-white transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
        </svg>
    </a>
    <h1 class="text-white font-bold text-xl">Draft Announcement</h1>
</div>

<div class="px-4 py-5">


    <form method="POST" action="{{ route('officer.announcements.store') }}" class="space-y-4" id="ann-form"
          data-loading="dialog" data-loading-message="Posting announcement">
        @csrf
        <input type="hidden" name="ai_assisted" id="ai_assisted_flag" value="0">

        {{-- Type toggle --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Type <span class="text-red-500">*</span>
            </label>
            <div class="grid grid-cols-2 gap-3">
                <label class="cursor-pointer">
                    <input type="radio" name="type" value="announcement" class="sr-only peer"
                           {{ old('type', 'announcement') === 'announcement' ? 'checked' : '' }}>
                    <div class="border-2 border-gray-200 rounded-xl px-4 py-3 text-sm font-semibold text-center text-gray-600
                                peer-checked:border-[#07873e] peer-checked:bg-[#07873e]/5 peer-checked:text-[#07873e] transition-all">
                        Announcement
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="type" value="letter" class="sr-only peer"
                           {{ old('type') === 'letter' ? 'checked' : '' }}>
                    <div class="border-2 border-gray-200 rounded-xl px-4 py-3 text-sm font-semibold text-center text-gray-600
                                peer-checked:border-[#07873e] peer-checked:bg-[#07873e]/5 peer-checked:text-[#07873e] transition-all">
                        Letter
                    </div>
                </label>
            </div>
        </div>

        {{-- Title --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                Title <span class="text-gray-400 text-xs font-normal">(Optional)</span>
            </label>
            <input type="text" name="title" id="title-field" value="{{ old('title') }}"
                   placeholder="Announcement title..."
                   class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30">
        </div>

        {{-- AI draft (when configured) and templates (always) --}}
        @include('officer.announcements.partials.assist', [
            'aiHeading'     => 'Generate with AI',
            'aiPlaceholder' => 'e.g. Remind members about upcoming general assembly on June 5...',
        ])

        {{-- Content --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                Content <span class="text-red-500">*</span>
            </label>
            <textarea name="content" id="content-field" rows="8" required
                      placeholder="Write your announcement here..."
                      class="w-full border rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30 resize-none
                             {{ $errors->has('content') ? 'border-red-400' : 'border-gray-300' }}">{{ old('content') }}</textarea>
            @error('content')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        <div class="grid grid-cols-2 gap-3 pt-2">
            <a href="{{ route('officer.announcements.index') }}"
               class="flex items-center justify-center border-2 border-[#07873e] text-[#07873e] font-semibold text-sm rounded-xl py-3.5 hover:bg-[#07873e]/5 transition-colors">
                Cancel
            </a>
            <button type="submit"
                    class="bg-[#07873e] text-[#F9A825] font-semibold text-sm rounded-xl py-3.5 hover:opacity-90 transition-opacity"
                    onclick="return announcementConfirm('Publish', 'Publish this to your club members?')">
                Publish
            </button>
        </div>
    </form>
</div>


@endsection
