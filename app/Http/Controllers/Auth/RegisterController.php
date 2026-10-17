<?php

namespace App\Http\Controllers\Auth;

use App\Exceptions\EmailAlreadyRegistered;
use App\Exceptions\UnreadableDocument;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Department;
use App\Models\EnrollmentVerification;
use App\Models\RegistrationDraft;
use App\Models\User;
use App\Services\AcademicClubEnrollment;
use App\Services\AuthManager;
use App\Services\EnrollmentCheck;
use App\Services\EnrollmentVerifier;
use App\Services\SessionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        private EnrollmentVerifier $verifier,
    ) {}

    /** The wording shared by both versions of step 1. */
    private const STEP1_MESSAGES = [
        'email.ends_with'    => 'Only Saint Columban College institutional emails (@sccpag.edu.ph) are accepted.',
        'email.unique'       => 'An account with this email already exists.',
        'year_level.required'=> 'Please select your school year level.',
        'year_level.between' => 'Year level must be between 1st and 4th year.',
        'terms.accepted'     => 'You must accept the Terms and Conditions to continue.',
    ];

    /** Shown on arriving at the department step from either version of step 1. */
    private const TO_DEPARTMENT = 'Your details are saved. Step 2 of 4: pick your department (college).';

    public function showStep1(): View
    {
        if ($google = $this->googleRegistration()) {
            return view('auth.register-google', ['google' => $google]);
        }

        return view('auth.register');
    }

    public function storeStep1(Request $request): RedirectResponse
    {
        if ($google = $this->googleRegistration()) {
            return $this->storeGoogleStep1($request, $google);
        }

        $validated = $request->validate([
            'first_name'    => ['required', 'string', 'max:100'],
            'last_name'     => ['required', 'string', 'max:100'],
            'edp_number'    => ['required', 'string', 'max:20'],
            'email'         => ['required', 'email', 'ends_with:@sccpag.edu.ph', 'unique:users,email'],
            'password'      => ['required', 'confirmed', Password::defaults()],
            'mobile_number' => ['required', 'string', 'max:20'],
            'year_level'    => ['required', 'integer', 'between:1,4'],
            'photo'         => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'terms'         => ['accepted'],
        ], self::STEP1_MESSAGES);

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
            'register.via_google'    => false,
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
            // New details, so failed checks against the old ones no longer count.
            'register.verify_attempts' => 0,
        ]);

        // Store the optional profile photo now and carry its URL through the
        // remaining steps (a file can't live in the session).
        if ($request->hasFile('photo')) {
            session(['register.photo_url' => Storage::url($request->file('photo')->store('profile-photos', 'public'))]);
        }

        RegistrationDraft::saveFromSession();

        return redirect()->route('register.department')->with('info', self::TO_DEPARTMENT);
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
                'This email address is already registered. Please sign in instead, or reset your password if you have forgotten it. '
                . 'If you started registering with Google, tap Register with Google to finish.'
            );
        }

        Log::info('Resumed an unfinished registration for ' . $email);

        return $account;
    }

    /**
     * Registering with an SCC Google account (POLICY.md §Authentication): name and
     * address come from Google, and no password is set — the student signs in with
     * Google. Only the EDP number, year level and mobile number are asked for here;
     * department, course and proof of enrollment follow as for everyone else.
     */
    private function storeGoogleStep1(Request $request, array $google): RedirectResponse
    {
        // Workspace accounts always carry a name, but never assume it.
        $askName = blank($google['first_name']) || blank($google['last_name']);

        $validated = $request->validate(array_filter([
            'first_name'    => $askName ? ['required', 'string', 'max:100'] : null,
            'last_name'     => $askName ? ['required', 'string', 'max:100'] : null,
            'edp_number'    => ['required', 'string', 'max:20'],
            'mobile_number' => ['required', 'string', 'max:20'],
            'year_level'    => ['required', 'integer', 'between:1,4'],
            'terms'         => ['accepted'],
        ]), self::STEP1_MESSAGES);

        if (User::where('email', $google['email'])->exists()) {
            session()->forget(['register.google', 'register.via_google']);

            return redirect()->route('login')->with('error',
                'An account with this email address already exists. Tap Continue with Google to sign in.');
        }

        // The Firebase account is made once. Coming back to edit the details after
        // that ("Edit my EDP number") keeps the one already made.
        $claimed = session('register.via_google')
            && session('register.firebase_uid')
            && session('register.email') === $google['email'];

        if ($claimed) {
            $account = [
                'localId'      => session('register.firebase_uid'),
                'idToken'      => session('register.id_token'),
                'refreshToken' => session('register.refresh_token'),
            ];
        } else {
            try {
                $account = $this->auth->signInWithGoogle($google['id_token']);
            } catch (\Throwable $e) {
                Log::error('Firebase registration with Google failed for ' . $google['email'] . ': ' . $e->getMessage());

                return back()->withInput()->with('error',
                    'We could not set up your account with Google right now. Please try again in a few minutes.');
            }

            // An earlier registration with a PASSWORD was started for this address and
            // never finished. Firebase hands it over only to that password, so it is
            // finished on the email-and-password form, which resumes it.
            if (! empty($account['needConfirmation']) || empty($account['localId']) || empty($account['idToken'])) {
                session()->forget(['register.google', 'register.via_google']);

                return redirect()->route('register')->withErrors(['email' =>
                    'This address already has an unfinished registration that uses a password. Please register below with '
                    . 'the same email and password to finish it, or reset the password with Forgot Password on the sign-in page.']);
            }
        }

        session([
            'register.via_google'      => true,
            'register.firebase_uid'    => $account['localId'],
            'register.id_token'        => $account['idToken'] ?? null,
            'register.refresh_token'   => $account['refreshToken'] ?? null,
            'register.first_name'      => $askName ? $validated['first_name'] : $google['first_name'],
            'register.last_name'       => $askName ? $validated['last_name'] : $google['last_name'],
            'register.edp_number'      => $validated['edp_number'],
            'register.email'           => $google['email'],
            'register.mobile_number'   => $validated['mobile_number'],
            'register.year_level'      => $validated['year_level'],
            'register.verify_attempts' => 0,
        ]);

        // Left over from a password registration started earlier in this session.
        session()->forget(['register.password', 'register.photo_url']);

        RegistrationDraft::saveFromSession();

        return redirect()->route('register.department')->with('info', self::TO_DEPARTMENT);
    }

    /**
     * The SCC Google account this registration is using, if any: one just chosen
     * (fresh for GoogleController::REGISTER_MINUTES), or — once step 1 has made the
     * Firebase account — the one recorded then, so "Edit my EDP number" still finds
     * the Google form after Google's answer has run out.
     *
     * @return array{email: string, first_name: ?string, last_name: ?string, id_token: ?string}|null
     */
    private function googleRegistration(): ?array
    {
        $google = session('register.google');

        if (is_array($google) && $google['expires'] >= time()) {
            return $google;
        }

        session()->forget('register.google');

        if (session('register.via_google') && session('register.firebase_uid')) {
            return [
                'email'      => session('register.email'),
                'first_name' => session('register.first_name'),
                'last_name'  => session('register.last_name'),
                'id_token'   => null,
            ];
        }

        return null;
    }

    /** "Register with email and password instead": drop the Google account chosen. */
    public function cancelGoogle(): RedirectResponse
    {
        if (session('register.via_google')) {
            RegistrationDraft::discard(session('register.firebase_uid'));
        }

        session()->forget(['register.google', 'register.via_google', 'register.firebase_uid',
            'register.id_token', 'register.refresh_token', 'register.email']);

        return redirect()->route('register');
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

        // A different department: the course picked under the old one no longer applies.
        if ((int) session('register.department_id') !== (int) $request->department_id) {
            session()->forget('register.course_id');
        }

        session(['register.department_id' => (int) $request->department_id]);

        RegistrationDraft::saveFromSession();

        $department = Department::find($request->department_id);

        return redirect()->route('register.course')->with('info',
            "Step 3 of 4: choose your course in the {$department->name}. Your course decides which academic club you join.");
    }

    public function showCourse(): RedirectResponse|View
    {
        if (! session('register.department_id')) {
            return redirect()->route('register.department');
        }

        // Each course's academic club, so the confirmation can say what the choice leads to.
        $department = Department::with(['courses.clubs' => fn ($q) => $q->where('club_type', 'Academic')->where('is_active', true)])
            ->findOrFail(session('register.department_id'));

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

        // The Firebase account was claimed at step 1. Without it in the session there
        // is no account to attach this to, and sending them back to the email field is
        // the only honest thing to do.
        if (! session('register.firebase_uid')) {
            return $this->sessionExpired();
        }

        session(['register.course_id' => $course->id]);

        RegistrationDraft::saveFromSession();

        return redirect()->route('register.verify')->with('info',
            'Step 4 of 4: upload your Certificate of Enrollment or the front of your school ID. Your account is created '
            . 'only after this step, so you can still change your department or course here.');
    }

    /**
     * The last step: proof that they are enrolled at SCC — their Certificate of
     * Enrollment or the front of their school ID, showing the same name, EDP number and
     * course they registered with (POLICY.md §Registration).
     */
    public function showVerify(): RedirectResponse|View
    {
        if (! session('register.firebase_uid')) {
            return $this->sessionExpired();
        }

        $course = Course::find(session('register.course_id'));

        if (! $course) {
            return redirect()->route('register.course');
        }

        $attempts = (int) session('register.verify_attempts', 0);

        return view('auth.register.verify', [
            'course'           => $course,
            'department'       => $course->department,
            'clubs'            => $course->clubs()->where('club_type', 'Academic')->where('is_active', true)->pluck('name'),
            'name'             => trim(session('register.first_name') . ' ' . session('register.last_name')),
            'edpNumber'        => session('register.edp_number'),
            'canRequestReview' => $attempts >= EnrollmentVerifier::ATTEMPTS_BEFORE_REVIEW,
            // The name of a Google registration is Google's, so only the EDP number is editable.
            'viaGoogle'        => (bool) session('register.via_google'),
        ]);
    }

    public function storeVerify(Request $request): RedirectResponse
    {
        if (! session('register.firebase_uid')) {
            return $this->sessionExpired();
        }

        $course = Course::find(session('register.course_id'));

        if (! $course) {
            return redirect()->route('register.course');
        }

        $request->validate(EnrollmentVerifier::rules(), EnrollmentVerifier::messages());

        try {
            $check = $this->verifier->check($request->file('document'), [
                'first_name' => (string) session('register.first_name'),
                'last_name'  => (string) session('register.last_name'),
                'edp_number' => (string) session('register.edp_number'),
            ], $course);
        } catch (UnreadableDocument $e) {
            return back()->withErrors(['document' => $e->getMessage()]);
        }

        $attempts = (int) session('register.verify_attempts', 0);

        // Only after the check has failed them more than once: a mistyped EDP number is
        // something they can fix themselves, and should, before a person is asked.
        $reviewRequested = $request->input('action') === 'review'
            && $attempts >= EnrollmentVerifier::ATTEMPTS_BEFORE_REVIEW;

        if ($check->failed() && ! $reviewRequested) {
            session(['register.verify_attempts' => $attempts + 1]);

            return back()->withErrors(['document' => array_values($check->problems)]);
        }

        // Everything from here creates the account, so it happens together or not at all.
        [$user, $clubs] = DB::transaction(function () use ($course, $check) {
            $user = $this->createAccount($course);

            if ($check->passed()) {
                return [$user, $this->verifier->markVerified($user, $check)];
            }

            $reason = $check->failed() ? EnrollmentVerification::REASON_STUDENT_REQUEST : $check->reviewReason();
            $this->verifier->sendForReview($user, $check, $reason);

            return [$user, null];
        });

        // The account holds it all now; the saved progress has done its job. Outside the
        // transaction, so a failure here can never undo the account.
        RegistrationDraft::discard($user->firebase_uid);

        $this->signIn($request, $user);

        if ($clubs === null) {
            return redirect()->route('verification.show')->with('success', $check->outcome === EnrollmentCheck::UNAVAILABLE
                ? 'Your account has been created. The automatic check is unavailable right now, so your proof of enrollment was '
                    . 'saved as Pending Verification. It will be checked automatically once the check is back, or by the Student '
                    . 'Affairs Office, whichever comes first.'
                : 'Your account has been created. Your proof of enrollment has been sent to the Student Affairs Office, '
                    . 'and you can use ClubSync as soon as they have checked it.');
        }

        return redirect()->route('member.dashboard')
            ->with('success', 'Welcome to ClubSync! Your enrollment has been verified. ' . $this->enrollment->summary($clubs, $course));
    }

    /**
     * The student account, from what the wizard collected. It starts `pending`: the
     * verifier, not this method, is what moves it on.
     */
    private function createAccount(Course $course): User
    {
        return User::create([
            'first_name'        => session('register.first_name'),
            'last_name'         => session('register.last_name'),
            'edp_number'        => session('register.edp_number'),
            'email'             => session('register.email'),
            // None for a Google registration: they sign in with Google (User::signsInWithGoogleOnly).
            'password'          => session('register.via_google') ? null : session('register.password'),
            'mobile_number'     => session('register.mobile_number'),
            'department_id'     => $course->department_id,
            'course_id'         => $course->id,
            'year_level'        => session('register.year_level'),
            'firebase_uid'      => session('register.firebase_uid'),
            'role'              => 'member',
            'profile_photo_url' => session('register.photo_url'),
            'enrollment_status' => EnrollmentVerification::STATUS_PENDING,
        ]);
    }

    /** Sign the new student in with the Firebase account claimed at step 1. */
    private function signIn(Request $request, User $user): void
    {
        // Firebase ID tokens last about an hour, and the one from step 1 may have
        // gone stale while the rest of the wizard was being filled in.
        $idToken      = session('register.id_token');
        $refreshToken = session('register.refresh_token');

        if ($refreshToken && (! $idToken || $this->auth->isTokenExpired($idToken))) {
            try {
                $idToken = $this->auth->refreshIdToken($refreshToken);
            } catch (\Throwable $e) {
                // Not fatal: the firebase.token middleware refreshes on the next request.
                Log::warning('Could not refresh the registration token for ' . $user->email . ': ' . $e->getMessage());
            }
        }

        session()->forget([
            'register.first_name', 'register.last_name', 'register.edp_number',
            'register.email', 'register.password', 'register.mobile_number',
            'register.year_level', 'register.department_id', 'register.photo_url',
            'register.firebase_uid', 'register.id_token', 'register.refresh_token',
            'register.course_id', 'register.verify_attempts', 'register.google', 'register.via_google',
        ]);

        $request->session()->regenerate();

        session([
            'course_selected'    => true,
            'enrollment_cleared' => $user->enrollment_status === EnrollmentVerification::STATUS_VERIFIED,
        ]);

        $this->session->store(
            (string) $idToken,
            $user->firebase_uid,
            'member',
            $user->id,
            refreshToken: $refreshToken,
        );
    }

    private function sessionExpired(): RedirectResponse
    {
        return redirect()->route('register')->withErrors([
            'email' => 'Your registration session expired before it was finished. Please start again.',
        ]);
    }
}
