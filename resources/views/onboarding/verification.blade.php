@extends('layouts.auth')
@section('title', 'Enrollment Verification — ClubSync')

@section('content')
<div class="flex flex-col min-h-screen">

    <div class="flex-1 px-8 pt-12 pb-10">

        @if($waiting)
            {{-- With a person: nothing to do but wait. --}}
            <div class="w-14 h-14 rounded-full bg-amber-50 border border-amber-200 flex items-center justify-center mb-5">
                <svg class="w-7 h-7 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/>
                </svg>
            </div>

            @if($latest->review_reason === \App\Models\EnrollmentVerification::REASON_AI_UNAVAILABLE)
                <p class="inline-block text-xs font-semibold text-amber-700 bg-amber-50 border border-amber-200 rounded-full px-3 py-1 mb-3">Pending Verification</p>
                <h1 class="text-2xl font-bold text-green-900 leading-snug mb-2">Your enrollment is being checked</h1>
                <p class="text-sm text-gray-600 leading-relaxed mb-5">
                    Hi {{ $user->first_name }}. The automatic check was unavailable when you uploaded your proof of enrollment
                    on {{ $latest->created_at->format('F j, Y') }}, so it was saved as Pending Verification. ClubSync checks it
                    again by itself as soon as the check is back, and the Student Affairs Office can also check it, whichever
                    comes first. You will get a notification either way, and you can use ClubSync once it is verified.
                </p>
            @else
                <h1 class="text-2xl font-bold text-green-900 leading-snug mb-2">Your enrollment is being checked</h1>
                <p class="text-sm text-gray-600 leading-relaxed mb-5">
                    Hi {{ $user->first_name }}. The Student Affairs Office is checking the proof of enrollment you uploaded
                    on {{ $latest->created_at->format('F j, Y') }}. You will get a notification as soon as they decide, and you
                    can use ClubSync once it is approved.
                </p>
            @endif

            <div class="bg-gray-50 border border-gray-200 rounded-2xl px-4 py-3.5 text-[13px] text-gray-700 space-y-1">
                <p><span class="text-gray-500">Document:</span> <span class="font-semibold">{{ $latest->document_label }}</span></p>
                <p><span class="text-gray-500">Course:</span> <span class="font-semibold">{{ $user->course?->name }}</span></p>
            </div>

            <p class="text-xs text-gray-400 mt-5 leading-relaxed">
                You can sign out and come back later. If it is taking long, visit the Student Affairs Office with your
                Certificate of Enrollment or school ID.
            </p>
        @else
            <h1 class="text-2xl font-bold text-green-900 leading-snug mb-2">Verify your enrollment</h1>

            @if($latest?->status === \App\Models\EnrollmentVerification::STATUS_REJECTED)
                <div class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-sm text-red-700 mb-5">
                    <p class="font-semibold mb-0.5">Your last document was not accepted</p>
                    <p>{{ $latest->remarks }}</p>
                </div>
            @else
                <p class="text-sm text-gray-500 leading-relaxed mb-5">
                    Upload your proof of enrollment to start using ClubSync.
                </p>
            @endif

            <x-enrollment-document-form
                :action="route('verification.store')"
                submit-label="Verify my enrollment"
                :can-request-review="$canRequestReview"
                :name="$user->name"
                :edp-number="$user->edp_number"
                :course-name="$user->course?->name" />

            <p class="text-center text-xs mt-5">
                <a href="{{ route('verification.course') }}" class="text-green-700 font-semibold hover:text-green-900">Picked the wrong course? Change it</a>
            </p>
        @endif

        <form method="POST" action="{{ route('logout') }}" data-turbo="false" class="mt-8 text-center">
            @csrf
            <button type="submit" class="text-sm text-gray-500 font-semibold hover:text-gray-800">Sign out</button>
        </form>
    </div>
</div>
@endsection
