@extends('layouts.app-admin')

@php
    $editing = $memo->exists;
    $input = 'w-full border border-gray-300 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30';
@endphp

@section('title', ($editing ? 'Edit' : 'Add') . ' CHED Memorandum — Admin')
@section('page-title', $editing ? 'Edit CHED Memorandum' : 'Add CHED Memorandum')

@section('content')

<div class="bg-[#07873e] px-5 pt-5 pb-6">
    <a href="{{ route('admin.ched-memos.index') }}" class="text-white/60 text-xs hover:text-white">← CHED Memorandums</a>
    <h1 class="text-white font-bold text-xl mt-1">{{ $editing ? $memo->citation : 'Add CHED Memorandum' }}</h1>
    <p class="text-white/60 text-xs mt-1">{{ $editing ? 'Correct its details, change its status or replace the PDF.' : 'Record the issuance and attach the signed PDF.' }}</p>
</div>

<div class="px-4 py-5 max-w-2xl">

    <form method="POST" enctype="multipart/form-data"
          action="{{ $editing ? route('admin.ched-memos.update', $memo) : route('admin.ched-memos.store') }}"
          class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 space-y-4">
        @csrf
        @if($editing) @method('PATCH') @endif

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="sm:col-span-3">
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Type <span class="text-red-500">*</span></label>
                <select name="type" required class="{{ $input }} bg-white @error('type') border-red-400 @enderror">
                    @foreach(\App\Models\ChedMemorandum::TYPES as $key => $label)
                        <option value="{{ $key }}" {{ old('type', $memo->type) === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                @error('type')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Number <span class="text-red-500">*</span></label>
                <input type="text" name="memo_number" value="{{ old('memo_number', $memo->memo_number) }}" required maxlength="20"
                       placeholder="e.g. 09" class="{{ $input }} @error('memo_number') border-red-400 @enderror">
                @error('memo_number')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Series (year) <span class="text-red-500">*</span></label>
                <input type="number" name="series_year" value="{{ old('series_year', $memo->series_year) }}" required
                       min="1994" max="{{ now()->year + 1 }}" placeholder="{{ now()->year }}"
                       class="{{ $input }} @error('series_year') border-red-400 @enderror">
                @error('series_year')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Status <span class="text-red-500">*</span></label>
                <select name="status" required class="{{ $input }} bg-white @error('status') border-red-400 @enderror">
                    @foreach(\App\Models\ChedMemorandum::STATUSES as $key => $label)
                        <option value="{{ $key }}" {{ old('status', $memo->status) === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
                @error('status')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Title / Subject <span class="text-red-500">*</span></label>
            <input type="text" name="title" value="{{ old('title', $memo->title) }}" required maxlength="255"
                   placeholder="e.g. Enhanced Policies and Guidelines on Student Affairs and Services"
                   class="{{ $input }} @error('title') border-red-400 @enderror">
            @error('title')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Summary</label>
            <textarea name="summary" rows="4" maxlength="5000"
                      placeholder="What it requires of SCC and its clubs, in a few lines."
                      class="{{ $input }} @error('summary') border-red-400 @enderror">{{ old('summary', $memo->summary) }}</textarea>
            @error('summary')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Date issued <span class="text-red-500">*</span></label>
                <input type="date" name="date_issued" value="{{ old('date_issued', $memo->date_issued?->format('Y-m-d')) }}" required
                       max="{{ today()->format('Y-m-d') }}" class="{{ $input }} @error('date_issued') border-red-400 @enderror">
                @error('date_issued')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Effective date</label>
                <input type="date" name="effective_date" value="{{ old('effective_date', $memo->effective_date?->format('Y-m-d')) }}"
                       class="{{ $input }} @error('effective_date') border-red-400 @enderror">
                @error('effective_date')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Link on the CHED website</label>
            <input type="url" name="source_url" value="{{ old('source_url', $memo->source_url) }}" maxlength="255"
                   placeholder="https://ched.gov.ph/…" class="{{ $input }} @error('source_url') border-red-400 @enderror">
            @error('source_url')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                Memorandum PDF @unless($editing)<span class="text-red-500">*</span>@endunless
            </label>
            @if($editing)
                <p class="text-xs text-gray-500 mb-1.5">
                    Current file:
                    <a href="{{ route('resources.memos.download', $memo) }}" data-turbo="false" class="text-[#07873e] font-semibold underline">{{ $memo->file_name }}</a>.
                    Choose a new file only to replace it.
                </p>
            @endif
            <input type="file" name="file" accept="application/pdf,.pdf" {{ $editing ? '' : 'required' }}
                   class="block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-[#07873e]/10 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-[#07873e] @error('file') border border-red-400 rounded-lg @enderror">
            <p class="text-xs text-gray-400 mt-1">PDF, up to 20 MB.</p>
            @error('file')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" class="bg-[#07873e] text-white text-sm font-semibold rounded-xl px-5 py-3 hover:opacity-90 transition-opacity">
                {{ $editing ? 'Save Changes' : 'Add to Library' }}
            </button>
            <a href="{{ route('admin.ched-memos.index') }}" class="text-sm text-gray-500 font-medium hover:text-gray-700">Cancel</a>
        </div>
    </form>

</div>
@endsection
