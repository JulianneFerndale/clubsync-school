@extends('layouts.auth')
@section('title', 'ClubSync — Get Started')

@section('content')
<div class="flex-1 flex flex-col min-h-screen">

    {{-- Background with icon arrangement --}}
    <div class="relative flex-1 flex items-center justify-center overflow-hidden bg-gray-50">
        <div class="absolute inset-0 bg-gradient-to-b from-green-50 to-white opacity-80"></div>

        {{-- Icon cluster --}}
        <div class="relative w-52 h-52 flex items-center justify-center">
            <div class="absolute w-48 h-48 rounded-full border-2 border-dashed border-green-300"></div>
            <div class="absolute w-32 h-32 rounded-full border-2 border-dashed border-green-400"></div>

            {{-- Center icon --}}
            <div class="w-14 h-14 rounded-full bg-white border-2 border-green-600 flex items-center justify-center shadow-md z-10">
                <img src="/images/clubsync_logo.png" alt="SCC ClubSync" class="w-full h-full object-contain p-1">
            </div>

            {{-- Orbital icons --}}
            <div class="absolute top-2 left-9 w-9 h-9 rounded-full bg-white shadow flex items-center justify-center border border-green-200">
                <svg class="w-4 h-4 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/>
                </svg>
            </div>
            <div class="absolute top-2 right-9 w-9 h-9 rounded-full bg-white shadow flex items-center justify-center border border-green-200">
                <svg class="w-4 h-4 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
                </svg>
            </div>
            <div class="absolute top-1/2 -translate-y-1/2 left-0 w-9 h-9 rounded-full bg-white shadow flex items-center justify-center border border-green-200">
                <svg class="w-4 h-4 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>
                </svg>
            </div>
            <div class="absolute top-1/2 -translate-y-1/2 right-0 w-9 h-9 rounded-full bg-white shadow flex items-center justify-center border border-green-200">
                <svg class="w-4 h-4 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.562.562 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.562.562 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/>
                </svg>
            </div>
            <div class="absolute bottom-2 left-9 w-9 h-9 rounded-full bg-white shadow flex items-center justify-center border border-green-200">
                <svg class="w-4 h-4 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.068.157 2.148.279 3.238.364.466.037.893.281 1.153.671L12 21l2.652-3.978c.26-.39.687-.634 1.153-.67 1.09-.086 2.17-.208 3.238-.365 1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/>
                </svg>
            </div>
            <div class="absolute bottom-2 right-9 w-9 h-9 rounded-full bg-white shadow flex items-center justify-center border border-green-200">
                <svg class="w-4 h-4 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
        </div>
    </div>

    {{-- Text + Buttons --}}
    <div class="px-8 pt-6 pb-12 flex flex-col">
        <h1 class="text-2xl font-bold text-green-900 mb-1">Let's Get Started!</h1>
        <p class="text-gray-600 text-sm font-medium mb-1">Stay connected. Stay involved.</p>
        <p class="text-gray-400 text-xs mb-8">Login with your SCC institutional email to connect with your clubs</p>

        <div class="space-y-3">
            <a href="{{ route('register') }}"
               class="flex items-center justify-between w-full border-2 border-green-700 text-green-800 font-semibold text-[15px] py-4 px-6 rounded-full hover:bg-green-50 transition-colors">
                Register
                <span class="w-8 h-8 rounded-full bg-yellow-400 flex items-center justify-center flex-shrink-0">
                    <svg class="w-4 h-4 text-green-900" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/>
                    </svg>
                </span>
            </a>

            <a href="{{ route('login') }}"
               class="flex items-center justify-center w-full bg-green-800 text-white font-semibold text-[15px] py-4 px-6 rounded-full hover:bg-green-900 transition-colors">
                Login
            </a>
        </div>
    </div>
</div>
@endsection
