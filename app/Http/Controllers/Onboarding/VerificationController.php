<?php

namespace App\Http\Controllers\Onboarding;

use App\Exceptions\UnreadableDocument;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Department;
use App\Models\EnrollmentVerification;
use App\Models\User;
use App\Services\AcademicClubEnrollment;
use App\Services\EnrollmentVerifier;
use App\Services\SessionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Where a signed-in student waits while their proof of enrollment is with the DSA, and
 * where they upload another one after it was turned down. RequireEnrollmentVerification
 * keeps them here until it is verified.
 */
class VerificationController extends Controller
{
    public function __construct(
        private EnrollmentVerifier $verifier,
        private AcademicClubEnrollment $enrollment,
        private SessionManager $session,
    ) {}

    public function show(): RedirectResponse|View
    {
        $user = User::with('course')->find(auth_user_id());

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $this->isHeld($user)) {
            session(['enrollment_cleared' => true]);

            return $this->session->redirectByRole($user->role);
        }

        $latest = EnrollmentVerification::where('user_id', $user->id)->latest('id')->first();

        return view('onboarding.verification', [
            'user'             => $user,
            'latest'           => $latest,
            // Waiting on a person: nothing to upload until they decide.
            'waiting'          => $user->enrollment_status === EnrollmentVerification::STATUS_PENDING
                                  && $latest?->isPending(),
            'canRequestReview' => (int) session('verification.attempts', 0) >= EnrollmentVerifier::ATTEMPTS_BEFORE_REVIEW,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = User::with('course')->find(auth_user_id());

        if (! $user) {
            return redirect()->route('login');
        }

        if (! $this->isHeld($user)) {
            return $this->session->redirectByRole($user->role);
        }

        if (! $user->course) {
            return redirect()->route('onboarding.course');
        }

        $waiting = EnrollmentVerification::where('user_id', $user->id)
            ->where('status', EnrollmentVerification::STATUS_PENDING)
            ->exists();

        if ($waiting) {
            return redirect()->route('verification.show')
                ->with('info', 'Your proof of enrollment is already with the Student Affairs Office.');
        }

        $request->validate(EnrollmentVerifier::rules(), EnrollmentVerifier::messages());

        try {
            $check = $this->verifier->check($request->file('document'), [
                'first_name' => (string) $user->first_name,
                'last_name'  => (string) $user->last_name,
                'edp_number' => (string) $user->edp_number,
            ], $user->course);
        } catch (UnreadableDocument $e) {
            return back()->withErrors(['document' => $e->getMessage()]);
        }

        $attempts = (int) session('verification.attempts', 0);

        $reviewRequested = $request->input('action') === 'review'
            && $attempts >= EnrollmentVerifier::ATTEMPTS_BEFORE_REVIEW;

        if ($check->failed() && ! $reviewRequested) {
            session(['verification.attempts' => $attempts + 1]);

            return back()->withErrors(['document' => array_values($check->problems)]);
        }

        session()->forget('verification.attempts');

        if ($check->passed()) {
            $clubs = $this->verifier->markVerified($user, $check);

            session(['enrollment_cleared' => true]);

            return $this->session->redirectByRole($user->role)
                ->with('success', 'Your enrollment has been verified. ' . $this->enrollment->summary($clubs, $user->course));
        }

        $reason = $check->failed() ? EnrollmentVerification::REASON_STUDENT_REQUEST : $check->reviewReason();
        $this->verifier->sendForReview($user, $check, $reason);

        return redirect()->route('verification.show')->with('success', $reason === EnrollmentVerification::REASON_AI_UNAVAILABLE
            ? 'The automatic check is unavailable right now, so your proof of enrollment was saved as Pending Verification. '
                . 'It will be checked automatically once the check is back, or by the Student Affairs Office, whichever comes first.'
            : 'Your proof of enrollment has been sent to the Student Affairs Office for review.');
    }

    /**
     * A course picked by mistake, corrected by the student while the account is held.
     * Nothing rests on it yet — academic club membership comes with verification — and
     * the next document is checked against it, so it must be theirs to fix.
     */
    public function editCourse(): RedirectResponse|View
    {
        $user = User::find(auth_user_id());

        if (! $user) {
            return redirect()->route('login');
        }

        if ($refused = $this->refuseCourseChange($user)) {
            return $refused;
        }

        $departments = Department::with(['courses' => fn ($q) => $q->orderBy('name')])
            ->orderBy('short_name')
            ->get();

        return view('onboarding.course', ['user' => $user, 'departments' => $departments, 'changing' => true]);
    }

    public function updateCourse(Request $request): RedirectResponse
    {
        $user = User::find(auth_user_id());

        if (! $user) {
            return redirect()->route('login');
        }

        if ($refused = $this->refuseCourseChange($user)) {
            return $refused;
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

        $from = $user->course_id;

        $user->forceFill([
            'department_id' => $course->department_id,
            'course_id'     => $course->id,
        ])->save();

        AuditLog::record('enrollment.course_changed', $user, [
            'from_course_id' => $from,
            'to_course_id'   => $course->id,
        ]);

        // Checks that failed against the old course no longer count against them.
        session()->forget('verification.attempts');

        return redirect()->route('verification.show')
            ->with('success', "Your course is now {$course->name}. Upload your proof of enrollment to continue.");
    }

    /**
     * Only a held account changes its course here, and not while a person is checking its
     * document: they are judging that document against the course on record.
     */
    private function refuseCourseChange(User $user): ?RedirectResponse
    {
        if (! $this->isHeld($user)) {
            return $this->session->redirectByRole($user->role);
        }

        $waiting = EnrollmentVerification::where('user_id', $user->id)
            ->where('status', EnrollmentVerification::STATUS_PENDING)
            ->exists();

        if ($waiting) {
            return redirect()->route('verification.show')->with('info',
                'Your proof of enrollment is with the Student Affairs Office. If your course is wrong, they will send it back '
                . 'and you can change it then.');
        }

        return null;
    }

    private function isHeld(User $user): bool
    {
        return in_array($user->enrollment_status, [
            EnrollmentVerification::STATUS_PENDING,
            EnrollmentVerification::STATUS_REJECTED,
        ], true);
    }
}
