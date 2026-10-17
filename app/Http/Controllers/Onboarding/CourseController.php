<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Department;
use App\Models\User;
use App\Services\AcademicClubEnrollment;
use App\Services\SessionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Collect the course from a signed-in student who has none, and enrol them.
 *
 * This is the same step registration ends with, reached from the other direction:
 * an account that is already signed in but never recorded a course, so it belongs to
 * no academic club. RequireCourseSelection sends them here and keeps them here until
 * it is answered.
 */
class CourseController extends Controller
{
    public function __construct(
        private AcademicClubEnrollment $enrollment,
        private SessionManager $session,
    ) {}

    public function show(): RedirectResponse|View
    {
        $user = User::find(auth_user_id());

        if (! $user) {
            return redirect()->route('login');
        }

        // Already answered — nothing to ask, so do not trap them on this page.
        if ($user->course_id) {
            session(['course_selected' => true]);

            return $this->session->redirectByRole($user->role);
        }

        $departments = Department::with(['courses' => fn ($q) => $q->orderBy('name')])
            ->orderBy('short_name')
            ->get();

        return view('onboarding.course', compact('user', 'departments'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = User::find(auth_user_id());

        if (! $user) {
            return redirect()->route('login');
        }

        $validated = $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
            'course_id'     => ['required', 'exists:courses,id'],
        ], [
            'department_id.required' => 'Please select your college or department.',
            'course_id.required'     => 'Please select your course or program.',
        ]);

        $course = Course::findOrFail($validated['course_id']);

        if ((int) $course->department_id !== (int) $validated['department_id']) {
            return back()->withInput()->withErrors([
                'course_id' => 'That course does not belong to the college you selected.',
            ]);
        }

        $user->forceFill([
            'department_id' => $course->department_id,
            'course_id'     => $course->id,
        ])->save();

        $clubs = $this->enrollment->enroll($user, $course);

        session(['course_selected' => true]);

        return $this->session->redirectByRole($user->role)
            ->with('success', 'Course saved. ' . $this->enrollment->summary($clubs, $course));
    }
}
