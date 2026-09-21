@extends('layouts.app-dsa')
@section('title', 'Edit Club — DSA')

@section('content')

{{-- Header --}}
<div class="flex items-center gap-3 bg-[#07873e] px-5 py-4">
    <a href="{{ route('dsa.clubs.show', $club) }}" class="text-white/70 hover:text-white transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
        </svg>
    </a>
    <h1 class="text-white font-bold text-xl">Edit Club</h1>
</div>

<div class="px-4 py-5">

    @if($errors->any())
        <div class="mb-4 bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-red-700 text-sm">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('dsa.clubs.update', $club) }}" enctype="multipart/form-data" class="space-y-4"
          data-loading="dialog" data-loading-message="Saving changes">
        @csrf
        @method('PATCH')

        {{-- Banner / cover upload --}}
        <div class="mb-2">
            <label class="block text-xs text-gray-400 font-medium mb-1.5">Cover / Banner</label>
            <label class="cursor-pointer block">
                <div id="banner-preview" class="h-28 w-full rounded-xl bg-[#07873e]/10 border-2 border-dashed border-[#07873e]/30 flex items-center justify-center overflow-hidden">
                    @if($club->banner_image)
                        <img src="{{ $club->banner_image }}" class="w-full h-full object-cover">
                    @else
                        <span class="text-xs text-[#07873e]/50">Tap to upload a banner (wide image)</span>
                    @endif
                </div>
                <input type="file" name="banner" accept="image/*" class="hidden" onchange="previewBanner(this)">
            </label>
        </div>

        {{-- Logo upload --}}
        <div class="flex flex-col items-center gap-3 py-4">
            <div id="logo-preview"
                 class="w-20 h-20 rounded-full bg-[#07873e]/10 border-2 border-dashed border-[#07873e]/30 flex items-center justify-center overflow-hidden">
                @if($club->profile_photo_url)
                    <img src="{{ $club->profile_photo_url }}" class="w-full h-full object-cover">
                @else
                    <svg class="w-8 h-8 text-[#07873e]/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z"/>
                    </svg>
                @endif
            </div>
            <label class="cursor-pointer text-sm text-[#07873e] font-semibold">
                Change Logo
                <input type="file" name="logo" accept="image/*" class="hidden" id="logo-input"
                       onchange="previewLogo(this)">
            </label>
            <p class="text-xs text-gray-400">PNG, JPG up to 2MB</p>
        </div>

        {{-- Club name --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Club Name <span class="text-red-500">*</span></label>
            <input type="text" name="name" value="{{ old('name', $club->name) }}" required
                   placeholder="e.g. Computer Science Society"
                   class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30 @error('name') border-red-400 @enderror">
            @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        {{-- Acronym --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Acronym <span class="text-red-500">*</span></label>
            <input type="text" name="acronym" value="{{ old('acronym', $club->acronym) }}" required
                   placeholder="e.g. CSS"
                   class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30 @error('acronym') border-red-400 @enderror">
            @error('acronym')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        {{-- Type --}}
        @php $currentType = old('type', $club->type ?? ($club->club_type === 'Academic' ? 'academic' : 'non_academic')); @endphp
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Club Type <span class="text-red-500">*</span></label>
            <div class="grid grid-cols-2 gap-3">
                <label class="cursor-pointer">
                    <input type="radio" name="type" value="academic" class="sr-only peer"
                           {{ $currentType === 'academic' ? 'checked' : '' }}>
                    <div class="border-2 border-gray-200 rounded-xl px-4 py-3 text-sm font-semibold text-center text-gray-600
                                peer-checked:border-[#07873e] peer-checked:bg-[#07873e]/5 peer-checked:text-[#07873e] transition-all">
                        Academic
                    </div>
                </label>
                <label class="cursor-pointer">
                    <input type="radio" name="type" value="non_academic" class="sr-only peer"
                           {{ $currentType === 'non_academic' ? 'checked' : '' }}>
                    <div class="border-2 border-gray-200 rounded-xl px-4 py-3 text-sm font-semibold text-center text-gray-600
                                peer-checked:border-[#07873e] peer-checked:bg-[#07873e]/5 peer-checked:text-[#07873e] transition-all">
                        Non-Academic
                    </div>
                </label>
            </div>
            @error('type')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        {{-- College --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">College</label>
            <select name="college_id"
                    class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30 bg-white">
                <option value="">— None —</option>
                @foreach($colleges as $college)
                    <option value="{{ $college->id }}" {{ (string) old('college_id', $club->college_id) === (string) $college->id ? 'selected' : '' }}>
                        {{ $college->name }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Assign adviser --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Assign Adviser</label>
            <select name="adviser_id"
                    class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30 bg-white">
                <option value="">— None —</option>
                @foreach($advisers as $adviser)
                    <option value="{{ $adviser->id }}" {{ (string) old('adviser_id', $club->adviser_id) === (string) $adviser->id ? 'selected' : '' }}>
                        {{ $adviser->name }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Description --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Description</label>
            <textarea name="description" rows="4"
                      placeholder="Brief description of the club's purpose and activities..."
                      class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30 resize-none">{{ old('description', $club->description) }}</textarea>
        </div>

        {{-- Facebook page --}}
        <div>
            <label for="facebook_url" class="block text-sm font-medium text-gray-700 mb-1.5">Facebook Page</label>
            <input type="url" id="facebook_url" name="facebook_url" maxlength="255"
                   value="{{ old('facebook_url', $club->facebook_url) }}"
                   placeholder="https://www.facebook.com/…"
                   class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30 @error('facebook_url') border-red-400 @enderror">
            @error('facebook_url')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @else
                <p class="text-gray-400 text-xs mt-1">Shown to every user as a Facebook button on the club page. Leave blank if the club has no page.</p>
            @enderror
        </div>

        {{-- Active status --}}
        @php $isActive = (bool) old('is_active', $club->is_active); @endphp
        <label class="flex items-center justify-between border border-gray-200 rounded-xl px-4 py-3 cursor-pointer">
            <span class="text-sm font-medium text-gray-700">Club is active</span>
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" {{ $isActive ? 'checked' : '' }}
                   class="w-5 h-5 rounded border-gray-300 text-[#07873e] focus:ring-[#07873e]/40">
        </label>

        {{-- Buttons --}}
        <div class="grid grid-cols-2 gap-3 pt-2">
            <a href="{{ route('dsa.clubs.show', $club) }}"
               class="flex items-center justify-center border-2 border-[#07873e] text-[#07873e] font-semibold text-sm rounded-xl py-3.5 hover:bg-[#07873e]/5 transition-colors">
                Cancel
            </a>
            <button type="submit"
                    class="bg-[#07873e] text-[#F9A825] font-semibold text-sm rounded-xl py-3.5 hover:opacity-90 transition-opacity">
                Save Changes
            </button>
        </div>

    </form>
</div>

<script>
function previewBanner(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('banner-preview').innerHTML = `<img src="${e.target.result}" class="w-full h-full object-cover">`;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function previewLogo(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            document.getElementById('logo-preview').innerHTML = `<img src="${e.target.result}" class="w-full h-full object-cover">`;
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

@endsection
