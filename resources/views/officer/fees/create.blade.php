@extends('layouts.app-officer')
@section('title', 'Add Fee')
@section('club-name', $club?->name ?? 'ClubSync')

@section('content')

{{-- Header --}}
<div class="flex items-center gap-3 bg-[#07873e] px-5 py-4">
    <a href="{{ route('officer.fees.index') }}" class="text-white/70 hover:text-white transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
        </svg>
    </a>
    <h1 class="text-white font-bold text-xl">Add Fee</h1>
</div>

<div class="px-4 py-5">

    @if($errors->any())
        <div class="mb-4 bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-red-700 text-sm">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('officer.fees.store') }}" class="space-y-4"
          data-loading="dialog" data-loading-message="Adding fee">
        @csrf

        {{-- Title --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                Fee Title <span class="text-red-500">*</span>
            </label>
            <input type="text" name="title" value="{{ old('title') }}" required
                   placeholder="e.g. Membership Fee, T-Shirt Fund..."
                   class="w-full border rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30
                          {{ $errors->has('title') ? 'border-red-400' : 'border-gray-300' }}">
            @error('title')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Amount --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                Amount (₱) <span class="text-red-500">*</span>
            </label>
            <div class="relative">
                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm text-gray-400 font-medium">₱</span>
                <input type="number" name="amount" value="{{ old('amount') }}" required
                       min="0.01" step="0.01" placeholder="0.00"
                       class="w-full border rounded-xl pl-8 pr-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30
                              {{ $errors->has('amount') ? 'border-red-400' : 'border-gray-300' }}">
            </div>
            @error('amount')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Due Date --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                Due Date <span class="text-red-500">*</span>
            </label>
            <input type="date" name="due_date" value="{{ old('due_date') }}" required
                   min="{{ today()->toDateString() }}"
                   class="w-full border rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30
                          {{ $errors->has('due_date') ? 'border-red-400' : 'border-gray-300' }}">
            @error('due_date')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Academic Period --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                Academic Period <span class="text-red-500">*</span>
            </label>
            <select name="academic_period" required
                    class="w-full border rounded-xl px-4 py-3 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#07873e]/30
                           {{ $errors->has('academic_period') ? 'border-red-400' : 'border-gray-300' }}">
                <option value="">Select period...</option>
                @php
                    // Plain PHP inside @php, so these interpolate with {$var} — `{{ }}`
                    // is Blade and would be stored literally, which is how fees ended up
                    // recorded as "1st Semester {{ 2026 }}-{{ 2026 + 1 }}".
                    $year = now()->year;
                    $next = $year + 1;
                    $prev = $year - 1;

                    $periods = [
                        "1st Semester {$year}-{$next}",
                        "2nd Semester {$year}-{$next}",
                        "Summer {$next}",
                        "1st Semester {$prev}-{$year}",
                        "2nd Semester {$prev}-{$year}",
                    ];
                @endphp
                @foreach($periods as $period)
                    <option value="{{ $period }}" {{ old('academic_period') === $period ? 'selected' : '' }}>
                        {{ $period }}
                    </option>
                @endforeach
            </select>
            @error('academic_period')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Info note --}}
        <div class="bg-[#07873e]/5 border border-[#07873e]/20 rounded-xl px-4 py-3">
            <p class="text-xs text-[#07873e] font-medium">
                This fee will automatically be assigned to all active club members.
            </p>
        </div>

        {{-- Buttons --}}
        <div class="grid grid-cols-2 gap-3 pt-2">
            <a href="{{ route('officer.fees.index') }}"
               class="flex items-center justify-center border-2 border-[#07873e] text-[#07873e] font-semibold text-sm rounded-xl py-3.5 hover:bg-[#07873e]/5 transition-colors">
                Cancel
            </a>
            <button type="submit"
                    class="bg-[#07873e] text-[#F9A825] font-semibold text-sm rounded-xl py-3.5 hover:opacity-90 transition-opacity">
                Create Fee
            </button>
        </div>

    </form>
</div>

@endsection
