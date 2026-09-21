@extends('layouts.auth')
@section('title', 'Forgot Password — ClubSync')

@section('content')
<div class="flex flex-col min-h-screen">

    {{-- Header --}}
    <div class="flex items-center gap-3 px-6 pt-14 pb-6">
        <a href="{{ route('login') }}" class="text-green-800 hover:text-green-900 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
            </svg>
        </a>
        <span class="text-green-800 font-semibold text-base">Forgot Password</span>
    </div>

    <div class="flex-1 px-8 pb-12">

        @if(session('sent'))
            {{-- ── Success state ───────────────────────────────────────────── --}}
            <div class="flex flex-col items-center text-center pt-8">
                <div class="w-16 h-16 rounded-full bg-green-100 flex items-center justify-center mb-6">
                    <svg class="w-8 h-8 text-green-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-green-900 mb-3">Check your email</h1>
                <p class="text-gray-500 text-sm leading-relaxed mb-2">
                    We've sent a password reset link to your email address.
                </p>
                <p class="text-gray-400 text-xs mb-10">
                    Didn't receive it? Check your spam folder or try again.
                </p>
                <a href="{{ route('forgot-password') }}"
                   class="text-green-700 font-semibold text-sm hover:text-green-900 transition-colors">
                    Send again
                </a>
            </div>

        @else
            {{-- ── Request form ─────────────────────────────────────────────── --}}
            <h1 class="text-2xl font-bold text-green-900 mb-2">Reset your password</h1>
            <p class="text-gray-500 text-sm mb-8">
                Enter your SCC institutional email and we'll send you a link to reset your password.
            </p>

            @if($errors->any())
                <div class="mb-5 bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-red-700 text-sm">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('forgot-password') }}" class="space-y-5">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Email</label>
                    <input
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        placeholder="yourname@sccpag.edu.ph"
                        autocomplete="email"
                        autofocus
                        class="w-full border border-gray-300 rounded-xl px-4 py-3.5 text-sm text-gray-900 placeholder-gray-400
                               focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition
                               @error('email') border-red-400 @enderror"
                    >
                </div>

                <div class="pt-2">
                    <button type="submit"
                            class="w-full bg-green-800 text-white font-semibold text-[15px] py-4 px-6 rounded-full
                                   hover:bg-green-900 transition-colors">
                        Send Reset Link
                    </button>
                </div>
            </form>

            <p class="text-center text-sm text-gray-500 mt-6">
                Remembered it?
                <a href="{{ route('login') }}" class="text-green-700 font-semibold hover:text-green-900 transition-colors">Login</a>
            </p>
        @endif

    </div>
</div>
@endsection
