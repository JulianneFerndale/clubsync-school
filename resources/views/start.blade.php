@extends('layouts.auth')
@section('title', 'ClubSync')

@section('content')
<div class="flex-1 flex flex-col relative min-h-screen">

    {{-- Photo collage background (replace divs with <img> tags for production) --}}
    <div class="flex-1 relative overflow-hidden">
        <div class="absolute inset-0 grid grid-cols-2 grid-rows-2 gap-1 p-1">
            <div class="relative rounded-2xl overflow-hidden bg-green-300">
                <img src="/images/event1.jpg" class="w-full h-full object-cover">
            </div>
            <div class="relative rounded-2xl overflow-hidden bg-green-200">
                <img src="/images/event2.jpg" class="w-full h-full object-cover">
            </div>
            <div class="relative rounded-2xl overflow-hidden bg-green-400">
                <img src="/images/event3.jpg" class="w-full h-full object-cover">
            </div>
            <div class="relative rounded-2xl overflow-hidden bg-green-300">
                <img src="/images/event4.jpg" class="w-full h-full object-cover">
            </div>
        </div>

        {{-- Club logo overlays --}}
        <div class="absolute top-6 left-6 w-10 h-10 rounded-full bg-white border-2 border-green-800 flex items-center justify-center shadow-md">
            <img src="/images/clubsync_logo.png" alt="SCC ClubSync" class="w-full h-full object-contain p-1">
        </div>
        <div class="absolute top-6 right-6 w-10 h-10 rounded-full bg-white/90 border border-green-300 flex items-center justify-center shadow">
            <svg class="w-5 h-5 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"/>
            </svg>
        </div>

        {{-- Bottom fade --}}
        <div class="absolute bottom-0 left-0 right-0 h-32 bg-gradient-to-t from-white via-white/80 to-transparent"></div>
    </div>

    {{-- Content --}}
    <div class="px-8 pb-12 pt-2 text-center">
        <h1 class="text-2xl font-bold text-green-900 leading-snug mb-2">
            See what's happening at Saint Columban College
        </h1>
        <p class="text-gray-500 text-sm mb-8">
            Sync up with every event, club, and update
        </p>

        <a href="{{ route('welcome') }}"
           class="flex items-center justify-between w-full bg-green-800 text-white font-semibold text-[15px] py-4 px-6 rounded-full hover:bg-green-900 transition-colors">
            Get Started
            <span class="w-8 h-8 rounded-full bg-yellow-400 flex items-center justify-center flex-shrink-0">
                <svg class="w-4 h-4 text-green-900" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/>
                </svg>
            </span>
        </a>
    </div>
</div>
@endsection
