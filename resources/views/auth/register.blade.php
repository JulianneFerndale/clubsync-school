@extends('layouts.auth')
@section('title', 'Register — ClubSync')

@section('content')
<div class="flex flex-col min-h-screen">
    @if ($errors->has('terms'))
        <div class="bg-red-500 text-white text-xs px-5 py-3 leading-relaxed">
            <div class="flex items-start gap-2">
                <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd"/>
                </svg>
                <span>We're sorry, but you need to agree to our Terms and Conditions to use ClubSync. This ensures your data is protected and the platform remains secure and reliable for all users.</span>
            </div>
        </div>
    @endif

    <div class="flex-1 overflow-y-auto px-8 pt-10 pb-12">
        <x-register-steps :current="1" />

        <h1 class="text-2xl font-bold text-green-800 mb-7">Register New Account</h1>

        {{-- Registering with Google needs no password: GoogleController → auth/register-google. --}}
        <x-google-sign-in label="Register with Google" divider="" class="mb-2" />
        @if (app(\App\Services\GoogleSignIn::class)->isConfigured())
            <div class="flex items-center gap-3 my-6" aria-hidden="true">
                <span class="flex-1 h-px bg-gray-200"></span>
                <span class="text-xs text-gray-400">or register with a password</span>
                <span class="flex-1 h-px bg-gray-200"></span>
            </div>
        @endif

        @if ($errors->hasAny(['first_name','last_name','edp_number','year_level','email','password','mobile_number']))
            <div class="mb-5 bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-red-700 text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('register') }}" enctype="multipart/form-data" class="space-y-4" id="registerForm">
            @csrf

            {{-- Profile photo (optional) --}}
            <div class="flex flex-col items-center gap-2 pb-1">
                <label class="cursor-pointer">
                    <div id="photo-preview" class="w-20 h-20 rounded-full bg-green-50 border-2 border-dashed border-green-300 flex items-center justify-center overflow-hidden">
                        <svg class="w-7 h-7 text-green-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z"/>
                        </svg>
                    </div>
                    <input type="file" name="photo" accept="image/*" class="hidden" onchange="previewPhoto(this)">
                </label>
                <span class="text-xs text-gray-400">Add a profile photo (optional)</span>
                @error('photo')<p class="text-red-500 text-xs">{{ $message }}</p>@enderror
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">First Name</label>
                    <input type="text" name="first_name" value="{{ old('first_name', session('register.first_name')) }}"
                           placeholder="First Name"
                           class="w-full border border-gray-300 rounded-xl px-3 py-3 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition @error('first_name') border-red-400 @enderror">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Last Name</label>
                    <input type="text" name="last_name" value="{{ old('last_name', session('register.last_name')) }}"
                           placeholder="Last Name"
                           class="w-full border border-gray-300 rounded-xl px-3 py-3 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition @error('last_name') border-red-400 @enderror">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">EDP Number</label>
                    <input type="text" name="edp_number" value="{{ old('edp_number', session('register.edp_number')) }}"
                           placeholder="EDP Number"
                           class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition @error('edp_number') border-red-400 @enderror">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Year Level</label>
                    <select name="year_level"
                            class="w-full border border-gray-300 rounded-xl px-3 py-3 text-sm text-gray-900 bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition @error('year_level') border-red-400 @enderror">
                        <option value="" {{ old('year_level', session('register.year_level')) === null ? 'selected' : '' }}>Select…</option>
                        @foreach([1 => '1st Year', 2 => '2nd Year', 3 => '3rd Year', 4 => '4th Year'] as $value => $label)
                            <option value="{{ $value }}" {{ (string) old('year_level', session('register.year_level')) === (string) $value ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Institutional Email</label>
                <input type="email" name="email" id="email-field" value="{{ old('email', session('register.email')) }}"
                       placeholder="yourname@sccpag.edu.ph (SCC email)"
                       autocomplete="email"
                       class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition @error('email') border-red-400 @enderror">
                <p id="email-status" class="hidden text-xs mt-1"></p>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Password <span class="font-normal text-gray-400">— at least 8 characters, with an uppercase letter, a lowercase letter and a number</span></label>
                <div class="relative">
                    <input type="password" name="password" id="password"
                           placeholder="••••••••••"
                           autocomplete="new-password"
                           class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition pr-11 @error('password') border-red-400 @enderror">
                    <button type="button" onclick="togglePassword('password', this)"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
                        <svg data-show-when="hidden" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                        </svg>
                        <svg data-show-when="visible" class="w-4 h-4 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Confirm Password</label>
                <div class="relative">
                    <input type="password" name="password_confirmation" id="password_confirmation"
                           placeholder="••••••••••"
                           autocomplete="new-password"
                           class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition pr-11">
                    <button type="button" onclick="togglePassword('password_confirmation', this)"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
                        <svg data-show-when="hidden" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"/>
                        </svg>
                        <svg data-show-when="visible" class="w-4 h-4 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Mobile Number</label>
                <input type="tel" name="mobile_number" value="{{ old('mobile_number', session('register.mobile_number')) }}"
                       placeholder="09XX XXXX XXXX"
                       class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition @error('mobile_number') border-red-400 @enderror">
            </div>

            {{-- Terms checkbox --}}
            <label class="flex items-start gap-3 cursor-pointer pt-1">
                <input type="checkbox" name="terms" id="termsCheckbox"
                       class="w-4 h-4 mt-0.5 rounded border-gray-300 text-green-600 focus:ring-green-500 flex-shrink-0"
                       onchange="updateRegisterBtn(this)">
                <span class="text-xs text-gray-600 leading-snug">
                    I have read and agree to the
                    <a href="{{ route('terms') }}" class="text-green-700 underline hover:text-green-900">Terms and Conditions</a>
                </span>
            </label>

            {{-- Register button --}}
            <div class="pt-2">
                <button type="submit" id="registerBtn"
                        class="flex items-center justify-between w-full bg-gray-300 text-white font-semibold text-[15px] py-4 px-6 rounded-full transition-colors"
                        disabled>
                    Register
                    <span class="w-8 h-8 rounded-full bg-yellow-400 flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-green-900" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/>
                        </svg>
                    </span>
                </button>
            </div>
        </form>

        <p class="text-center text-sm text-gray-500 mt-5">
            Already have an account?
            <a href="{{ route('login') }}" class="text-green-700 font-semibold hover:text-green-900 transition-colors">Login</a>
        </p>
    </div>
</div>

<script>
function updateRegisterBtn(checkbox) {
    const btn = document.getElementById('registerBtn');
    if (checkbox.checked) {
        btn.disabled = false;
        btn.classList.remove('bg-gray-300');
        btn.classList.add('bg-green-800', 'hover:bg-green-900');
    } else {
        btn.disabled = true;
        btn.classList.add('bg-gray-300');
        btn.classList.remove('bg-green-800', 'hover:bg-green-900');
    }
}

function previewPhoto(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('photo-preview').innerHTML =
                `<img src="${e.target.result}" class="w-full h-full object-cover">`;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function togglePassword(id, btn) {
    const input = document.getElementById(id);
    const isHidden = input.type === 'password';
    input.type = isHidden ? 'text' : 'password';
    btn.querySelectorAll('svg').forEach(svg => {
        const showWhen = svg.dataset.showWhen;
        svg.classList.toggle('hidden', showWhen === 'hidden' ? !isHidden : isHidden);
    });
}

document.addEventListener('DOMContentLoaded', function () {
    const cb = document.getElementById('termsCheckbox');
    if (cb && cb.checked) updateRegisterBtn(cb);

    // Flag an address we already hold an account for, as soon as it is typed, rather
    // than letting someone pick a department and a course first. This reads the
    // ClubSync side only; whether Firebase already holds the address is settled when
    // the form is submitted, so saying nothing here is not a promise that it is free.
    const email  = document.getElementById('email-field');
    const status = document.getElementById('email-status');
    if (!email || !status) return;

    const show = (message, tone) => {
        if (!message) { status.classList.add('hidden'); status.textContent = ''; return; }
        status.textContent = message;
        status.className = 'text-xs mt-1 ' + (tone === 'bad' ? 'text-red-500' : 'text-gray-500');
        status.classList.remove('hidden');
    };

    const mark = (bad) => {
        email.classList.toggle('border-red-400', bad);
        email.classList.toggle('border-gray-300', !bad);
    };

    let last = null;

    const check = async () => {
        const value = email.value.trim();

        if (!value || value === last) return;
        last = value;

        try {
            const res = await fetch('{{ route('register.check-email') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ email: value }),
            });

            if (!res.ok) return;   // throttled or offline — the submit still checks

            const data = await res.json();
            show(data.message, 'bad');
            mark(data.status === 'taken' || data.status === 'invalid');
        } catch (e) {
            // Offline: silence here, and the submit is still authoritative.
        }
    };

    email.addEventListener('blur', check);
    email.addEventListener('input', () => { show(null); mark(false); });
});
</script>
@endsection
