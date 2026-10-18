@extends($layout)

@section('title', 'My Profile — ClubSync')
@section('page-title', 'My Profile')
@section('club-name', 'ClubSync')

@section('content')
<div class="max-w-lg mx-auto px-4 py-8 space-y-6">

    {{-- Profile card --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

        {{-- Avatar --}}
        <div class="p-6">
            <div class="mb-5 flex items-center gap-4">
                <div class="relative flex-shrink-0">
                    @if($user->profile_photo_url)
                        <img src="{{ $user->profile_photo_url }}"
                             alt="{{ $user->first_name }}"
                             class="w-20 h-20 rounded-full object-cover ring-4 ring-[#07873e]/10">
                    @else
                        <div class="w-20 h-20 rounded-full bg-[#07873e] ring-4 ring-[#07873e]/10 flex items-center justify-center">
                            <span class="text-white text-2xl font-bold">
                                {{ strtoupper(substr($user->first_name ?? 'U', 0, 1)) }}
                            </span>
                        </div>
                    @endif
                </div>
                <div class="min-w-0">
                    <p class="font-bold text-gray-900 text-lg leading-tight">{{ $user->first_name }} {{ $user->last_name }}</p>
                    <p class="text-gray-500 text-sm truncate">{{ $user->email }}</p>
                </div>
            </div>

            {{-- User info --}}
            <dl class="space-y-3 text-sm mb-6">
                @if($user->course)
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Course</dt>
                        <dd class="text-gray-800 font-medium text-right max-w-[60%]">{{ $user->course->name }}</dd>
                    </div>
                @endif
                @if($user->department)
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Department</dt>
                        <dd class="text-gray-800 font-medium">{{ $user->department->short_name }}</dd>
                    </div>
                @endif
                @if($user->edp_number)
                    <div class="flex justify-between">
                        <dt class="text-gray-500">EDP No.</dt>
                        <dd class="text-gray-800 font-medium">{{ $user->edp_number }}</dd>
                    </div>
                @endif
                @if($user->mobile_number)
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Mobile</dt>
                        <dd class="text-gray-800 font-medium">{{ $user->mobile_number }}</dd>
                    </div>
                @endif
            </dl>

            {{-- Upload form --}}
            <form method="POST" action="{{ route('profile.photo') }}" enctype="multipart/form-data" id="photo-form">
                @csrf
                <label class="block text-sm font-medium text-gray-700 mb-2">Profile Photo</label>

                {{-- Drop zone / preview --}}
                <label for="photo-input"
                       class="flex flex-col items-center justify-center w-full h-36 border-2 border-dashed border-gray-300 rounded-xl cursor-pointer hover:border-green-500 hover:bg-green-50 transition-colors relative overflow-hidden"
                       id="drop-zone">
                    <img id="photo-preview" src="" alt="" class="absolute inset-0 w-full h-full object-cover hidden rounded-xl">
                    <div id="drop-hint" class="flex flex-col items-center gap-2 z-10">
                        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5"/>
                        </svg>
                        <span class="text-sm text-gray-500">Tap to choose a photo</span>
                        <span class="text-xs text-gray-400">JPG, PNG, WEBP · max 2 MB</span>
                    </div>
                    <input type="file" id="photo-input" name="photo" accept="image/*" class="hidden">
                </label>

                <button type="submit"
                        id="upload-btn"
                        class="mt-3 w-full bg-[#07873e] text-white font-semibold py-3 rounded-full hover:bg-green-900 transition-colors disabled:opacity-40"
                        disabled>
                    Upload Photo
                </button>
            </form>

            {{-- Remove photo --}}
            @if($user->profile_photo_url)
                <form method="POST" action="{{ route('profile.photo.delete') }}" class="mt-3"
                      onsubmit="return confirm('Remove your profile photo?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="w-full border border-red-200 text-red-600 font-medium py-3 rounded-full hover:bg-red-50 transition-colors text-sm">
                        Remove Photo
                    </button>
                </form>
            @endif
        </div>
    </div>

    {{-- Signature for room booking letters (officers, advisers, the VPA) --}}
    @if($user->signsLetters())
    <div id="signature" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 scroll-mt-20">
        <h2 class="font-bold text-gray-900 mb-1">Signature <span class="text-xs text-gray-400 font-normal">(optional)</span></h2>
        <p class="text-xs text-gray-500 mb-4">
            Room booking letters are signed with it:
            @if($user->role === 'vpa')
                under "Approved by" when you approve one.
            @elseif($user->role === 'adviser')
                under "Noted by" when you confirm an activity.
            @else
                above your name when you send one to the VPA.
            @endif
            Without one, a letter says it was signed electronically. Only you see it here; others see it only on letters you have signed.
        </p>

        <div class="border border-gray-200 rounded-xl h-28 flex items-center justify-center bg-gray-50 mb-4">
            @if($user->signature_path)
                <img src="{{ route('profile.signature.show') }}?v={{ substr(md5($user->signature_path), 0, 8) }}" alt="Your signature" class="max-h-24 max-w-full">
            @else
                <span class="text-xs text-gray-400">No signature on file</span>
            @endif
        </div>

        <form method="POST" action="{{ route('profile.signature') }}" enctype="multipart/form-data" id="signature-form" class="space-y-3">
            @csrf
            <input type="hidden" name="signature_drawn" id="signature-drawn">

            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="text-sm font-medium text-gray-700">Draw it here</label>
                    <button type="button" id="signature-clear" class="text-xs text-gray-500 hover:text-gray-700 underline">Clear</button>
                </div>
                <canvas id="signature-pad" class="w-full h-36 border-2 border-dashed border-gray-300 rounded-xl bg-white touch-none cursor-crosshair"></canvas>
            </div>

            <div>
                <label for="signature-file" class="text-sm font-medium text-gray-700 block mb-1.5">…or upload a photo of it</label>
                <input type="file" id="signature-file" name="signature_file" accept="image/jpeg,image/png,image/webp"
                       class="block w-full text-xs text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-[#07873e]/10 file:px-3 file:py-2 file:text-[#07873e] file:font-semibold">
                <p class="text-[11px] text-gray-400 mt-1">Sign in dark ink on plain white paper. The paper is removed so only the ink is kept. JPG, PNG or WebP, up to 4 MB.</p>
            </div>

            @error('signature')<p class="text-red-500 text-xs">{{ $message }}</p>@enderror
            @error('signature_file')<p class="text-red-500 text-xs">{{ $message }}</p>@enderror
            @error('signature_drawn')<p class="text-red-500 text-xs">{{ $message }}</p>@enderror
            <p id="signature-hint" class="hidden text-red-500 text-xs">Draw your signature above, or choose a photo of it.</p>

            <button type="submit"
                    class="w-full bg-[#07873e] text-white font-semibold py-3 rounded-full hover:bg-green-900 transition-colors">
                Save Signature
            </button>
        </form>

        @if($user->signature_path)
            <form method="POST" action="{{ route('profile.signature.delete') }}" class="mt-3"
                  onsubmit="return confirm('Remove your signature? Letters you already signed keep it.')">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="w-full border border-red-200 text-red-600 font-medium py-3 rounded-full hover:bg-red-50 transition-colors text-sm">
                    Remove Signature
                </button>
            </form>
        @endif
    </div>
    @endif

    {{-- Change password --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h2 class="font-bold text-gray-900 mb-1">Change Password</h2>

        @if($user->signsInWithGoogleOnly())
            {{-- Registered with Google and never set a password: nothing to change. --}}
            <p class="text-sm text-gray-600">
                You sign in with your SCC Google account ({{ $user->email }}), so there is no ClubSync password to change.
            </p>
        @else
        <p class="text-xs text-gray-500 mb-4">
            At least 8 characters, with an uppercase letter, a lowercase letter and a number.
            For your security, a password can only be changed once every {{ $user::PASSWORD_COOLDOWN_DAYS }} days.
        </p>

        @if(! $user->canChangePassword())
            <div class="bg-amber-50 border border-amber-200 text-amber-800 text-sm rounded-xl px-4 py-3">
                You changed your password on {{ $user->last_password_changed->format('F j, Y') }}.
                You can change it again on <span class="font-semibold">{{ $user->passwordChangeAvailableAt()->format('F j, Y') }}</span>.
            </div>
        @else
            <form method="POST" action="{{ route('profile.password.update') }}" class="space-y-3">
                @csrf
                <div>
                    <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                    <input type="password" id="current_password" name="current_password" required autocomplete="current-password"
                           class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30">
                </div>
                <div>
                    <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">New Password</label>
                    <input type="password" id="new_password" name="password" required autocomplete="new-password"
                           class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30">
                </div>
                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password"
                           class="w-full border border-gray-300 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30">
                </div>
                <button type="submit"
                        class="w-full bg-[#07873e] text-white font-semibold py-3 rounded-full hover:bg-green-900 transition-colors">
                    Update Password
                </button>
            </form>
        @endif
        @endif
    </div>
</div>

<script>
const input   = document.getElementById('photo-input');
const preview = document.getElementById('photo-preview');
const hint    = document.getElementById('drop-hint');
const btn     = document.getElementById('upload-btn');

input.addEventListener('change', () => {
    const file = input.files[0];
    if (!file) return;
    const url = URL.createObjectURL(file);
    preview.src = url;
    preview.classList.remove('hidden');
    hint.classList.add('hidden');
    btn.disabled = false;
});

// Signature pad: strokes drawn with a finger, pen or mouse, sent as a PNG.
(() => {
    const canvas = document.getElementById('signature-pad');
    const form   = document.getElementById('signature-form');
    if (! canvas || ! form) return;

    const ctx    = canvas.getContext('2d');
    const drawn  = document.getElementById('signature-drawn');
    const file   = document.getElementById('signature-file');
    const hintEl = document.getElementById('signature-hint');
    let inked = false;
    let last  = null;

    const fit = () => {
        const ratio = window.devicePixelRatio || 1;
        const box = canvas.getBoundingClientRect();
        canvas.width  = Math.round(box.width * ratio);
        canvas.height = Math.round(box.height * ratio);
        ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
        ctx.lineWidth = 2.5;
        ctx.lineCap = ctx.lineJoin = 'round';
        ctx.strokeStyle = '#111827';
        inked = false;
    };
    fit();

    const point = (e) => {
        const box = canvas.getBoundingClientRect();
        return { x: e.clientX - box.left, y: e.clientY - box.top };
    };

    canvas.addEventListener('pointerdown', (e) => {
        canvas.setPointerCapture(e.pointerId);
        last = point(e);
        ctx.beginPath();
        ctx.arc(last.x, last.y, 1.2, 0, Math.PI * 2);
        ctx.fillStyle = '#111827';
        ctx.fill();
        inked = true;
    });
    canvas.addEventListener('pointermove', (e) => {
        if (! last) return;
        const p = point(e);
        ctx.beginPath();
        ctx.moveTo(last.x, last.y);
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
        last = p;
    });
    ['pointerup', 'pointercancel', 'pointerleave'].forEach((type) => canvas.addEventListener(type, () => { last = null; }));

    document.getElementById('signature-clear')?.addEventListener('click', () => {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        inked = false;
    });

    form.addEventListener('submit', (e) => {
        if (file.files.length) {
            drawn.value = '';
            return;
        }
        if (! inked) {
            e.preventDefault();
            hintEl.classList.remove('hidden');
            return;
        }
        drawn.value = canvas.toDataURL('image/png');
    });
})();
</script>
@endsection
