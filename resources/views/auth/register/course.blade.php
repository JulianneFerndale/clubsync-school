@extends('layouts.auth')
@section('title', 'Choose Course — ClubSync')

@section('content')
<div class="flex flex-col min-h-screen">

    <div class="flex-1 px-8 pt-12 pb-6">
        <h1 class="text-2xl font-bold text-green-900 leading-snug mb-6">
            Choose your course<br>to continue
        </h1>

        @error('course_id')
            <div class="mb-5 bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-red-700 text-sm">
                {{ $message }}
            </div>
        @enderror

        <form method="POST" action="{{ route('register.course') }}" data-turbo="false" id="courseForm"
              data-loading="splash" data-loading-message="Enrolling you in your academic club…">
            @csrf

            {{-- Selected department card --}}
            <div class="flex items-center gap-3 border border-gray-200 rounded-2xl px-4 py-3 mb-5 bg-white">
                <div class="w-10 h-10 rounded-full bg-green-100 border border-green-300 flex items-center justify-center flex-shrink-0">
                    {{-- Replace with <img src="/images/dept/{{ $department->slug }}.png" class="w-10 h-10 rounded-full object-cover"> --}}
                    <span class="text-green-800 font-bold text-[8px] leading-tight text-center">{{ $department->short_name }}</span>
                </div>
                <span class="text-sm font-semibold text-gray-800">{{ $department->name }}</span>
            </div>

            {{-- Course radio list --}}
            <div class="space-y-3" id="courseList">
                @foreach ($department->courses as $course)
                    <label class="flex items-center gap-3 border-2 border-gray-200 rounded-2xl px-4 py-3.5 cursor-pointer hover:border-green-400 transition-all course-card"
                           id="courseCard_{{ $course->id }}"
                           for="course_{{ $course->id }}">
                        <input type="radio" name="course_id" id="course_{{ $course->id }}"
                               value="{{ $course->id }}"
                               class="sr-only"
                               onchange="selectCourse(this)"
                               {{ old('course_id') == $course->id ? 'checked' : '' }}>
                        <span class="w-5 h-5 rounded-full border-2 flex-shrink-0 flex items-center justify-center transition-colors
                                     {{ old('course_id') == $course->id ? 'border-green-700 bg-green-700' : 'border-gray-300' }}"
                              id="courseRadio_{{ $course->id }}">
                            @if(old('course_id') == $course->id)
                                <span class="w-2 h-2 rounded-full bg-white"></span>
                            @endif
                        </span>
                        <span class="text-sm text-gray-700 font-medium leading-snug">{{ $course->name }}</span>
                    </label>
                @endforeach
            </div>

            {{-- Continue button --}}
            <div class="mt-8">
                <button type="submit" id="continueBtn"
                        class="flex items-center justify-between w-full bg-gray-300 text-white font-semibold text-[15px] py-4 px-6 rounded-full transition-colors"
                        disabled>
                    Continue
                    <span class="w-8 h-8 rounded-full bg-yellow-400 flex items-center justify-center flex-shrink-0">
                        <svg class="w-4 h-4 text-green-900" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/>
                        </svg>
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function selectCourse(radio) {
    // Reset all cards and radio indicators
    document.querySelectorAll('.course-card').forEach(function (card) {
        card.classList.remove('border-green-700', 'bg-green-50');
        card.classList.add('border-gray-200');
    });
    document.querySelectorAll('[id^="courseRadio_"]').forEach(function (el) {
        el.classList.remove('border-green-700', 'bg-green-700');
        el.classList.add('border-gray-300');
        el.innerHTML = '';
    });

    // Highlight selected
    const card = document.getElementById('courseCard_' + radio.value);
    card.classList.remove('border-gray-200');
    card.classList.add('border-green-700', 'bg-green-50');

    const radioEl = document.getElementById('courseRadio_' + radio.value);
    radioEl.classList.remove('border-gray-300');
    radioEl.classList.add('border-green-700', 'bg-green-700');
    radioEl.innerHTML = '<span class="w-2 h-2 rounded-full bg-white"></span>';

    // Enable button
    const btn = document.getElementById('continueBtn');
    btn.disabled = false;
    btn.classList.remove('bg-gray-300');
    btn.classList.add('bg-green-800', 'hover:bg-green-900');
}

document.addEventListener('DOMContentLoaded', function () {
    const checked = document.querySelector('input[name="course_id"]:checked');
    if (checked) selectCourse(checked);
});
</script>
@endsection
