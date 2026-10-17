@extends('layouts.auth')
@section('title', 'Select Your Course — ClubSync')

@section('content')

{{-- Deliberately on the auth layout, with no navigation: until a course is on
     record the account belongs to no academic club, and there is nothing else for
     it to do yet.

     Also the page where a student held at proof of enrollment corrects a course picked
     by mistake ($changing — VerificationController::editCourse). --}}
@php($changing = $changing ?? false)

<div class="w-full max-w-md mx-auto bg-white rounded-2xl shadow-sm p-6 sm:p-8">

    @if($changing)
        <h1 class="text-2xl font-bold text-[#07873e]">Change your course</h1>
        <p class="text-sm text-gray-600 mt-2 leading-relaxed">
            Hi {{ $user->first_name }} — pick the course you are enrolled in. Your Certificate of Enrollment
            or school ID must show it, and it decides your academic club once your enrollment is verified.
        </p>
    @else
        <h1 class="text-2xl font-bold text-[#07873e]">One more step</h1>
        <p class="text-sm text-gray-600 mt-2 leading-relaxed">
            Hi {{ $user->first_name }} — your account does not have a course on record yet.
            Your course is what makes you a member of your academic club, so please
            select it to continue.
        </p>
    @endif

    @php($currentDepartment = old('department_id', $changing ? $user->department_id : null))
    @php($currentCourse = old('course_id', $changing ? $user->course_id : null))


    @if($departments->isEmpty())
        <div class="mt-4 bg-[#F9A825]/10 border border-[#F9A825]/40 rounded-xl px-4 py-3 text-sm text-gray-700">
            No colleges have been set up yet. Please contact the Student Affairs Office.
        </div>
    @else
    <form method="POST" action="{{ $changing ? route('verification.course.update') : route('onboarding.course.store') }}" class="mt-6 space-y-4" id="course-form">
        @csrf

        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">College / Department</label>
            <select name="department_id" id="department-select" required
                    class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm text-gray-900 bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                <option value="">Select…</option>
                @foreach($departments as $department)
                    <option value="{{ $department->id }}" {{ (string) $currentDepartment === (string) $department->id ? 'selected' : '' }}>
                        {{ $department->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Course / Program</label>
            <select name="course_id" id="course-select" required disabled
                    class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm text-gray-900 bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition disabled:bg-gray-50 disabled:text-gray-400">
                <option value="">Select your college first…</option>
            </select>
            <p class="text-xs text-gray-400 mt-1.5">
                @if($changing)
                    You will become a member of the academic club mapped to this course once your enrollment is verified.
                @else
                    You will become a member of the academic club mapped to this course right away —
                    no approval needed.
                @endif
            </p>
        </div>

        <button type="submit"
                class="w-full bg-[#07873e] text-[#F9A825] font-semibold text-sm rounded-xl py-3.5 hover:opacity-90 transition-opacity">
            {{ $changing ? 'Save my course' : 'Save and continue' }}
        </button>
    </form>
    @endif

    @if($changing)
        <p class="mt-4 text-center">
            <a href="{{ route('verification.show') }}" class="text-xs text-gray-500 hover:text-gray-700 transition-colors">Keep my current course</a>
        </p>
    @else
        <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
            @csrf
            <button type="submit" class="text-xs text-gray-400 hover:text-gray-600 transition-colors">
                Sign out instead
            </button>
        </form>
    @endif
</div>

<script>
(() => {
    const byDepartment = @json($departments->mapWithKeys(fn ($d) => [
        $d->id => $d->courses->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values(),
    ]));

    const department = document.getElementById('department-select');
    const course     = document.getElementById('course-select');
    if (! department || ! course) return;

    const selected = @json($currentCourse);

    const fill = () => {
        const courses = byDepartment[department.value] ?? [];

        course.innerHTML = '';
        course.disabled = courses.length === 0;

        if (courses.length === 0) {
            course.append(new Option(department.value ? 'No courses listed for this college' : 'Select your college first…', ''));
            return;
        }

        course.append(new Option('Select…', ''));

        for (const c of courses) {
            const option = new Option(c.name, c.id);
            option.selected = String(c.id) === String(selected);
            course.append(option);
        }
    };

    department.addEventListener('change', fill);
    fill();   // repopulate after a validation bounce
})();
</script>

@endsection
