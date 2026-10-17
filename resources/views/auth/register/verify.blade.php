@extends('layouts.auth')
@section('title', 'Verify Your Enrollment — ClubSync')

@section('content')
<div class="flex flex-col min-h-screen">

    <div class="flex-1 px-8 pt-12 pb-10">
        <x-register-steps :current="4" />

        <h1 class="text-2xl font-bold text-green-900 leading-snug mb-2">
            Verify your enrollment
        </h1>
        <p class="text-sm text-gray-500 leading-relaxed mb-5">
            ClubSync is for students of Saint Columban College. One last step: show us that you are enrolled.
            Your account is created only once this is done.
        </p>

        {{-- The last look before anything is created: what was chosen, and what it leads to. --}}
        <div class="border border-gray-200 rounded-2xl px-4 py-3.5 mb-5 bg-white">
            <p class="text-xs font-semibold text-gray-500 mb-2">Before you create your account, check your choice</p>
            <dl class="text-[13px] space-y-2">
                <div class="flex items-start gap-3">
                    <div class="flex-1 min-w-0">
                        <dt class="text-gray-500">Department</dt>
                        <dd class="font-semibold text-gray-900">{{ $department->name }}</dd>
                    </div>
                    <a href="{{ route('register.department') }}" class="text-xs text-green-700 font-semibold hover:text-green-900 pt-0.5">Change</a>
                </div>
                <div class="flex items-start gap-3">
                    <div class="flex-1 min-w-0">
                        <dt class="text-gray-500">Course</dt>
                        <dd class="font-semibold text-gray-900">{{ $course->name }}</dd>
                    </div>
                    <a href="{{ route('register.course') }}" class="text-xs text-green-700 font-semibold hover:text-green-900 pt-0.5">Change</a>
                </div>
                <div>
                    <dt class="text-gray-500">Academic club you will join</dt>
                    <dd class="font-semibold text-gray-900">
                        {{ $clubs->isNotEmpty() ? $clubs->implode(', ') : 'None yet. The Student Affairs Office will assign your club.' }}
                    </dd>
                </div>
            </dl>
        </div>

        <x-enrollment-document-form
            :action="route('register.verify')"
            submit-label="Verify & create my account"
            :can-request-review="$canRequestReview"
            :name="$name"
            :edp-number="$edpNumber"
            :course-name="$course->name" />

        <div class="flex items-center justify-center text-xs mt-6">
            <a href="{{ route('register') }}" class="text-green-700 font-semibold hover:text-green-900">{{ $viaGoogle ? 'Edit my EDP number' : 'Edit my name or EDP number' }}</a>
        </div>
    </div>
</div>
@endsection
