@extends('layouts.auth')
@section('title', 'ClubSync — Get Started')

@section('content')
<div class="flex-1 flex flex-col min-h-screen">

    {{-- Hero: the SCC ClubSync crest at the centre of an orbit of what the app does --}}
    <div class="cs-hero relative flex-1 flex items-center justify-center overflow-hidden py-10">

        <div class="cs-orbit relative flex items-center justify-center">
            <div class="cs-ring cs-ring--outer" aria-hidden="true"></div>
            <div class="cs-ring cs-ring--inner" aria-hidden="true"></div>
            <div class="cs-glow" aria-hidden="true"></div>

            {{-- The crest. The image is a wide canvas with the crest in the middle,
                 so object-cover frames the crest itself instead of shrinking the canvas. --}}
            <div class="cs-crest relative z-10 rounded-full bg-white p-1.5">
                <img src="/images/clubsync_logo.png" alt="Saint Columban College ClubSync crest"
                     class="w-full h-full rounded-full object-cover">
            </div>

            {{-- Orbital icons: events, members, notifications, highlights, messages, attendance --}}
            <div class="cs-satellite" style="--a:-120deg;--d:0s" aria-hidden="true"><span>
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/>
                </svg>
            </span></div>
            <div class="cs-satellite" style="--a:-60deg;--d:.6s" aria-hidden="true"><span>
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
                </svg>
            </span></div>
            <div class="cs-satellite" style="--a:180deg;--d:1.2s" aria-hidden="true"><span>
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0"/>
                </svg>
            </span></div>
            <div class="cs-satellite" style="--a:0deg;--d:1.8s" aria-hidden="true"><span>
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11.48 3.499a.562.562 0 011.04 0l2.125 5.111a.563.563 0 00.475.345l5.518.442c.499.04.701.663.321.988l-4.204 3.602a.563.563 0 00-.182.557l1.285 5.385a.562.562 0 01-.84.61l-4.725-2.885a.562.562 0 00-.586 0L6.982 20.54a.562.562 0 01-.84-.61l1.285-5.386a.562.562 0 00-.182-.557l-4.204-3.602a.562.562 0 01.321-.988l5.518-.442a.563.563 0 00.475-.345L11.48 3.5z"/>
                </svg>
            </span></div>
            <div class="cs-satellite" style="--a:120deg;--d:2.4s" aria-hidden="true"><span>
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.25 12.76c0 1.6 1.123 2.994 2.707 3.227 1.068.157 2.148.279 3.238.364.466.037.893.281 1.153.671L12 21l2.652-3.978c.26-.39.687-.634 1.153-.67 1.09-.086 2.17-.208 3.238-.365 1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0012 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018z"/>
                </svg>
            </span></div>
            <div class="cs-satellite" style="--a:60deg;--d:3s" aria-hidden="true"><span>
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </span></div>
        </div>
    </div>

    {{-- Text + Buttons --}}
    <div class="px-8 pt-2 pb-12 flex flex-col">
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

<style>
    /* Sizes scale together: the orbit, the rings on it, and the crest in the middle. */
    .cs-hero{--g:#07873e;--gold:#F9A825;--orbit:18.5rem;--crest:10.5rem;
        background:
            radial-gradient(circle at 50% 50%, rgba(249,168,37,.20) 0, rgba(249,168,37,0) 38%),
            radial-gradient(rgba(7,135,62,.10) 1px, transparent 1.4px) 0 0/18px 18px,
            linear-gradient(to bottom, #ecfdf3, #ffffff)}
    @media (min-width:640px){.cs-hero{--orbit:22rem;--crest:12.5rem}}
    .cs-orbit{width:var(--orbit);height:var(--orbit)}
    .cs-ring{position:absolute;border-radius:9999px;border:2px dashed rgba(7,135,62,.35)}
    .cs-ring--outer{inset:0;animation:cs-spin 60s linear infinite}
    .cs-ring--inner{inset:calc((var(--orbit) - var(--crest)) / 2 - 1.4rem);border-color:rgba(249,168,37,.7);animation:cs-spin 45s linear infinite reverse}
    .cs-glow{position:absolute;width:calc(var(--crest) + 3rem);height:calc(var(--crest) + 3rem);border-radius:9999px;
        background:radial-gradient(circle, rgba(249,168,37,.45) 0%, rgba(249,168,37,0) 70%);animation:cs-breathe 5s ease-in-out infinite}
    .cs-crest{width:var(--crest);height:var(--crest);
        box-shadow:0 0 0 5px var(--gold),0 0 0 11px rgba(7,135,62,.12),0 22px 45px -14px rgba(7,135,62,.6);
        animation:cs-crest-in .8s cubic-bezier(.34,1.56,.64,1) both, cs-float 6s 1s ease-in-out infinite}
    /* Each satellite sits on the outer ring at angle --a, then bobs on its own. */
    .cs-satellite{position:absolute;left:50%;top:50%;width:2.75rem;height:2.75rem;
        transform:translate(-50%,-50%) rotate(var(--a)) translateX(calc(var(--orbit) / 2)) rotate(calc(-1 * var(--a)))}
    .cs-satellite>span{display:flex;width:100%;height:100%;align-items:center;justify-content:center;border-radius:9999px;
        background:#fff;color:var(--g);border:1.5px solid rgba(249,168,37,.7);box-shadow:0 8px 18px -8px rgba(7,135,62,.45);
        animation:cs-pop .5s calc(.35s + var(--d) / 6) cubic-bezier(.34,1.56,.64,1) both, cs-bob 4s var(--d) ease-in-out infinite}
    @keyframes cs-spin{to{transform:rotate(360deg)}}
    @keyframes cs-breathe{0%,100%{transform:scale(.94);opacity:.8}50%{transform:scale(1.06);opacity:1}}
    @keyframes cs-crest-in{from{transform:scale(.6);opacity:0}to{transform:none;opacity:1}}
    @keyframes cs-float{0%,100%{transform:translateY(0)}50%{transform:translateY(-6px)}}
    @keyframes cs-pop{from{transform:scale(0);opacity:0}to{transform:none;opacity:1}}
    @keyframes cs-bob{0%,100%{translate:0 0}50%{translate:0 -5px}}
    @media (prefers-reduced-motion:reduce){
        .cs-ring,.cs-glow,.cs-crest,.cs-satellite>span{animation:none!important}
    }
</style>
@endsection
