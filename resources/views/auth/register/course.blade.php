@extends('layouts.auth')
@section('title', 'Choose Course — ClubSync')

@section('content')
<div class="flex flex-col min-h-screen">

    <div class="flex-1 px-8 pt-12 pb-6">
        <x-register-steps :current="3" />

        <h1 class="text-2xl font-bold text-green-900 leading-snug mb-6">
            Choose your course<br>to continue
        </h1>

        @error('course_id')
            <div class="mb-5 bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-red-700 text-sm">
                {{ $message }}
            </div>
        @enderror

        {{-- Coming back to change it: the course chosen before is still selected. --}}
        @php($chosen = old('course_id', session('register.course_id')))

        <form method="POST" action="{{ route('register.course') }}" data-turbo="false" id="courseForm"
              data-loading="splash" data-loading-message="Saving your course…">
            @csrf

            {{-- Selected department card --}}
            <div class="flex items-center gap-3 border border-gray-200 rounded-2xl px-4 py-3 mb-5 bg-white">
                @if ($department->logo_url)
                    <img src="{{ $department->logo_url }}" alt="{{ $department->short_name }} seal"
                         class="w-11 h-11 object-contain flex-shrink-0" width="44" height="44" decoding="async">
                @else
                    <div class="w-10 h-10 rounded-full bg-green-100 border border-green-300 flex items-center justify-center flex-shrink-0">
                        <span class="text-green-800 font-bold text-[8px] leading-tight text-center">{{ $department->short_name }}</span>
                    </div>
                @endif
                <span class="text-sm font-semibold text-gray-800 flex-1 min-w-0">{{ $department->name }}</span>
                <a href="{{ route('register.department') }}" class="text-xs text-green-700 font-semibold hover:text-green-900 flex-shrink-0">Change</a>
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
                               data-name="{{ $course->name }}"
                               data-clubs="{{ $course->clubs->pluck('name')->implode(', ') }}"
                               onchange="selectCourse(this)"
                               {{ $chosen == $course->id ? 'checked' : '' }}>
                        <span class="w-5 h-5 rounded-full border-2 flex-shrink-0 flex items-center justify-center transition-colors
                                     {{ $chosen == $course->id ? 'border-green-700 bg-green-700' : 'border-gray-300' }}"
                              id="courseRadio_{{ $course->id }}">
                            @if($chosen == $course->id)
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

{{--
    The course decides the academic club the student joins and must match their proof of
    enrollment, so it is read back to them before they go on. Without JavaScript the form
    simply submits, and step 4 still shows the choice with a way to change it.
--}}
<dialog id="courseConfirm" aria-labelledby="courseConfirmTitle"
        class="w-[calc(100%-2rem)] max-w-sm rounded-2xl p-0 shadow-xl backdrop:bg-black/40">
    <div class="px-6 pt-6 pb-5">
        <h2 id="courseConfirmTitle" class="text-lg font-bold text-green-900 mb-1">Is this right?</h2>
        <p class="text-sm text-gray-500 mb-4">Please check your choice before you go on.</p>

        <dl class="text-sm space-y-2.5 bg-green-50 border border-green-100 rounded-xl px-4 py-3">
            <div>
                <dt class="text-xs text-gray-500">Department</dt>
                <dd class="font-semibold text-gray-900">{{ $department->name }}</dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500">Course</dt>
                <dd class="font-semibold text-gray-900" data-confirm="course"></dd>
            </div>
            <div>
                <dt class="text-xs text-gray-500">Academic club you will join</dt>
                <dd class="font-semibold text-gray-900" data-confirm="clubs"></dd>
            </div>
        </dl>

        <p class="text-xs text-gray-500 leading-relaxed mt-3">
            Your Certificate of Enrollment or school ID must show this same course.
        </p>
    </div>

    <div class="flex flex-col gap-2 px-6 pb-6">
        <button type="button" data-confirm="yes"
                class="w-full bg-green-800 hover:bg-green-900 text-white font-semibold text-sm py-3 rounded-full transition-colors">
            Yes, this is my course
        </button>
        <button type="button" data-confirm="no"
                class="w-full border border-gray-300 text-gray-700 font-semibold text-sm py-3 rounded-full hover:bg-gray-50 transition-colors">
            Let me change it
        </button>
        <a href="{{ route('register.department') }}" class="text-center text-xs text-green-700 font-semibold hover:text-green-900 mt-1">
            Wrong department? Pick another one
        </a>
    </div>
</dialog>

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

// Run now rather than on DOMContentLoaded, which a Turbo visit never fires.
(function () {
    const checked = document.querySelector('input[name="course_id"]:checked');
    if (checked) selectCourse(checked);

    const form    = document.getElementById('courseForm');
    const button  = document.getElementById('continueBtn');
    const dialog  = document.getElementById('courseConfirm');
    if (!form || !button || !dialog || typeof dialog.showModal !== 'function') return;

    // Caught at the click, before the form submits, so the "Saving…" splash does not
    // start for a choice that has not been confirmed. Enter on a radio clicks it too.
    button.addEventListener('click', function (e) {
        const selected = form.querySelector('input[name="course_id"]:checked');
        if (!selected) return;

        e.preventDefault();
        dialog.querySelector('[data-confirm="course"]').textContent = selected.dataset.name;
        dialog.querySelector('[data-confirm="clubs"]').textContent = selected.dataset.clubs
            || 'None yet. The Student Affairs Office will assign your club.';
        dialog.showModal();
    });

    dialog.querySelector('[data-confirm="yes"]').addEventListener('click', function () {
        dialog.close();
        form.requestSubmit();
    });

    dialog.querySelector('[data-confirm="no"]').addEventListener('click', function () {
        dialog.close();
    });
})();
</script>
@endsection
