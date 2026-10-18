@extends('layouts.app-vpa')
@section('title', $event->title . ' — Booking Letter')
@section('page-title', 'Booking Letter')

@section('content')

<div class="bg-[#07873e] px-5 pt-5 pb-6">
    <a href="{{ route('vpa.letters.index') }}" class="text-white/70 hover:text-white mb-4 inline-block" aria-label="Back to letters">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/>
        </svg>
    </a>
    <div class="flex items-start justify-between gap-3">
        <div class="flex-1 min-w-0">
            <h1 class="text-white font-bold text-xl leading-snug">{{ $event->title }}</h1>
            <p class="text-white/60 text-xs mt-1">{{ $event->club?->name }} · {{ $event->venue }} · {{ $event->date->format('l, F j, Y') }}</p>
        </div>
        <x-status-badge :status="$event->approval_status" />
    </div>
</div>

<div class="px-4 py-5 max-w-6xl">
    <div class="grid gap-5 lg:grid-cols-5">

        {{-- Decision, details and progress --}}
        <div class="space-y-4 lg:col-span-2 lg:order-2">

            @if($event->isAwaitingVpa())
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 space-y-3">
                    <p class="text-sm font-semibold text-gray-800">Your decision</p>

                    @if($conflict)
                        <div class="bg-red-50 border border-red-200 rounded-lg px-3 py-2 text-xs text-red-700">
                            {{ $event->venue }} is also held by another booking from
                            {{ substr((string) $conflict->time_start, 0, 5) }} to {{ substr((string) $conflict->time_end, 0, 5) }} that day.
                            Consider returning the letter so the club can pick another room or time.
                        </div>
                    @endif

                    <p class="text-xs text-gray-500 leading-relaxed">
                        Approving signs the letter under "Approved by" and passes the activity to the club adviser to confirm.
                        @unless($hasSignature)
                            You have no signature on file, so the letter will say it was signed electronically.
                            <a href="{{ route('profile') }}#signature" class="text-[#07873e] font-semibold underline">Add a signature</a>.
                        @endunless
                    </p>

                    <form method="POST" action="{{ route('vpa.letters.approve', $event) }}"
                          data-loading="dialog" data-loading-message="Signing the letter">
                        @csrf
                        <button type="submit"
                                class="w-full bg-[#07873e] text-[#F9A825] font-semibold text-sm rounded-xl py-3 hover:opacity-90 transition-opacity">
                            Approve &amp; Sign
                        </button>
                    </form>

                    <details class="group" @if($errors->has('review_remarks')) open @endif>
                        <summary class="cursor-pointer list-none w-full text-center border border-red-300 text-red-600 font-semibold text-sm rounded-xl py-2.5 hover:bg-red-50 transition-colors">
                            Return with remarks
                        </summary>
                        <form method="POST" action="{{ route('vpa.letters.reject', $event) }}" class="mt-3 space-y-2">
                            @csrf
                            <label for="review_remarks" class="text-xs text-gray-500 font-medium block">What should the club change?</label>
                            <textarea id="review_remarks" name="review_remarks" rows="3" required minlength="5" maxlength="2000"
                                      placeholder="e.g. The AVR is reserved for the college assembly that afternoon. Please choose another room."
                                      class="w-full border rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-red-200
                                             {{ $errors->has('review_remarks') ? 'border-red-400' : 'border-gray-300' }}">{{ old('review_remarks') }}</textarea>
                            @error('review_remarks')
                                <p class="text-red-500 text-xs">{{ $message }}</p>
                            @enderror
                            <button type="submit" class="w-full bg-red-500 text-white text-sm font-semibold rounded-xl py-2.5 hover:opacity-90 transition-opacity">
                                Return the letter
                            </button>
                            <p class="text-[11px] text-gray-400">The officers and adviser see your remarks. The room is released until the club sends the letter again.</p>
                        </form>
                    </details>
                </div>
            @endif

            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                <p class="text-sm font-semibold text-gray-800 mb-3">Approval</p>
                <x-booking-letter-progress :event="$event" />
            </div>

            <div class="bg-white rounded-xl border border-gray-100 shadow-sm divide-y divide-gray-50">
                @foreach([
                    'Room'          => $event->venue,
                    'Date'          => $event->date->format('l, F j, Y'),
                    'Time'          => \Carbon\Carbon::parse($event->time_start)->format('g:i A') . ' – ' . \Carbon\Carbon::parse($event->time_end)->format('g:i A'),
                    'Activity type' => $event->activity_type_label,
                    'Expected'      => number_format((int) $event->expected_participants) . ' participants',
                    'Year levels'   => $event->target_year_levels_label,
                ] as $label => $value)
                    <div class="flex items-center justify-between gap-3 px-4 py-2.5">
                        <span class="text-xs text-gray-500">{{ $label }}</span>
                        <span class="text-xs font-semibold text-gray-800 text-right">{{ $value }}</span>
                    </div>
                @endforeach
            </div>

            <details class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
                <summary class="text-sm font-semibold text-gray-700 cursor-pointer">Description and purpose</summary>
                <p class="text-xs text-gray-500 mt-3 font-medium">Description</p>
                <p class="text-sm text-gray-700 leading-relaxed mt-1">{{ $event->description }}</p>
                <p class="text-xs text-gray-500 mt-3 font-medium">Purpose / Objectives</p>
                <p class="text-sm text-gray-700 leading-relaxed mt-1">{{ $event->purpose }}</p>
            </details>
        </div>

        {{-- The letter --}}
        <div class="lg:col-span-3 lg:order-1 space-y-2">
            <div class="flex items-center justify-between">
                <p class="text-sm font-semibold text-gray-700">
                    The letter
                    @if($event->letter_ai_drafted)
                        <span class="ml-1 text-[10px] font-semibold uppercase tracking-wide text-[#07873e] bg-[#07873e]/10 rounded px-1.5 py-0.5">AI-assisted, reviewed by the officer</span>
                    @endif
                </p>
                <a href="{{ route('vpa.letters.pdf', $event) }}" class="text-xs text-[#07873e] font-semibold underline">Download PDF</a>
            </div>
            <x-booking-letter :letter="$letter" />
        </div>
    </div>
</div>
@endsection
