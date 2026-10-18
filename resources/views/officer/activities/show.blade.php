@extends('layouts.app-officer')
@section('title', $event->title)
@section('club-name', $club?->name ?? 'ClubSync')

@section('content')

{{-- Header --}}
<div class="bg-[#07873e] px-5 pt-5 pb-6">
    <a href="{{ route('officer.activities.index') }}" class="text-white/70 hover:text-white mb-4 inline-block">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
        </svg>
    </a>
    <div class="flex items-start justify-between gap-3">
        <div class="flex-1">
            <h1 class="text-white font-bold text-xl leading-snug">{{ $event->title }}</h1>
            <p class="text-white/60 text-xs mt-1">
                {{ $event->date->format('l, F j, Y') }}
            </p>
        </div>
        <div class="flex flex-col items-end gap-1.5 flex-shrink-0">
            <x-status-badge :status="$event->status" />
            <x-status-badge :status="$event->activity_type" />
            <x-status-badge :status="$event->approval_status" />
        </div>
    </div>
</div>

<div class="px-4 py-5 space-y-5">

    {{-- Key details --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm divide-y divide-gray-50">
        <div class="flex items-center justify-between px-4 py-3">
            <span class="text-xs text-gray-500">Date</span>
            <span class="text-xs font-semibold text-gray-800">{{ $event->date->format('F j, Y') }}</span>
        </div>
        <div class="flex items-center justify-between px-4 py-3">
            <span class="text-xs text-gray-500">Time</span>
            <span class="text-xs font-semibold text-gray-800">
                {{ \Carbon\Carbon::parse($event->time_start)->format('g:i A') }}
                – {{ \Carbon\Carbon::parse($event->time_end)->format('g:i A') }}
            </span>
        </div>
        <div class="flex items-center justify-between px-4 py-3">
            <span class="text-xs text-gray-500">Venue</span>
            <span class="text-xs font-semibold text-gray-800">{{ $event->venue }}</span>
        </div>
        <div class="flex items-center justify-between px-4 py-3">
            <span class="text-xs text-gray-500">Expected</span>
            <span class="text-xs font-semibold text-gray-800">{{ number_format($event->expected_participants) }} participants</span>
        </div>
        <div class="flex items-center justify-between px-4 py-3">
            <span class="text-xs text-gray-500">Year Levels</span>
            <span class="text-xs font-semibold text-gray-800">{{ $event->target_year_levels_label }}</span>
        </div>
        @if($attendanceCount > 0)
        <div class="flex items-center justify-between px-4 py-3">
            <span class="text-xs text-gray-500">Attendance Recorded</span>
            <span class="text-xs font-semibold text-green-600">{{ $attendanceCount }} checked in</span>
        </div>
        @endif
    </div>

    {{-- Description --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
        <p class="text-xs text-gray-500 mb-1.5 font-medium">Description</p>
        <p class="text-sm text-gray-700 leading-relaxed">{{ $event->description }}</p>
    </div>

    {{-- Purpose --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
        <p class="text-xs text-gray-500 mb-1.5 font-medium">Purpose / Objectives</p>
        <p class="text-sm text-gray-700 leading-relaxed">{{ $event->purpose }}</p>
    </div>

    {{-- Approval: the VPA's, then the adviser's (every activity but a meeting scheduled before that rule) --}}
    @if($event->hasBookingLetter() || $event->approval_status !== 'no_approval_needed')
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 space-y-3">
        <div class="flex items-center justify-between">
            <p class="text-sm font-semibold text-gray-700">{{ $event->hasBookingLetter() ? 'Room Booking Letter' : 'Adviser Approval' }}</p>
            <x-status-badge :status="$event->approval_status" />
        </div>

        @if($event->hasBookingLetter())
            <x-booking-letter-progress :event="$event" />

            <div class="flex flex-wrap gap-x-4 gap-y-2 pt-1">
                <a href="{{ route('officer.activities.booking-letter', $event) }}" class="text-xs text-[#07873e] font-semibold underline">
                    Download the letter (PDF)
                </a>
                @if($event->approval_status === 'rejected')
                    <a href="{{ route('officer.activities.edit', $event) }}" class="text-xs text-[#07873e] font-semibold underline">
                        Edit and send again
                    </a>
                @endif
            </div>
        @elseif($event->approval_status === 'rejected' && $event->review_remarks)
            {{-- Sent before the VPA step: decided by the adviser alone. --}}
            <p class="text-xs text-red-500">Remarks: {{ $event->review_remarks }}</p>
        @endif

        @if($event->approval_letter_path)
            <a href="{{ route('officer.activities.letter', $event) }}" class="text-xs text-[#07873e] font-semibold underline block">
                View uploaded supporting document
            </a>
        @endif
    </div>
    @endif

    {{-- Change history --}}
    @if($event->changeLogs->isNotEmpty())
    <details class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
        <summary class="text-sm font-semibold text-gray-700 cursor-pointer">Change History ({{ $event->changeLogs->count() }})</summary>
        <div class="mt-3 space-y-3 divide-y divide-gray-50">
            @foreach($event->changeLogs as $log)
                <div class="pt-3 first:pt-0">
                    <p class="text-xs text-gray-400">
                        {{ $log->changedBy?->name ?? 'Unknown' }} · {{ $log->created_at->format('M j, Y g:i A') }}
                    </p>
                    <ul class="mt-1 space-y-0.5">
                        @foreach($log->changes as $change)
                            <li class="text-xs text-gray-600">
                                <span class="font-medium">{{ ucfirst(str_replace('_', ' ', $change['field'])) }}:</span>
                                {{ $change['before'] }} → {{ $change['after'] }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </details>
    @endif

    {{-- Action buttons --}}
    @if($event->status === 'scheduled')
        <div class="space-y-3 pt-2">
            {{-- Edit --}}
            <a href="{{ route('officer.activities.edit', $event) }}"
               class="flex items-center justify-between w-full border-2 border-[#07873e] text-[#07873e] font-semibold text-sm rounded-xl px-5 py-4 hover:bg-[#07873e]/5 transition-colors">
                Edit Activity
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125"/>
                </svg>
            </a>

            {{-- Record Attendance --}}
            <a href="{{ route('officer.activities.attendance', $event) }}"
               class="flex items-center justify-between w-full bg-[#07873e] text-[#F9A825] font-semibold text-sm rounded-xl px-5 py-4 hover:opacity-90 transition-opacity">
                Record Attendance
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m8.25 4.5 7.5 7.5-7.5 7.5"/>
                </svg>
            </a>

            {{-- Mark as Completed (Step 7) --}}
            <form method="POST" action="{{ route('officer.activities.complete', $event) }}">
                @csrf
                <button type="submit"
                        class="w-full flex items-center justify-between border-2 border-[#07873e] text-[#07873e] font-semibold text-sm rounded-xl px-5 py-4 hover:bg-[#07873e]/5 transition-colors"
                        onclick="return confirm('Mark this activity as completed? This will generate a post-activity report.')">
                    Mark as Completed
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                    </svg>
                </button>
            </form>
        </div>
    @elseif($event->status === 'completed')
        <div class="bg-green-50 border border-green-200 rounded-xl px-4 py-3 text-green-700 text-sm text-center font-medium">
            This activity has been completed.
        </div>
    @endif

    {{-- Photos and records for the semester-end report documents --}}
    @php($categories = \App\Models\ActivityPhoto::CATEGORIES)
    <div id="report-photos" class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 space-y-4 scroll-mt-4">
        <div>
            <p class="text-sm font-semibold text-gray-700">Semester Report Photos</p>
            <p class="text-xs text-gray-500 mt-1 leading-relaxed">
                Attach photos once the activity or meeting is over. They are filed in the club's reporting folder and pasted into the
                semester-end documents the AI drafts for the DSA: the accomplishment report, minutes of meetings, financial report,
                property inventory, and communication letters &amp; publication materials.
            </p>
        </div>

        @if($reportLocked)
            <div class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-xs text-gray-600">
                The semester narrative report covering this activity has been submitted to the DSA, so these records are now fixed.
            </div>
        @endif

        @error('photos')
            <p class="text-red-500 text-xs">{{ $message }}</p>
        @enderror
        @error('photos.*')
            <p class="text-red-500 text-xs">{{ $message }}</p>
        @enderror

        {{-- Budget released: what the receipts are liquidated against in the financial report --}}
        <form method="POST" action="{{ route('officer.activities.budget', $event) }}" class="flex flex-wrap items-end gap-2">
            @csrf
            @method('PATCH')
            <div class="flex-1 min-w-[10rem]">
                <label for="budget_amount" class="text-xs text-gray-500 font-medium block mb-1">Budget released (PHP)</label>
                <input type="number" id="budget_amount" name="budget_amount" step="0.01" min="0"
                       value="{{ old('budget_amount', $event->budget_amount) }}" placeholder="None" @disabled($reportLocked)
                       class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30">
            </div>
            @unless($reportLocked)
                <button type="submit" class="border-2 border-[#07873e] text-[#07873e] font-semibold text-xs rounded-lg px-4 py-2 hover:bg-[#07873e]/5">Save budget</button>
            @endunless
            @error('budget_amount')
                <p class="text-red-500 text-xs w-full">{{ $message }}</p>
            @enderror
        </form>

        {{-- What is attached so far, by the report section it goes into --}}
        @forelse($photosByCategory as $category => $photos)
            <div>
                <p class="text-xs font-semibold text-gray-700">
                    {{ \App\Models\ActivityPhoto::categoryLabel($category) }}
                    <span class="font-normal text-gray-400">· {{ $categories[$category]['printed'] ?? '' }}</span>
                </p>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 mt-2">
                    @foreach($photos as $photo)
                        <figure class="border border-gray-100 rounded-lg overflow-hidden bg-gray-50">
                            <a href="{{ route('officer.activities.photos.show', [$event, $photo]) }}" target="_blank" rel="noopener">
                                <img src="{{ route('officer.activities.photos.show', [$event, $photo]) }}" alt="{{ $photo->caption ?: $photo->category_label }}"
                                     loading="lazy" class="w-full h-24 object-cover">
                            </a>
                            <figcaption class="px-2 py-1.5 space-y-0.5">
                                @if($photo->caption)
                                    <p class="text-[11px] text-gray-700 leading-snug">{{ $photo->caption }}</p>
                                @endif
                                @if($photo->category === 'receipt')
                                    <p class="text-[11px] font-semibold text-gray-800">PHP {{ number_format((float) $photo->amount, 2) }}@if($photo->reference_number) · No. {{ $photo->reference_number }}@endif</p>
                                @elseif($photo->category === 'property')
                                    <p class="text-[11px] font-semibold text-gray-800">Qty {{ $photo->quantity }}@if($photo->remarks) · {{ $photo->remarks }}@endif</p>
                                @endif
                                <p class="text-[10px] text-gray-400">{{ $photo->uploader?->name ?? 'An officer' }} · {{ $photo->created_at->format('M j') }}</p>
                                @unless($reportLocked)
                                    <form method="POST" action="{{ route('officer.activities.photos.destroy', [$event, $photo]) }}"
                                          onsubmit="return confirm('Remove this from the semester report?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-[11px] text-red-500 hover:underline">Remove</button>
                                    </form>
                                @endunless
                            </figcaption>
                        </figure>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="text-xs text-gray-400">Nothing attached yet.</p>
        @endforelse

        {{-- Attach more --}}
        @unless($reportLocked)
            <form method="POST" action="{{ route('officer.activities.photos.store', $event) }}" enctype="multipart/form-data"
                  class="border-t border-gray-100 pt-4 space-y-3"
                  x-data="{ category: @js(old('category', 'documentation')), meta: @js(collect($categories)->map(fn ($c) => ['hint' => $c['hint'], 'multiple' => $c['multiple']])) }">
                @csrf
                <div>
                    <label for="photo_category" class="text-xs text-gray-500 font-medium block mb-1">What are you attaching?</label>
                    <select id="photo_category" name="category" x-model="category"
                            class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#07873e]/30">
                        @foreach($categories as $key => $category)
                            <option value="{{ $key }}" @selected(old('category', 'documentation') === $key)>{{ $category['label'] }}</option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-gray-400 mt-1" x-text="meta[category]?.hint"></p>
                    @error('category')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="photo_files" class="text-xs text-gray-500 font-medium block mb-1">Photo(s)</label>
                    <input type="file" id="photo_files" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple required
                           x-bind:multiple="meta[category]?.multiple"
                           class="block w-full text-xs text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-[#07873e]/10 file:px-3 file:py-2 file:text-[#07873e] file:font-semibold">
                    <p class="text-[11px] text-gray-400 mt-1">JPG, PNG or WebP, up to 8 MB each.</p>
                </div>

                <div>
                    <label for="photo_caption" class="text-xs text-gray-500 font-medium block mb-1"
                           x-text="category === 'receipt' ? 'Item / description' : (category === 'property' ? 'Property' : 'Caption (optional)')">Caption (optional)</label>
                    <input type="text" id="photo_caption" name="caption" maxlength="255" value="{{ old('caption') }}"
                           class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30">
                    @error('caption')
                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-2 gap-2" x-show="category === 'receipt'" style="display: none">
                    <div>
                        <label for="photo_amount" class="text-xs text-gray-500 font-medium block mb-1">Amount (PHP)</label>
                        <input type="number" id="photo_amount" name="amount" step="0.01" min="0.01" value="{{ old('amount') }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30">
                        @error('amount')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="photo_reference" class="text-xs text-gray-500 font-medium block mb-1">Receipt no. (optional)</label>
                        <input type="text" id="photo_reference" name="reference_number" maxlength="60" value="{{ old('reference_number') }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2" x-show="category === 'property'" style="display: none">
                    <div>
                        <label for="photo_quantity" class="text-xs text-gray-500 font-medium block mb-1">Quantity</label>
                        <input type="number" id="photo_quantity" name="quantity" min="1" step="1" value="{{ old('quantity') }}"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30">
                        @error('quantity')
                            <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label for="photo_remarks" class="text-xs text-gray-500 font-medium block mb-1">Condition / remarks</label>
                        <input type="text" id="photo_remarks" name="remarks" maxlength="255" value="{{ old('remarks') }}" placeholder="e.g. Functioning"
                               class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#07873e]/30">
                    </div>
                </div>

                <button type="submit"
                        class="w-full bg-[#07873e] text-[#F9A825] font-semibold text-sm rounded-xl px-5 py-3 hover:opacity-90 transition-opacity">
                    Attach to Semester Report
                </button>
            </form>
        @endunless
    </div>

</div>
@endsection
