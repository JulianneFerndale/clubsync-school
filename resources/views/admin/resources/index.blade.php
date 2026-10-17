@extends('layouts.app-admin')
@section('title', 'Handbook & Templates — Admin')
@section('page-title', 'Handbook & Templates')

@php
    $fmt = fn ($bytes) => $bytes >= 1048576
        ? number_format($bytes / 1048576, 1) . ' MB'
        : number_format($bytes / 1024, 0) . ' KB';
    $input = 'w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30';
@endphp

@section('content')

<div class="bg-[#07873e] px-5 pt-5 pb-6">
    <h1 class="text-white font-bold text-xl">Handbook &amp; Templates</h1>
    <p class="text-white/60 text-xs mt-1">The Saint Columban College club handbook and the report templates clubs fill in. Everything here can be downloaded by every user from Resources.</p>
</div>

<div class="px-4 py-5 space-y-5 max-w-4xl">


    {{-- Upload --}}
    <form method="POST" action="{{ route('admin.resources.store') }}" enctype="multipart/form-data"
          class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 space-y-4">
        @csrf
        <p class="text-sm font-semibold text-gray-700">Upload a file</p>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Kind <span class="text-red-500">*</span></label>
                <select name="category" required class="{{ $input }} bg-white @error('category') border-red-400 @enderror">
                    @foreach($categories as $key => $label)
                        <option value="{{ $key }}" {{ old('category', 'template') === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                @error('category')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Title <span class="text-red-500">*</span></label>
                <input type="text" name="title" value="{{ old('title') }}" required maxlength="255"
                       placeholder="e.g. Accomplishment Report Template"
                       class="{{ $input }} @error('title') border-red-400 @enderror">
                @error('title')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Description</label>
            <textarea name="description" rows="2" maxlength="2000"
                      placeholder="When clubs use it and who it goes to."
                      class="{{ $input }} @error('description') border-red-400 @enderror">{{ old('description') }}</textarea>
            @error('description')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        <div>
            <input type="file" name="file" required accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx"
                   class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-[#07873e]/10 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-[#07873e]">
            <p class="text-xs text-gray-400 mt-1">PDF, Word, Excel or PowerPoint, up to 20 MB.</p>
            @error('file')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        <button type="submit" class="bg-[#07873e] text-white text-sm font-semibold rounded-xl px-5 py-3 hover:opacity-90 transition-opacity">
            Upload
        </button>
    </form>

    {{-- Files, by kind --}}
    @foreach($categories as $key => $label)
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
            <p class="text-xs font-semibold text-gray-400 uppercase tracking-widest px-4 pt-4 pb-1">{{ $label }}{{ $key === 'template' ? 's' : '' }}</p>
            <div class="divide-y divide-gray-50">
                @forelse($documents->get($key, collect()) as $document)
                    <div class="flex flex-col sm:flex-row sm:items-center gap-3 px-4 py-3">
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-800">{{ $document->title }}</p>
                            @if($document->description)
                                <p class="text-xs text-gray-500 mt-0.5">{{ $document->description }}</p>
                            @endif
                            <p class="text-[11px] text-gray-400 mt-0.5">
                                {{ $document->extension }} · {{ $fmt($document->file_size) }} · Updated {{ $document->updated_at->format('M j, Y') }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2 flex-shrink-0">
                            <a href="{{ route('resources.documents.download', $document) }}" data-turbo="false"
                               class="border border-[#07873e]/40 text-[#07873e] text-xs font-semibold rounded-lg px-3 py-1.5 hover:bg-[#07873e]/5 transition-colors">
                                Download
                            </a>
                            <a href="{{ route('admin.resources.edit', $document) }}"
                               class="border border-gray-300 text-gray-600 text-xs font-semibold rounded-lg px-3 py-1.5 hover:bg-gray-50 transition-colors">
                                Edit
                            </a>
                            <form method="POST" action="{{ route('admin.resources.destroy', $document) }}"
                                  onsubmit="return confirm('Remove this file? Users will no longer be able to download it.')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="border border-red-200 text-red-500 text-xs font-semibold rounded-lg px-3 py-1.5 hover:bg-red-50 transition-colors">
                                    Remove
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <p class="text-gray-400 text-sm px-4 py-4">Nothing uploaded yet.</p>
                @endforelse
            </div>
        </div>
    @endforeach

</div>
@endsection
