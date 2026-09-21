@extends($layout)

@section('title', 'My Profile — ClubSync')
@section('page-title', 'My Profile')
@section('club-name', 'ClubSync')

@section('content')
<div class="max-w-lg mx-auto px-4 py-8 space-y-6">

    {{-- Success / Error flash --}}
    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 text-sm rounded-xl px-4 py-3">
            {{ session('success') }}
        </div>
    @endif
    @if($errors->any())
        <div class="bg-red-50 border border-red-200 text-red-800 text-sm rounded-xl px-4 py-3">
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Profile card --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

        {{-- Header band --}}
        <div class="bg-[#07873e] h-24 relative"></div>

        {{-- Avatar --}}
        <div class="px-6 pb-6">
            <div class="-mt-12 mb-4 flex items-end gap-4">
                <div class="relative">
                    @if($user->profile_photo_url)
                        <img src="{{ $user->profile_photo_url }}"
                             alt="{{ $user->first_name }}"
                             class="w-24 h-24 rounded-full object-cover border-4 border-white shadow-md">
                    @else
                        <div class="w-24 h-24 rounded-full bg-[#07873e] border-4 border-white shadow-md flex items-center justify-center">
                            <span class="text-white text-3xl font-bold">
                                {{ strtoupper(substr($user->first_name ?? 'U', 0, 1)) }}
                            </span>
                        </div>
                    @endif
                </div>
                <div class="pb-1">
                    <p class="font-bold text-gray-900 text-lg leading-tight">{{ $user->first_name }} {{ $user->last_name }}</p>
                    <p class="text-gray-500 text-sm">{{ $user->email }}</p>
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

    {{-- Change password --}}
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <h2 class="font-bold text-gray-900 mb-1">Change Password</h2>
        <p class="text-xs text-gray-500 mb-4">
            At least 8 characters, with an uppercase letter, a number, and a special character.
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
</script>
@endsection
