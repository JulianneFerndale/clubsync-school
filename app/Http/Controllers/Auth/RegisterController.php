<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\EmailAlreadyRegistered;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Department;
use App\Models\User;
use App\Services\AcademicClubEnrollment;
use App\Services\AuthManager;
use App\Services\SessionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function __construct(
        private AuthManager $auth,
        private SessionManager $session,
        private AcademicClubEnrollment $enrollment,
    ) {}

    public function showStep1(): View
    {
        return view('auth.register');
    }

    public function storeStep1(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'first_name'    => ['required', 'string', 'max:100'],
            'last_name'     => ['required', 'string', 'max:100'],
            'edp_number'    => ['required', 'string', 'max:20'],
            'email'         => ['required', 'email', 'ends_with:@sccpag.edu.ph', 'unique:users,email'],
            'password'      => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'mobile_number' => ['required', 'string', 'max:20'],
            'year_level'    => ['required', 'integer', 'between:1,4'],
            'photo'         => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'terms'         => ['accepted'],
        ], [
            'email.ends_with'    => 'Only Saint Columban College institutional emails (@sccpag.edu.ph) are accepted.',
            'email.unique'       => 'An account with this email already exists.',
            'year_level.required'=> 'Please select your school year level.',
            'year_level.between' => 'Year level must be between 1st and 4th year.',
            'terms.accepted'     => 'You must accept the Terms and Conditions to continue.',
        ]);

        $email    = strtolower($validated['email']);
        $password = $validated['password'];

        // The address is checked against Firebase HERE, while the person is still
        // looking at the email field, rather than at the end of the wizard. Without
        // this, an address that exists in Firebase but never finished registering
        // passes `unique:users,email`, and the failure only surfaces after a
        // department and a course have been picked — with nothing said about why.
        try {
            $account = $this->claimFirebaseAccount($email, $password);
        } catch (EmailAlreadyRegistered $e) {
            return back()->withInput()->withErrors(['email' => $e->getMessage()]);
        } catch (\Throwable $e) {
            Log::error('Firebase registration failed for ' . $email . ': ' . $e->getMessage());

            return back()->withInput()->withErrors([
                'email' => 'We could not check this email address right now. Please try again.',
            ]);
        }

        session([
            'register.firebase_uid'  => $account['localId'],
            'register.id_token'      => $account['idToken'] ?? null,
            'register.refresh_token' => $account['refreshToken'] ?? null,
            'register.first_name'    => $validated['first_name'],
            'register.last_name'     => $validated['last_name'],
            'register.edp_number'    => $validated['edp_number'],
            'register.email'         => $email,
            'register.password'      => $password,
            'register.mobile_number' => $validated['mobile_number'],
            'register.year_level'    => $validated['year_level'],
        ]);

        // Store the optional profile photo now and carry its URL through the
        // remaining steps (a file can't live in the session).
        if ($request->hasFile('photo')) {
            session(['register.photo_url' => Storage::url($request->file('photo')->store('profile-photos', 'public'))]);
        }

        return redirect()->route('register.department');
    }

    /**
     * The Firebase account this registration will use, created if the address is free.
     *
     * Firebase will not answer "is this address taken?" — email enumeration protection
     * withholds `registered` from accounts:createAuthUri, and a real address and a
     * made-up one come back byte-identical. It will, however, refuse to create a
     * duplicate. So creating the account IS the check, and it runs at the email field.
     *
     * A refusal means two different things to the person typing:
     *   - a ClubSync account exists too → the address really is spoken for; sign in.
     *   - no ClubSync account exists → an earlier attempt created the Firebase half
     *     and never finished. That half is theirs, so if the password they just typed
     *     opens it they are handed it back and carry on from where they left off.
     *     If it does not open, the address belongs to somebody else.
     *
     * @throws EmailAlreadyRegistered when the address is not this person's to register
     */
    private function claimFirebaseAccount(string $email, string $password): array
    {
        try {
            return $this->auth->registerWithFirebase($email, $password);
        } catch (\Throwable $e) {
            if (! str_contains($e->getMessage(), 'EMAIL_EXISTS')) {
                throw $e;
            }
        }

        if (User::where('email', $email)->exists()) {
            throw new EmailAlreadyRegistered(
                'An account with this email address already exists. Please sign in instead.'
            );
        }

        // Half-finished registration. Adopt it only if this person can open it.
        try {
            $account = $this->auth->loginWithFirebase($email, $password, remember: false);
        } catch (\Throwable $e) {
            throw new EmailAlreadyRegistered(
                'This email address is already registered. Please sign in instead, or reset your password if you have forgotten it.'
            );
        }

        Log::info('Resumed an unfinished registration for ' . $email);

        return $account;
    }

    /**
     * Live check behind the email field, so the common case is caught as it is typed.
     *
     * Answers only from the ClubSync side, which is all that can be read without
     * side effects. Silence is therefore "nothing known against it", not "available"
     * — the address is settled for certain when step 1 is submitted and the Firebase
     * account is claimed.
     */
    public function checkEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'max:255'],
        ]);

        $email = strtolower(trim($validated['email']));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['status' => 'pending', 'message' => null]);
        }

        if (! str_ends_with($email, '@sccpag.edu.ph')) {
            return response()->json([
                'status'  => 'invalid',
                'message' => 'Only Saint Columban College institutional emails (@sccpag.edu.ph) are accepted.',
            ]);
        }

        if (User::where('email', $email)->exists()) {
            return response()->json([
                'status'  => 'taken',
                'message' => 'An account with this email address already exists. Please sign in instead.',
            ]);
        }

        return response()->json(['status' => 'pending', 'message' => null]);
    }

    public function showDepartment(): RedirectResponse|View
    {
        if (! session('register.email')) {
            return redirect()->route('register');
        }

        $departments = Department::orderBy('short_name')->get();

        return view('auth.register.department', compact('departments'));
    }

    public function storeDepartment(Request $request): RedirectResponse
    {
        $request->validate([
            'department_id' => ['required', 'exists:departments,id'],
        ]);

        session(['register.department_id' => $request->department_id]);

        return redirect()->route('register.course');
    }

    public function showCourse(): RedirectResponse|View
    {
        if (! session('register.department_id')) {
            return redirect()->route('register.department');
        }

        $department = Department::with('courses')->findOrFail(session('register.department_id'));

        return view('auth.register.course', compact('department'));
    }

    public function storeCourse(Request $request): RedirectResponse
    {
        $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
        ]);

        $course = Course::findOrFail($request->course_id);

        if ((int) $course->department_id !== (int) session('register.department_id')) {
            return back()->withErrors(['course_id' => 'Invalid course selection.']);
        }

        $email    = session('register.email');
        $password = session('register.password');

        // The Firebase account was claimed at step 1, so there is nothing to create
        // here and nothing left that can fail on the address. Without it in the
        // session there is no account to attach this to, and sending them back to
        // the email field is the only honest thing to do.
        $firebaseUid = session('register.firebase_uid');

        if (! $firebaseUid) {
            return redirect()->route('register')->withErrors([
                'email' => 'Your registration session expired before it was finished. Please start again.',
            ]);
        }

        $user = User::create([
            'first_name'    => session('register.first_name'),
            'last_name'     => session('register.last_name'),
            'edp_number'    => session('register.edp_number'),
            'email'         => $email,
            'password'      => $password,
            'mobile_number' => session('register.mobile_number'),
            'department_id' => session('register.department_id'),
            'course_id'     => $request->course_id,
            'year_level'    => session('register.year_level'),
            'firebase_uid'  => $firebaseUid,
            'role'          => 'member',
            'profile_photo_url' => session('register.photo_url'),
        ]);

        // Queue the new student for the academic club(s) their course maps to. Shared
        // with the post-login course step, so the two cannot disagree about what
        // enrolment means or about a course that maps to no club at all.
        $this->enrollment->enroll($user, $course);

        // Firebase ID tokens last about an hour, and the one from step 1 may have
        // gone stale while the department and course were being chosen.
        $idToken      = session('register.id_token');
        $refreshToken = session('register.refresh_token');

        if ($refreshToken && (! $idToken || $this->auth->isTokenExpired($idToken))) {
            try {
                $idToken = $this->auth->refreshIdToken($refreshToken);
            } catch (\Throwable $e) {
                // Not fatal: the firebase.token middleware refreshes on the next request.
                Log::warning('Could not refresh the registration token for ' . $email . ': ' . $e->getMessage());
            }
        }

        session()->forget([
            'register.first_name', 'register.last_name', 'register.edp_number',
            'register.email', 'register.password', 'register.mobile_number',
            'register.year_level', 'register.department_id', 'register.photo_url',
            'register.firebase_uid', 'register.id_token', 'register.refresh_token',
        ]);

        $request->session()->regenerate();

        session(['course_selected' => true]);

        $this->session->store(
            (string) $idToken,
            $firebaseUid,
            'member',
            $user->id,
            refreshToken: $refreshToken,
        );

        return redirect()->route('member.dashboard');
    }
}
