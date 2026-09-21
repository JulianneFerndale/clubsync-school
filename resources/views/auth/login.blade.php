@extends('layouts.auth')
@section('title', 'Login — ClubSync')

@section('content')
<div class="flex flex-col min-h-screen">

    {{-- Header --}}
    <div class="flex items-center gap-3 px-6 pt-14 pb-6">
        <a href="{{ route('welcome') }}" class="text-green-800 hover:text-green-900 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
            </svg>
        </a>
        <span class="text-green-800 font-semibold text-base">Login</span>
    </div>

    {{-- Form --}}
    <div class="flex-1 px-8 pb-12">
        <h1 class="text-2xl font-bold text-green-900 mb-8">Login to your account</h1>

        @if ($errors->any())
            <div class="mb-5 bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-red-700 text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('login') }}" data-turbo="false" class="space-y-5"
              data-loading="splash" data-loading-message="Signing you in…">
            @csrf

            {{-- Email --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Email</label>
                <input
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    placeholder="Enter your Institutional Email"
                    autocomplete="email"
                    class="w-full border border-gray-300 rounded-xl px-4 py-3.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition @error('email') border-red-400 @enderror"
                >
            </div>

            {{-- Password --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
                <div class="relative">
                    <input
                        type="password"
                        name="password"
                        id="password"
                        placeholder="Enter Password"
                        autocomplete="current-password"
                        class="w-full border border-gray-300 rounded-xl px-4 py-3.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition pr-12"
                    >
                    <button type="button" onclick="togglePassword('password', this)"
                            class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
                        {{-- eye-open (shown when password is hidden) --}}
                        <svg data-show-when="hidden" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                        </svg>
                        {{-- eye-slash (shown when password is visible) --}}
                        <svg data-show-when="visible" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/>
                        </svg>
                    </button>
                </div>
            </div>

            {{-- Remember + Forgot --}}
            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="remember" class="w-4 h-4 rounded border-gray-300 text-green-600 focus:ring-green-500">
                    <span class="text-sm text-gray-600">Remember Me</span>
                </label>
                <a href="{{ route('forgot-password') }}" class="text-sm text-green-700 font-medium hover:text-green-900 transition-colors">
                    Forgot Password?
                </a>
            </div>

            {{-- Submit --}}
            <div class="pt-4">
                <button type="submit"
                        class="w-full bg-green-800 text-white font-semibold text-[15px] py-4 px-6 rounded-full hover:bg-green-900 focus:bg-green-900 transition-colors">
                    Login
                </button>
            </div>
        </form>

        <p class="text-center text-sm text-gray-500 mt-6">
            Don't have an account?
            <a href="{{ route('register') }}" class="text-green-700 font-semibold hover:text-green-900 transition-colors">Register</a>
        </p>
    </div>
</div>

<script>
function togglePassword(id, btn) {
    const input = document.getElementById(id);
    const isHidden = input.type === 'password';
    input.type = isHidden ? 'text' : 'password';
    btn.querySelectorAll('svg').forEach(svg => {
        const showWhen = svg.dataset.showWhen;
        svg.classList.toggle('hidden', showWhen === 'hidden' ? !isHidden : isHidden);
    });
}
</script>
@endsection
