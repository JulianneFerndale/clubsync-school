@extends('layouts.auth')
@section('title', 'Reset Password — ClubSync')

@section('content')
<div class="flex flex-col min-h-screen">

    {{-- Header --}}
    <div class="flex items-center gap-3 px-6 pt-14 pb-6">
        <a href="{{ route('login') }}" class="text-green-800 hover:text-green-900 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
            </svg>
        </a>
        <span class="text-green-800 font-semibold text-base">Reset Password</span>
    </div>

    <div class="flex-1 px-8 pb-12">

        @if($valid)
            {{-- ── Set a new password ───────────────────────────────────────── --}}
            <h1 class="text-2xl font-bold text-green-900 mb-2">Set a new password</h1>
            <p class="text-gray-500 text-sm mb-8">
                Choose a new password for
                <span class="font-semibold text-gray-700">{{ $email }}</span>.
            </p>

            <form method="POST" action="{{ route('reset-password.update') }}" class="space-y-5">
                @csrf
                <input type="hidden" name="oobCode" value="{{ $oobCode }}">

                {{-- New password --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">New password</label>
                    <div class="relative">
                        <input
                            type="password"
                            name="password"
                            id="password"
                            placeholder="Enter a new password"
                            autocomplete="new-password"
                            autofocus
                            class="w-full border border-gray-300 rounded-xl px-4 py-3.5 text-sm text-gray-900 placeholder-gray-400
                                   focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition pr-12
                                   @error('password') border-red-400 @enderror"
                        >
                        <button type="button" onclick="toggleReset('password', this)"
                                class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
                            <svg data-show-when="hidden" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                            </svg>
                            <svg data-show-when="visible" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/>
                            </svg>
                        </button>
                    </div>
                    <p class="text-xs text-gray-400 mt-1.5">At least 8 characters, with an uppercase letter, a lowercase letter and a number.</p>
                </div>

                {{-- Confirm --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Confirm new password</label>
                    <div class="relative">
                        <input
                            type="password"
                            name="password_confirmation"
                            id="password_confirmation"
                            placeholder="Re-enter the new password"
                            autocomplete="new-password"
                            class="w-full border border-gray-300 rounded-xl px-4 py-3.5 text-sm text-gray-900 placeholder-gray-400
                                   focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition pr-12"
                        >
                        <button type="button" onclick="toggleReset('password_confirmation', this)"
                                class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
                            <svg data-show-when="hidden" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                            </svg>
                            <svg data-show-when="visible" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit"
                            class="w-full bg-green-800 text-white font-semibold text-[15px] py-4 px-6 rounded-full
                                   hover:bg-green-900 transition-colors">
                        Reset Password
                    </button>
                </div>
            </form>

        @else
            {{-- ── Invalid / expired link ───────────────────────────────────── --}}
            <div class="flex flex-col items-center text-center pt-8">
                <div class="w-16 h-16 rounded-full bg-amber-100 flex items-center justify-center mb-6">
                    <svg class="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8"
                              d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-green-900 mb-3">This link isn't valid</h1>
                <p class="text-gray-500 text-sm leading-relaxed mb-10">
                    Your password reset link has expired or has already been used.
                    Request a new one and we'll email you a fresh link.
                </p>
                <a href="{{ route('forgot-password') }}"
                   class="w-full bg-green-800 text-white font-semibold text-[15px] py-4 px-6 rounded-full
                          hover:bg-green-900 transition-colors text-center">
                    Request a new link
                </a>
            </div>
        @endif

        <p class="text-center text-sm text-gray-500 mt-6">
            Remembered it?
            <a href="{{ route('login') }}" class="text-green-700 font-semibold hover:text-green-900 transition-colors">Login</a>
        </p>

    </div>
</div>

@if($valid)
<script>
function toggleReset(id, btn) {
    const input = document.getElementById(id);
    const isHidden = input.type === 'password';
    input.type = isHidden ? 'text' : 'password';
    btn.querySelectorAll('svg').forEach(svg => {
        const showWhen = svg.dataset.showWhen;
        svg.classList.toggle('hidden', showWhen === 'hidden' ? !isHidden : isHidden);
    });
}
</script>
@endif
@endsection
