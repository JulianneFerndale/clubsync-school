@extends('layouts.app-officer')
@section('title', 'Edit Activity')
@section('club-name', $club?->name ?? 'ClubSync')

@section('content')

{{-- Header --}}
<div class="flex items-center gap-3 bg-[#07873e] px-5 py-4">
    <a href="{{ route('officer.activities.show', $event) }}" class="text-white/70 hover:text-white transition-colors">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
        </svg>
    </a>
    <h1 class="text-white font-bold text-xl">Edit Activity</h1>
</div>

<div class="px-4 py-5">

    {{-- Validation errors --}}

    @if(in_array($event->approval_status, ['approved', 'pending_approval'], true))
        <div class="mb-4 bg-[#F9A825]/10 border border-[#F9A825]/40 rounded-xl px-4 py-3">
            <p class="text-sm text-gray-700 font-medium">
                {{ $event->approval_status === 'approved' ? 'This activity is already approved.' : 'The VPA has already approved this letter.' }}
                Saving a change signs the letter again and sends it back to the Vice President for Administration, so it needs their approval and the adviser's again.
            </p>
        </div>
    @elseif($event->approval_status === 'no_approval_needed')
        <div class="mb-4 bg-[#F9A825]/10 border border-[#F9A825]/40 rounded-xl px-4 py-3">
            <p class="text-sm text-gray-700 font-medium">
                This meeting was scheduled before every activity needed approval. Saving a change sends it with a room booking letter to the Vice President for Administration, then to your adviser, and it is off members' schedules until both approve.
            </p>
        </div>
    @elseif($event->approval_status === 'rejected')
        <div class="mb-4 bg-red-50 border border-red-200 rounded-xl px-4 py-3">
            <p class="text-sm text-red-700 font-medium">
                {{ $event->wasRejectedByVpa() ? 'The Vice President for Administration returned this letter.' : 'The club adviser did not approve this activity.' }}
                Make the changes asked for, then save to sign the letter and send it to the VPA again.
            </p>
            @if($event->review_remarks)
                <p class="text-xs text-red-600 mt-1">Remarks: {{ $event->review_remarks }}</p>
            @endif
        </div>
    @elseif($event->isAwaitingVpa())
        <div class="mb-4 bg-gray-50 border border-gray-200 rounded-xl px-4 py-3">
            <p class="text-sm text-gray-700">The letter is with the Vice President for Administration. Changes you save replace the letter they see, signed again by you.</p>
        </div>
    @endif

    <form method="POST" action="{{ route('officer.activities.update', $event) }}" enctype="multipart/form-data" class="space-y-4" id="event-form"
          data-loading="dialog" data-loading-message="Saving changes"
          x-data="{ activityType: '{{ old('activity_type', $event->activity_type) }}' }">
        @csrf
        @method('PATCH')

        {{-- Activity title --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                Activity Title <span class="text-red-500">*</span>
            </label>
            <input type="text" name="title" value="{{ old('title', $event->title) }}" required
                   class="w-full border rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30
                          {{ $errors->has('title') ? 'border-red-400' : 'border-gray-300' }}">
            @error('title')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Description --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                Description <span class="text-red-500">*</span>
            </label>
            <textarea name="description" rows="3" required
                      class="w-full border rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30 resize-none
                             {{ $errors->has('description') ? 'border-red-400' : 'border-gray-300' }}">{{ old('description', $event->description) }}</textarea>
            @error('description')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Date --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                Date <span class="text-red-500">*</span>
            </label>
            <input type="date" name="date" value="{{ old('date', $event->date->toDateString()) }}" required
                   class="w-full border rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30
                          {{ $errors->has('date') ? 'border-red-400' : 'border-gray-300' }}">
            @error('date')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Time (start + end) --}}
        <div class="grid grid-cols-2 gap-3">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                    Start Time <span class="text-red-500">*</span>
                </label>
                <input type="time" name="time_start" value="{{ old('time_start', substr((string) $event->time_start, 0, 5)) }}" required
                       class="w-full border rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30
                              {{ $errors->has('time_start') ? 'border-red-400' : 'border-gray-300' }}">
                @error('time_start')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                    End Time <span class="text-red-500">*</span>
                </label>
                <input type="time" name="time_end" value="{{ old('time_end', substr((string) $event->time_end, 0, 5)) }}" required
                       class="w-full border rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30
                              {{ $errors->has('time_end') ? 'border-red-400' : 'border-gray-300' }}">
                @error('time_end')
                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                @enderror
            </div>
        </div>

        {{-- Location --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                Location <span class="text-red-500">*</span>
            </label>
            <select name="venue" required
                    class="w-full border rounded-xl px-4 py-3 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#07873e]/30
                           {{ $errors->has('venue') ? 'border-red-400' : 'border-gray-300' }}">
                <option value="">Select a room...</option>
                {{-- Booked before the switch to main-campus rooms: keep it selectable so
                     saving an edit does not silently move the activity. --}}
                @if($event->venue && ! in_array($event->venue, $venues, true))
                    <option value="{{ $event->venue }}" {{ old('venue', $event->venue) === $event->venue ? 'selected' : '' }}>
                        {{ $event->venue }} (no longer on the room list)
                    </option>
                @endif
                @foreach($venues as $venue)
                    <option value="{{ $venue }}" {{ old('venue', $event->venue) === $venue ? 'selected' : '' }}>
                        {{ $venue }}
                    </option>
                @endforeach
            </select>
            <p class="text-xs text-gray-400 mt-1">Main campus rooms only.</p>
            @error('venue')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Purpose / Objectives --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                Purpose / Objectives <span class="text-red-500">*</span>
            </label>
            <textarea name="purpose" rows="3" required
                      class="w-full border rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30 resize-none
                             {{ $errors->has('purpose') ? 'border-red-400' : 'border-gray-300' }}">{{ old('purpose', $event->purpose) }}</textarea>
            @error('purpose')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Expected Participants --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                Expected Participants <span class="text-red-500">*</span>
            </label>
            <input type="number" name="expected_participants" value="{{ old('expected_participants', $event->expected_participants) }}"
                   min="1" required
                   class="w-full border rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30
                          {{ $errors->has('expected_participants') ? 'border-red-400' : 'border-gray-300' }}">
            @error('expected_participants')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Target Year Level(s) --}}
        @php($selectedYearLevels = old('target_year_levels', $event->target_year_levels ?? []))
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                Target Year Level(s)
                <span class="text-xs text-gray-400 font-normal ml-1">(Optional)</span>
            </label>
            <div class="grid grid-cols-2 gap-2">
                @foreach($yearLevels as $value => $label)
                    <label class="flex items-center gap-2.5 border rounded-xl px-3 py-2.5 cursor-pointer transition-colors
                                  {{ in_array($value, $selectedYearLevels) ? 'border-[#07873e] bg-[#07873e]/5' : 'border-gray-300' }}">
                        <input type="checkbox" name="target_year_levels[]" value="{{ $value }}"
                               class="w-4 h-4 rounded border-gray-300 text-[#07873e] focus:ring-[#07873e]"
                               {{ in_array($value, $selectedYearLevels) ? 'checked' : '' }}>
                        <span class="text-sm text-gray-700">{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            <p class="text-xs text-gray-400 mt-1.5">
                For year-level-specific workshops (e.g. a session for 1st years only). Leave all unchecked to open the activity to every year level.
            </p>
            @error('target_year_levels')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            @error('target_year_levels.*')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        {{-- Activity Type --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                Activity Type <span class="text-red-500">*</span>
            </label>
            <select name="activity_type" required x-model="activityType"
                    class="w-full border rounded-xl px-4 py-3 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#07873e]/30
                           {{ $errors->has('activity_type') ? 'border-red-400' : 'border-gray-300' }}">
                <option value="internal_meeting">Internal Meeting</option>
                <option value="acle">ACLE</option>
                <option value="community_involvement">Community Involvement</option>
                <option value="campus_resource_use">Campus Resource Use</option>
                <option value="other_external">Other External Activity</option>
            </select>
            @error('activity_type')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Room booking letter to the VPA (every activity) --}}
        @include('officer.activities._booking-letter-field', [
            'letterBody' => old('letter_body', $event->letter_body ?? ''),
            'aiDrafted'  => old('letter_ai_drafted', $event->letter_ai_drafted ? '1' : '0'),
        ])

        {{-- Supporting document --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                Other Supporting Document
                <span class="text-xs text-gray-400 font-normal ml-1">(Optional, PDF/JPG/PNG, max 5MB)</span>
            </label>
            @if($event->approval_letter_path)
                <a href="{{ route('officer.activities.letter', $event) }}" class="text-xs text-[#07873e] font-semibold underline block mb-2">
                    View current uploaded document
                </a>
            @endif
            <input type="file" name="approval_letter" accept=".pdf,.jpg,.jpeg,.png"
                   class="w-full border rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30
                          {{ $errors->has('approval_letter') ? 'border-red-400' : 'border-gray-300' }}">
            @error('approval_letter')
                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Buttons --}}
        <div class="grid grid-cols-2 gap-3 pt-2">
            <a href="{{ route('officer.activities.show', $event) }}"
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

@endsection
