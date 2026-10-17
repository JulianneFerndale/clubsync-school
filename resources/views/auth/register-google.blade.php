@extends('layouts.auth')
@section('title', 'Register with Google — ClubSync')

@section('content')
{{--
    Step 1 for a student registering with their SCC Google account. Name and address
    come from Google and no password is set — they sign in with Google — so only the
    EDP number, year level and mobile number are asked for. Department, course and
    proof of enrollment follow as for every registration.
--}}
<div class="flex flex-col min-h-screen">
    @if ($errors->has('terms'))
        <div class="bg-red-500 text-white text-xs px-5 py-3 leading-relaxed">
            We're sorry, but you need to agree to our Terms and Conditions to use ClubSync. This ensures your data is protected and the platform remains secure and reliable for all users.
        </div>
    @endif

    <div class="flex-1 overflow-y-auto px-8 pt-10 pb-12">
        <x-register-steps :current="1" />

        <h1 class="text-2xl font-bold text-green-800 mb-2">Register with Google</h1>
        <p class="text-sm text-gray-500 mb-6">You'll sign in with this Google account, so there's no password to set.</p>

        @php($askName = blank($google['first_name']) || blank($google['last_name']))

        {{-- The Google account in use --}}
        <div class="flex items-center gap-3 border border-gray-200 rounded-2xl px-4 py-3 mb-6">
            <div class="w-11 h-11 rounded-full bg-green-100 text-green-800 font-bold flex items-center justify-center flex-shrink-0">
                {{ strtoupper(mb_substr($google['first_name'] ?: $google['email'], 0, 1)) }}
            </div>
            <div class="min-w-0">
                @unless ($askName)
                    <p class="font-semibold text-gray-900 truncate">{{ $google['first_name'] }} {{ $google['last_name'] }}</p>
                @endunless
                <p class="text-sm text-gray-500 truncate">{{ $google['email'] }}</p>
            </div>
            <svg class="w-5 h-5 ml-auto flex-shrink-0" viewBox="0 0 48 48" aria-label="Google account">
                <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
            </svg>
        </div>

        <form method="POST" action="{{ route('register') }}" class="space-y-4" id="registerForm">
            @csrf

            @if ($askName)
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">First Name</label>
                        <input type="text" name="first_name" value="{{ old('first_name') }}" placeholder="First Name"
                               class="w-full border border-gray-300 rounded-xl px-3 py-3 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition @error('first_name') border-red-400 @enderror">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Last Name</label>
                        <input type="text" name="last_name" value="{{ old('last_name') }}" placeholder="Last Name"
                               class="w-full border border-gray-300 rounded-xl px-3 py-3 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition @error('last_name') border-red-400 @enderror">
                    </div>
                </div>
            @endif

            <div>
                <label for="edp_number" class="block text-xs font-medium text-gray-600 mb-1">EDP Number</label>
                <input type="text" name="edp_number" id="edp_number" value="{{ old('edp_number', session('register.edp_number')) }}"
                       placeholder="EDP Number" inputmode="numeric" autofocus
                       class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition @error('edp_number') border-red-400 @enderror">
            </div>

            <div>
                <label for="year_level" class="block text-xs font-medium text-gray-600 mb-1">Year Level</label>
                <select name="year_level" id="year_level"
                        class="w-full border border-gray-300 rounded-xl px-3 py-3 text-sm text-gray-900 bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition @error('year_level') border-red-400 @enderror">
                    <option value="" {{ old('year_level', session('register.year_level')) === null ? 'selected' : '' }}>Select…</option>
                    @foreach([1 => '1st Year', 2 => '2nd Year', 3 => '3rd Year', 4 => '4th Year'] as $value => $label)
                        <option value="{{ $value }}" {{ (string) old('year_level', session('register.year_level')) === (string) $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="mobile_number" class="block text-xs font-medium text-gray-600 mb-1">Mobile Number</label>
                <input type="tel" name="mobile_number" id="mobile_number" value="{{ old('mobile_number', session('register.mobile_number')) }}"
                       placeholder="09XX XXXX XXXX" autocomplete="tel"
                       class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition @error('mobile_number') border-red-400 @enderror">
            </div>

            <p class="text-xs text-gray-400">Next you'll pick your department and course, then show your Certificate of Enrollment or SCC ID.</p>

            <label class="flex items-start gap-3 cursor-pointer pt-1">
                <input type="checkbox" name="terms" id="termsCheckbox" {{ old('terms') ? 'checked' : '' }}
                       class="w-4 h-4 mt-0.5 rounded border-gray-300 text-green-600 focus:ring-green-500 flex-shrink-0">
                <span class="text-xs text-gray-600 leading-snug">
                    I have read and agree to the
                    <a href="{{ route('terms') }}" class="text-green-700 underline hover:text-green-900">Terms and Conditions</a>
                </span>
            </label>

            <div class="pt-2">
                <button type="submit" id="registerBtn" disabled
                        class="flex items-center justify-between w-full bg-gray-300 text-white font-semibold text-[15px] py-4 px-6 rounded-full transition-colors">
                    Continue
                    <span class="w-8 h-8 rounded-full bg-yellow-400 flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-green-900" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/>
                        </svg>
                    </span>
                </button>
            </div>
        </form>

        <div class="text-center text-sm text-gray-500 mt-6 space-y-2">
            <p>
                Not you?
                <a href="{{ route('auth.google') }}" data-turbo="false" class="text-green-700 font-semibold hover:text-green-900">Use a different Google account</a>
            </p>
            <form method="POST" action="{{ route('register.google.cancel') }}" data-turbo="false">
                @csrf
                <button type="submit" class="text-green-700 font-semibold hover:text-green-900">Register with email and password instead</button>
            </form>
        </div>
    </div>
</div>

<script>
// Continue unlocks once the Terms are accepted, as on the password form.
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
