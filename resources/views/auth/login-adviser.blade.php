@extends('layouts.auth')
@section('title', 'Adviser Login — ClubSync')

@section('content')
{{--
    The advisers' sign-in (POLICY.md §Authentication). Advisers are employees, not
    students: they sign in with their adviser number as well as their SCC email and
    password, or with their SCC Google account, and only an adviser account gets in here.
--}}
<div class="flex flex-col min-h-screen">

    {{-- Header --}}
    <div class="flex items-center gap-3 px-6 pt-14 pb-6">
        <a href="{{ route('welcome') }}" class="text-green-800 hover:text-green-900 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
            </svg>
        </a>
        <span class="text-green-800 font-semibold text-base">Adviser Login</span>
    </div>

    {{-- Form --}}
    <div class="flex-1 px-8 pb-12">
        <h1 class="text-2xl font-bold text-green-900 mb-6">Login to your account</h1>

        @include('auth._sign-in-tabs', ['active' => 'adviser'])

        <form id="login-form" method="POST" action="{{ route('login.adviser') }}" data-turbo="false" class="space-y-5"
              data-loading="splash" data-loading-message="Signing you in…">
            @csrf

            {{-- Adviser number --}}
            <div>
                <label for="employee_number" class="block text-sm font-medium text-gray-700 mb-1.5">Adviser Number</label>
                <input
                    type="text"
                    name="employee_number"
                    id="employee_number"
                    value="{{ old('employee_number') }}"
                    placeholder="e.g. SCC-ADV-0001"
                    autocomplete="username"
                    autocapitalize="characters"
                    class="w-full border border-gray-300 rounded-xl px-4 py-3.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition @error('employee_number') border-red-400 @enderror"
                >
            </div>

            {{-- Email --}}
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700 mb-1.5">Email</label>
                <input
                    type="email"
                    name="email"
                    id="email"
                    value="{{ old('email') }}"
                    placeholder="Enter your Institutional Email"
                    autocomplete="email"
                    class="w-full border border-gray-300 rounded-xl px-4 py-3.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition @error('email') border-red-400 @enderror"
                >
            </div>

            {{-- Password --}}
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1.5">Password</label>
                <div class="relative">
                    <input
                        type="password"
                        name="password"
                        id="password"
                        placeholder="Enter Password"
                        autocomplete="current-password"
                        class="w-full border border-gray-300 rounded-xl px-4 py-3.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition pr-12 @error('password') border-red-400 @enderror"
                    >
                    <button type="button" onclick="togglePassword('password', this)" aria-label="Show or hide the password"
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

            {{-- Remember + Forgot --}}
            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="remember" class="w-4 h-4 rounded border-gray-300 text-green-600 focus:ring-green-500">
                    <span class="text-sm text-gray-600">Remember Me</span>
                </label>
                <a href="{{ route('forgot-password') }}" data-forgot-link
                   class="text-sm text-green-700 font-medium hover:text-green-900 transition-colors">
                    Forgot Password?
                </a>
            </div>

            {{-- Submit --}}
            <div class="pt-4">
                <button type="submit"
                        class="w-full bg-green-800 text-white font-semibold text-[15px] py-4 px-6 rounded-full hover:bg-green-900 focus:bg-green-900 transition-colors">
                    Login as Adviser
                </button>
            </div>
        </form>

        <x-google-sign-in as="adviser" />

        <p class="text-center text-sm text-gray-500 mt-6">
            New adviser?
            <a href="{{ route('register.adviser') }}" class="text-green-700 font-semibold hover:text-green-900 transition-colors">Create an adviser account</a>
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

// "Remember Me" applies to Google sign-in too, carried on the link as it is tapped.
document.querySelector('[data-google-sign-in]')?.addEventListener('click', function () {
    const url = new URL(this.href);
    url.searchParams.delete('remember');
    if (document.querySelector('#login-form [name="remember"]')?.checked) url.searchParams.set('remember', '1');
    this.href = url.toString();
});

// Carry a typed email over to Forgot Password, as on the student sign-in.
document.querySelector('[data-forgot-link]')?.addEventListener('click', function () {
    const email = document.getElementById('email')?.value.trim();
    const url = new URL(this.href);
    url.searchParams.delete('email');
    if (email && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) url.searchParams.set('email', email);
    this.href = url.toString();
});

// Offline, the server cannot say what went wrong, so check before signing in (as on
// the student sign-in): a HEAD request bypasses the service worker's cache.
(function () {
    const form = document.getElementById('login-form');
    if (!form) return;

    form.addEventListener('submit', async function (e) {
        if (form.dataset.reachable) { delete form.dataset.reachable; return; }

        e.preventDefault();

        if (navigator.onLine && await reach('/up')) {
            form.dataset.reachable = '1';
            form.requestSubmit();
            return;
        }

        window.ClubSyncLoading?.hide();
        window.csFlash('error', 'No internet connection',
            "ClubSync can't sign you in while you're offline. Check your Wi-Fi or mobile data, then try again.");
    });

    async function reach(url) {
        const abort = new AbortController();
        const timer = setTimeout(() => abort.abort(), 6000);
        try {
            await fetch(url + '?t=' + Date.now(), { method: 'HEAD', cache: 'no-store', signal: abort.signal });
            return true;
        } catch (_) {
            return false;
        } finally {
            clearTimeout(timer);
        }
    }
})();
</script>
@endsection
