@extends('layouts.auth')
@section('title', 'Connect Google — ClubSync')

@section('content')
{{--
    The first Google sign-in to an account that already has a password. Firebase joins
    the two only after the password is given once (GoogleController); after that, Google
    alone signs this person in.
--}}
<div class="flex flex-col min-h-screen">

    <div class="flex items-center gap-3 px-6 pt-14 pb-6">
        <a href="{{ route('login') }}" class="text-green-800 hover:text-green-900 transition-colors" aria-label="Back to login">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
            </svg>
        </a>
        <span class="text-green-800 font-semibold text-base">Connect Google</span>
    </div>

    <div class="flex-1 px-8 pb-12">
        <h1 class="text-2xl font-bold text-green-900 mb-3">Confirm it's you</h1>
        <p class="text-sm text-gray-600 leading-relaxed mb-8">
            <span class="font-medium text-gray-900">{{ $email }}</span> already has a ClubSync password.
            Enter it once to connect your SCC Google account. After this you can sign in with Google
            or with your email and password.
        </p>

        <form method="POST" action="{{ route('auth.google.link') }}" data-turbo="false" class="space-y-5"
              data-loading="splash" data-loading-message="Connecting your Google account…">
            @csrf

            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">ClubSync password</label>
                <input
                    type="password"
                    name="password"
                    id="password"
                    placeholder="Enter Password"
                    autocomplete="current-password"
                    autofocus
                    class="w-full border border-gray-300 rounded-xl px-4 py-3.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition @error('password') border-red-400 @enderror"
                >
            </div>

            <div class="flex justify-end">
                <a href="{{ route('forgot-password') }}" class="text-sm text-green-700 font-medium hover:text-green-900 transition-colors">
                    Forgot Password?
                </a>
            </div>

            <div class="pt-4">
                <button type="submit"
                        class="w-full bg-green-800 text-white font-semibold text-[15px] py-4 px-6 rounded-full hover:bg-green-900 focus:bg-green-900 transition-colors">
                    Connect and sign in
                </button>
            </div>
        </form>

        <p class="text-center text-sm text-gray-500 mt-6">
            <a href="{{ route('login') }}" class="text-green-700 font-semibold hover:text-green-900 transition-colors">Cancel</a>
        </p>
    </div>
</div>
@endsection
