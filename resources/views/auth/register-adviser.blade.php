@extends('layouts.auth')
@section('title', 'Adviser Sign-up — ClubSync')

@section('content')
{{--
    Adviser sign-up (POLICY.md §Registration). Advisers are employees: adviser number,
    full name, SCC email and password — or their SCC Google account in place of the email
    and password. No department, course or proof of enrollment. The adviser picks the club
    they advise from those with no adviser; an administrator confirms it.
    $google: the SCC Google account chosen, or null. $clubs: the clubs with no adviser.
--}}
@php
    $input = 'w-full border border-gray-300 rounded-xl px-4 py-3 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition';
    $askName = ! $google || blank($google['first_name']) || blank($google['last_name']);
@endphp
<div class="flex flex-col min-h-screen">
    @if ($errors->has('terms'))
        <div class="bg-red-500 text-white text-xs px-5 py-3 leading-relaxed">
            We're sorry, but you need to agree to our Terms and Conditions to use ClubSync. This ensures your data is protected and the platform remains secure and reliable for all users.
        </div>
    @endif

    {{-- Header --}}
    <div class="flex items-center gap-3 px-6 pt-14 pb-2">
        <a href="{{ route('login.adviser') }}" class="text-green-800 hover:text-green-900 transition-colors" aria-label="Back to adviser login">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
            </svg>
        </a>
        <span class="text-green-800 font-semibold text-base">Adviser Sign-up</span>
    </div>

    <div class="flex-1 overflow-y-auto px-8 pt-6 pb-12">
        <h1 class="text-2xl font-bold text-green-800 mb-2">Create an adviser account</h1>
        <p class="text-sm text-gray-500 mb-6">For SCC employees who advise a club. Students register on the <a href="{{ route('register') }}" class="text-green-700 font-semibold underline">student form</a>.</p>

        @if ($google)
            {{-- The Google account in use: no password to set, they sign in with Google --}}
            <div class="flex items-center gap-3 border border-gray-200 rounded-2xl px-4 py-3 mb-2">
                <div class="w-11 h-11 rounded-full bg-green-100 text-green-800 font-bold flex items-center justify-center flex-shrink-0">
                    {{ strtoupper(mb_substr($google['first_name'] ?: $google['email'], 0, 1)) }}
                </div>
                <div class="min-w-0">
                    @unless ($askName)
                        <p class="font-semibold text-gray-900 truncate">{{ $google['first_name'] }} {{ $google['last_name'] }}</p>
                    @endunless
                    <p class="text-sm text-gray-500 truncate">{{ $google['email'] }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('register.adviser.google.cancel') }}" class="mb-6">
                @csrf
                <button type="submit" class="text-xs text-green-700 font-semibold underline">Use an email and password instead</button>
            </form>
        @else
            <x-google-sign-in as="adviser" label="Sign up with Google" divider="" class="mb-2" />
            @if (app(\App\Services\GoogleSignIn::class)->isConfigured())
                <div class="flex items-center gap-3 my-6" aria-hidden="true">
                    <span class="flex-1 h-px bg-gray-200"></span>
                    <span class="text-xs text-gray-400">or sign up with a password</span>
                    <span class="flex-1 h-px bg-gray-200"></span>
                </div>
            @endif
        @endif

        @if ($errors->any() && ! $errors->has('terms'))
            <div class="mb-5 bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-red-700 text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('register.adviser') }}" data-turbo="false" class="space-y-4" id="registerForm"
              data-loading="splash" data-loading-message="Creating your account…">
            @csrf

            <div>
                <label for="employee_number" class="block text-xs font-medium text-gray-600 mb-1">Adviser Number</label>
                <input type="text" name="employee_number" id="employee_number" value="{{ old('employee_number') }}"
                       placeholder="Your SCC employee number" autocapitalize="characters"
                       class="{{ $input }} @error('employee_number') border-red-400 @enderror">
            </div>

            @if ($askName)
                <fieldset>
                    <legend class="block text-xs font-medium text-gray-600 mb-1">Full Name</legend>
                    <div class="grid grid-cols-2 gap-3">
                        <input type="text" name="first_name" value="{{ old('first_name') }}" placeholder="First Name" aria-label="First name" autocomplete="given-name"
                               class="{{ $input }} @error('first_name') border-red-400 @enderror">
                        <input type="text" name="last_name" value="{{ old('last_name') }}" placeholder="Last Name" aria-label="Last name" autocomplete="family-name"
                               class="{{ $input }} @error('last_name') border-red-400 @enderror">
                    </div>
                </fieldset>
            @endif

            @unless ($google)
                <div>
                    <label for="email" class="block text-xs font-medium text-gray-600 mb-1">Institutional Email</label>
                    <input type="email" name="email" id="email" value="{{ old('email') }}"
                           placeholder="yourname@sccpag.edu.ph (SCC email)" autocomplete="email"
                           class="{{ $input }} @error('email') border-red-400 @enderror">
                </div>

                <div>
                    <label for="password" class="block text-xs font-medium text-gray-600 mb-1">Password <span class="font-normal text-gray-400">— at least 8 characters, with an uppercase letter, a lowercase letter and a number</span></label>
                    <input type="password" name="password" id="password" placeholder="••••••••••" autocomplete="new-password"
                           class="{{ $input }} @error('password') border-red-400 @enderror">
                </div>

                <div>
                    <label for="password_confirmation" class="block text-xs font-medium text-gray-600 mb-1">Confirm Password</label>
                    <input type="password" name="password_confirmation" id="password_confirmation" placeholder="••••••••••" autocomplete="new-password"
                           class="{{ $input }}">
                </div>
            @endunless

            {{-- The club they advise: a request an administrator confirms --}}
            <div>
                <label for="club_id" class="block text-xs font-medium text-gray-600 mb-1">The Club You Advise</label>
                @if ($clubs->isEmpty())
                    <p class="text-xs text-gray-600 bg-gray-50 border border-gray-200 rounded-xl px-4 py-3">
                        Every club has an adviser right now. Create your account anyway; an administrator gives you your club when one is free.
                    </p>
                @else
                    <select name="club_id" id="club_id"
                            class="{{ $input }} bg-white @error('club_id') border-red-400 @enderror">
                        <option value="">Choose your club…</option>
                        @foreach ($clubs as $club)
                            <option value="{{ $club->id }}" @selected((string) old('club_id') === (string) $club->id)>{{ $club->name }}{{ $club->acronym ? ' (' . $club->acronym . ')' : '' }}</option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-gray-400 mt-1">Only clubs with no adviser are listed: a club has one adviser. An administrator confirms your club before its pages open to you.</p>
                @endif
            </div>

            {{-- Terms checkbox --}}
            <label class="flex items-start gap-3 cursor-pointer pt-1">
                <input type="checkbox" name="terms" id="termsCheckbox" @checked(old('terms'))
                       class="w-4 h-4 mt-0.5 rounded border-gray-300 text-green-600 focus:ring-green-500 flex-shrink-0">
                <span class="text-xs text-gray-600 leading-snug">
                    I have read and agree to the
                    <a href="{{ route('terms') }}" class="text-green-700 underline hover:text-green-900">Terms and Conditions</a>
                </span>
            </label>

            <div class="pt-2">
                <button type="submit" id="registerBtn" disabled
                        class="w-full bg-gray-300 text-white font-semibold text-[15px] py-4 px-6 rounded-full transition-colors">
                    Create Adviser Account
                </button>
            </div>
        </form>

        <p class="text-center text-sm text-gray-500 mt-5">
            Already have an adviser account?
            <a href="{{ route('login.adviser') }}" class="text-green-700 font-semibold hover:text-green-900 transition-colors">Login</a>
        </p>
    </div>
</div>

<script>
// The button unlocks once the Terms are accepted, as on the student form.
(function () {
    const box = document.getElementById('termsCheckbox');
    const btn = document.getElementById('registerBtn');
    if (!box || !btn) return;

    const sync = () => {
        btn.disabled = !box.checked;
        btn.classList.toggle('bg-gray-300', !box.checked);
        btn.classList.toggle('bg-green-800', box.checked);
        btn.classList.toggle('hover:bg-green-900', box.checked);
    };

    box.addEventListener('change', sync);
    sync();
})();
</script>
@endsection
