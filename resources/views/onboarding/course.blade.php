@extends('layouts.auth')
@section('title', 'Select Your Course — ClubSync')

@section('content')

{{-- Deliberately on the auth layout, with no navigation: until a course is on
     record the account belongs to no academic club, and there is nothing else for
     it to do yet. --}}
<div class="w-full max-w-md mx-auto bg-white rounded-2xl shadow-sm p-6 sm:p-8">

    <h1 class="text-2xl font-bold text-[#07873e]">One more step</h1>
    <p class="text-sm text-gray-600 mt-2 leading-relaxed">
        Hi {{ $user->first_name }} — your account does not have a course on record yet.
        Your course is what puts you in the queue for your academic club, so please
        select it to continue.
    </p>

    @if($errors->any())
        <div class="mt-4 bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-red-700 text-sm">
            {{ $errors->first() }}
        </div>
    @endif

    @if($departments->isEmpty())
        <div class="mt-4 bg-[#F9A825]/10 border border-[#F9A825]/40 rounded-xl px-4 py-3 text-sm text-gray-700">
            No colleges have been set up yet. Please contact the Student Affairs Office.
        </div>
    @else
    <form method="POST" action="{{ route('onboarding.course.store') }}" class="mt-6 space-y-4" id="course-form">
        @csrf

        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">College / Department</label>
            <select name="department_id" id="department-select" required
                    class="w-full border border-gray-300 rounded-xl px-4 py-3 text-sm text-gray-900 bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-transparent transition">
                <option value="">Select…</option>
                @foreach($departments as $department)
                    <option value="{{ $department->id }}" {{ (string) old('department_id') === (string) $department->id ? 'selected' : '' }}>
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
                You will be queued for the academic club mapped to this course. Membership starts
                once the club officers accept you and your adviser approves.
            </p>
        </div>

        <button type="submit"
                class="w-full bg-[#07873e] text-[#F9A825] font-semibold text-sm rounded-xl py-3.5 hover:opacity-90 transition-opacity">
            Save and continue
        </button>
    </form>
    @endif

    <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center">
        @csrf
        <button type="submit" class="text-xs text-gray-400 hover:text-gray-600 transition-colors">
            Sign out instead
        </button>
    </form>
</div>

<script>
(() => {
    const byDepartment = @json($departments->mapWithKeys(fn ($d) => [
        $d->id => $d->courses->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values(),
    ]));

    const department = document.getElementById('department-select');
    const course     = document.getElementById('course-select');
    if (! department || ! course) return;

    const selected = @json(old('course_id'));

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
