@extends('layouts.app-admin')
@section('title', 'Edit File — Admin')
@section('page-title', 'Edit File')

@php
    $input = 'w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30';
@endphp

@section('content')

<div class="bg-[#07873e] px-5 pt-5 pb-6">
    <a href="{{ route('admin.resources.index') }}" class="text-white/60 text-xs hover:text-white">← Handbook &amp; Templates</a>
    <h1 class="text-white font-bold text-xl mt-1">{{ $document->title }}</h1>
    <p class="text-white/60 text-xs mt-1">Correct its details or upload a newer version.</p>
</div>

<div class="px-4 py-5 max-w-2xl">

    <form method="POST" action="{{ route('admin.resources.update', $document) }}" enctype="multipart/form-data"
          class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 space-y-4">
        @csrf
        @method('PATCH')

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Kind <span class="text-red-500">*</span></label>
            <select name="category" required class="{{ $input }} bg-white @error('category') border-red-400 @enderror">
                @foreach($categories as $key => $label)
                    <option value="{{ $key }}" {{ old('category', $document->category) === $key ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            @error('category')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Title <span class="text-red-500">*</span></label>
            <input type="text" name="title" value="{{ old('title', $document->title) }}" required maxlength="255"
                   class="{{ $input }} @error('title') border-red-400 @enderror">
            @error('title')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Description</label>
            <textarea name="description" rows="3" maxlength="2000"
                      class="{{ $input }} @error('description') border-red-400 @enderror">{{ old('description', $document->description) }}</textarea>
            @error('description')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">File</label>
            <p class="text-xs text-gray-500 mb-1.5">
                Current file:
                <a href="{{ route('resources.documents.download', $document) }}" data-turbo="false" class="text-[#07873e] font-semibold underline">{{ $document->file_name }}</a>.
                Choose a new file only to replace it.
            </p>
            <input type="file" name="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx"
                   class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-[#07873e]/10 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-[#07873e]">
            <p class="text-xs text-gray-400 mt-1">PDF, Word, Excel or PowerPoint, up to 20 MB.</p>
            @error('file')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="bg-[#07873e] text-white text-sm font-semibold rounded-xl px-5 py-3 hover:opacity-90 transition-opacity">
                Save Changes
            </button>
            <a href="{{ route('admin.resources.index') }}" class="text-sm text-gray-500 font-medium hover:text-gray-700">Cancel</a>
        </div>
    </form>

</div>
@endsection
