<?php

use App\Models\AuditLog;
use App\Models\Course;
use App\Models\Department;
use App\Models\EnrollmentVerification;
use App\Models\RegistrationDraft;
use App\Models\User;
use App\Services\DatabaseStorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/*
 * Registration tells the student where they are, reads their department and course back
 * to them before anything is created, lets them change either until the last step, and
 * keeps their progress so that signing in again resumes it (POLICY.md §Registration).
 * A student held at proof of enrollment can still correct a course picked by mistake.
 */

beforeEach(function () {
    config([
        'firebase.api_key'              => 'test-key',
        'firebase.sign_up_url'          => 'https://identitytoolkit.googleapis.com/v1/accounts:signUp',
        'firebase.sign_in_url'          => 'https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword',
        'firebase.sign_in_with_idp_url' => 'https://identitytoolkit.googleapis.com/v1/accounts:signInWithIdp',
        'google.sign_in.client_id'      => 'client-123.apps.googleusercontent.com',
        'google.sign_in.client_secret'  => 'client-secret',
        'gemini.api_key'                => 'test-key',
    ]);
});

/** An unsigned token that is not expired, so nothing tries to refresh it. */
function rzToken(string $uid = 'rz-uid'): string
{
    $part = fn (array $data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');

    return $part(['alg' => 'none']) . '.' . $part(['user_id' => $uid, 'exp' => time() + 3600]) . '.sig';
}

function rzOtherCourse(): Course
{
    $department = Department::firstOrCreate(['slug' => 'rz-other'], ['name' => 'Other College', 'short_name' => 'OC']);

    return Course::firstOrCreate(['slug' => 'rz-other-program'], ['name' => 'Other Program', 'department_id' => $department->id]);
}

/**
 * Firebase as registration and sign-in see it: step 1 claims a new account, and the
 * password 'Str0ng!Pass1' opens it again later. Gemini reads a COE matching rzPayload().
 */
function rzFakes(string $uid = 'rz-uid'): void
{
    Http::fake([
        '*accounts:signUp*' => Http::response(['localId' => $uid, 'idToken' => rzToken($uid), 'refreshToken' => 'rz-refresh']),
        '*accounts:signInWithPassword*' => fn ($request) => ($request->data()['password'] ?? null) === 'Str0ng!Pass1'
            ? Http::response(['localId' => $uid, 'idToken' => rzToken($uid), 'refreshToken' => 'rz-refresh-2'])
            : Http::response(['error' => ['message' => 'INVALID_LOGIN_CREDENTIALS']], 400),
        '*generativelanguage*' => Http::response(['candidates' => [[
            'content' => ['parts' => [['text' => json_encode([
                'document_type' => 'certificate_of_enrollment',
                'school_name'   => 'SAINT COLUMBAN COLLEGE',
                'full_name'     => 'DELA CRUZ, JUAN P',
                'edp_number'    => '0123456789',
                'course'        => 'FIXTURE PROGRAM',
                'course_id'     => testCourse()->id,
                'looks_altered' => false,
                'notes'         => '',
            ])]]],
            'finishReason' => 'STOP',
        ]]]),
    ]);
}

function rzPayload(array $overrides = []): array
{
    return array_merge([
        'first_name'            => 'Juan',
        'last_name'             => 'Dela Cruz',
        'edp_number'            => '0123456789',
        'email'                 => 'juan.delacruz@sccpag.edu.ph',
        'password'              => 'Str0ng!Pass1',
        'password_confirmation' => 'Str0ng!Pass1',
        'mobile_number'         => '09171234567',
        'year_level'            => 2,
        'terms'                 => 'on',
    ], $overrides);
}

/** Steps 1–3, as far as the proof of enrollment, then the browser is closed. */
function rzStopBeforeProof($test): void
{
    $course = testCourse();

    $test->post('/register', rzPayload());
    $test->post('/register/department', ['department_id' => $course->department_id]);
    $test->post('/register/course', ['course_id' => $course->id]);

    $test->flushSession();
}

function rzSignIn($test, string $password = 'Str0ng!Pass1')
{
    return $test->from(route('login'))->post('/login', ['email' => 'juan.delacruz@sccpag.edu.ph', 'password' => $password]);
}

function rzHeldStudent(string $status = EnrollmentVerification::STATUS_REJECTED): User
{
    return User::create([
        'first_name'        => 'Held',
        'last_name'         => 'Student',
        'edp_number'        => '0000007001',
        'email'             => 'held.student@sccpag.edu.ph',
        'password'          => Hash::make('Str0ng!Pass1'),
        'firebase_uid'      => 'held-uid',
        'role'              => 'member',
        'department_id'     => testCourse()->department_id,
        'course_id'         => testCourse()->id,
        'year_level'        => 1,
        'enrollment_status' => $status,
        'is_admin'          => false,
        'is_suspended'      => false,
    ]);
}

function rzSession(User $user): array
{
    return [
        'firebase_id_token'  => rzToken($user->firebase_uid),
        'firebase_uid'       => $user->firebase_uid,
        'firebase_user_role' => $user->role,
        'firebase_user_id'   => $user->id,
        'firebase_is_admin'  => false,
        'course_selected'    => true,
    ];
}

// ── Where am I? ────────────────────────────────────────────────────────────

it('tells the student which step they have reached, and that nothing is created before the last', function () {
    rzFakes();
    $course = testCourse();

    $this->get('/register')->assertOk()->assertSee('Step 1 of 4');

    $this->post('/register', rzPayload())
        ->assertRedirect(route('register.department'))
        ->assertSessionHas('info', fn ($text) => str_contains($text, 'Step 2 of 4'));
    $this->get('/register/department')->assertOk()->assertSee('Step 2 of 4')->assertSee('Back to my details');

    $this->post('/register/department', ['department_id' => $course->department_id])
        ->assertRedirect(route('register.course'))
        ->assertSessionHas('info', fn ($text) => str_contains($text, 'Step 3 of 4') && str_contains($text, 'Fixture College'));
    $this->get('/register/course')->assertOk()->assertSee('Step 3 of 4')->assertSee('Is this right?');

    $this->post('/register/course', ['course_id' => $course->id])
        ->assertRedirect(route('register.verify'))
        ->assertSessionHas('info', fn ($text) => str_contains($text, 'Step 4 of 4') && str_contains($text, 'only after this step'));

    $this->get('/register/verify')->assertOk()
        ->assertSee('Step 4 of 4')
        ->assertSee('Before you create your account, check your choice')
        ->assertSee('Fixture College')
        ->assertSee(route('register.department'), false)
        ->assertSee(route('register.course'), false);

    expect(User::count())->toBe(0);
});

it('keeps the department and course chosen when the student goes back to change them', function () {
    rzFakes();
    $course = testCourse();

    $this->post('/register', rzPayload());
    $this->post('/register/department', ['department_id' => $course->department_id]);
    $this->post('/register/course', ['course_id' => $course->id]);

    expect($this->get('/register/department')->getContent())
        ->toMatch('/id="dept_' . $course->department_id . '"[^>]*checked/');
    expect($this->get('/register/course')->getContent())
        ->toMatch('/id="course_' . $course->id . '"[^>]*checked/');
});

it('drops the course when the student picks a different department', function () {
    rzFakes();
    $course = testCourse();
    $other  = rzOtherCourse();

    $this->post('/register', rzPayload());
    $this->post('/register/department', ['department_id' => $course->department_id]);
    $this->post('/register/course', ['course_id' => $course->id]);

    $this->post('/register/department', ['department_id' => $other->department_id])->assertRedirect(route('register.course'));

    expect(session('register.course_id'))->toBeNull()
        ->and(RegistrationDraft::sole()->details['course_id'])->toBeNull();

    // Without a course there is no proof-of-enrollment step to reach.
    $this->get('/register/verify')->assertRedirect(route('register.course'));
});

// ── Saved progress ─────────────────────────────────────────────────────────

it('saves progress encrypted, and never the password or a Firebase token', function () {
    rzFakes();
    $course = testCourse();

    $this->post('/register', rzPayload());
    $this->post('/register/department', ['department_id' => $course->department_id]);

    $draft = RegistrationDraft::sole();

    expect($draft->firebase_uid)->toBe('rz-uid')
        ->and($draft->details['edp_number'])->toBe('0123456789')
        ->and($draft->details['department_id'])->toBe($course->department_id)
        ->and($draft->details)->not->toHaveKeys(['password', 'id_token', 'refresh_token'])
        ->and($draft->expires_at->isAfter(now()->addDays(13)))->toBeTrue();

    $raw = DB::table('registration_drafts')->first();

    expect($raw->details)->not->toContain('0123456789')
        ->and($raw->details)->not->toContain('juan.delacruz')
        ->and($raw->email_hash)->not->toContain('juan');
});

it('deletes the saved progress once the account is created', function () {
    rzFakes();
    $course = testCourse();

    $this->post('/register', rzPayload());
    $this->post('/register/department', ['department_id' => $course->department_id]);
    $this->post('/register/course', ['course_id' => $course->id]);
    expect(RegistrationDraft::count())->toBe(1);

    $this->post('/register/verify', ['document' => UploadedFile::fake()->image('coe.jpg', 600, 800)])
        ->assertRedirect(route('member.dashboard'));

    expect(User::sole()->enrollment_status)->toBe('verified')
        ->and(RegistrationDraft::count())->toBe(0);
});

// ── Signing in again ───────────────────────────────────────────────────────

it('takes a student who stopped before the proof of enrollment straight back to it when they sign in', function () {
    rzFakes();
    rzStopBeforeProof($this);

    rzSignIn($this)
        ->assertRedirect(route('register.verify'))
        ->assertSessionHas('info', fn ($text) => str_contains($text, 'not finished registering'));

    expect(session('register.email'))->toBe('juan.delacruz@sccpag.edu.ph')
        ->and(session('register.edp_number'))->toBe('0123456789')
        ->and(session('register.course_id'))->toBe(testCourse()->id)
        ->and(session('register.firebase_uid'))->toBe('rz-uid')
        ->and(session('register.password'))->toBe('Str0ng!Pass1')
        ->and(session('firebase_user_id'))->toBeNull();

    expect(AuditLog::where('action', 'auth.registration.resumed')->count())->toBe(1);

    $this->get('/register/verify')->assertOk()->assertSee('Fixture Program');

    // And finishing it from there creates the account as usual.
    $this->post('/register/verify', ['document' => UploadedFile::fake()->image('coe.jpg', 600, 800)])
        ->assertRedirect(route('member.dashboard'));

    expect(User::sole()->email)->toBe('juan.delacruz@sccpag.edu.ph')
        ->and(RegistrationDraft::count())->toBe(0);
});

it('takes a student back to the first step they had not answered', function () {
    rzFakes();

    $this->post('/register', rzPayload());
    $this->flushSession();

    rzSignIn($this)->assertRedirect(route('register.department'));
});

it('does not resume anything for a wrong password', function () {
    rzFakes();
    rzStopBeforeProof($this);

    rzSignIn($this, 'Wrong-pass1')
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => 'This email is not registered in ClubSync yet. Tap Register below to create your account.']);

    expect(session('register'))->toBeEmpty();
});

it('asks for the details again when the unfinished registration was started before progress was saved', function () {
    rzFakes();

    rzSignIn($this)
        ->assertRedirect(route('register'))
        ->assertSessionHas('info', fn ($text) => str_contains($text, 'did not finish'))
        ->assertSessionHasInput('email', 'juan.delacruz@sccpag.edu.ph');
});

it('lets saved progress run out, and prunes it', function () {
    rzFakes();
    rzStopBeforeProof($this);

    RegistrationDraft::query()->update(['expires_at' => now()->subMinute()]);

    rzSignIn($this)->assertRedirect(route('register'));
    expect(RegistrationDraft::count())->toBe(0);

    // The daily retention check removes any that nobody came back for.
    rzStopBeforeProof($this);
    RegistrationDraft::query()->update(['expires_at' => now()->subMinute()]);

    expect(app(DatabaseStorageService::class)->pruneTransient()['registration_drafts'])->toBe(1)
        ->and(RegistrationDraft::count())->toBe(0);
});

it('resumes an unfinished Google registration through Continue with Google', function () {
    $course = testCourse();
    $part   = fn (array $data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');
    $google = $part(['alg' => 'RS256']) . '.' . $part([
        'iss' => 'https://accounts.google.com', 'aud' => 'client-123.apps.googleusercontent.com', 'exp' => time() + 3600,
        'nonce' => 'nonce-abc', 'hd' => 'sccpag.edu.ph', 'email' => 'maria.santos@sccpag.edu.ph', 'email_verified' => true,
        'given_name' => 'Maria', 'family_name' => 'Santos',
    ]) . '.signature';

    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['id_token' => $google, 'access_token' => 'at']),
        '*accounts:signInWithIdp*'    => Http::response([
            'localId' => 'maria-google-uid', 'idToken' => rzToken('maria-google-uid'), 'refreshToken' => 'google-refresh',
        ]),
    ]);

    RegistrationDraft::create([
        'firebase_uid' => 'maria-google-uid',
        'email_hash'   => RegistrationDraft::hashEmail('maria.santos@sccpag.edu.ph'),
        'details'      => [
            'first_name' => 'Maria', 'last_name' => 'Santos', 'edp_number' => '0123456789',
            'email' => 'maria.santos@sccpag.edu.ph', 'mobile_number' => '09171234567', 'year_level' => 2,
            'via_google' => true, 'photo_url' => null, 'department_id' => $course->department_id, 'course_id' => $course->id,
        ],
        'expires_at' => now()->addDays(14),
    ]);

    $this->withSession(['google.oauth' => [
        'state' => 'state-xyz', 'nonce' => 'nonce-abc', 'verifier' => str_repeat('v', 64), 'remember' => false,
    ]])->get('/auth/google/callback?state=state-xyz&code=auth-code')
        ->assertRedirect(route('register.verify'))
        ->assertSessionHas('info', fn ($text) => str_contains($text, 'not finished registering'));

    expect(session('register.via_google'))->toBeTrue()
        ->and(session('register.firebase_uid'))->toBe('maria-google-uid')
        ->and(session('register.password'))->toBeNull();

    $this->get('/register/verify')->assertOk()->assertSee('Edit my EDP number');
});

// ── Correcting a course after the account exists ───────────────────────────

it('lets a student whose proof was sent back correct their course', function () {
    $student = rzHeldStudent();
    EnrollmentVerification::create(['user_id' => $student->id, 'status' => 'rejected', 'remarks' => 'The course does not match.']);
    $other = rzOtherCourse();

    $this->withSession(rzSession($student))->get(route('verification.show'))
        ->assertOk()->assertSee('Picked the wrong course? Change it');

    $this->withSession(rzSession($student))->get(route('verification.course'))
        ->assertOk()->assertSee('Change your course');

    $this->withSession(rzSession($student) + ['verification.attempts' => 2])
        ->post(route('verification.course.update'), ['department_id' => $other->department_id, 'course_id' => $other->id])
        ->assertRedirect(route('verification.show'))
        ->assertSessionHas('success');

    expect($student->fresh()->course_id)->toBe($other->id)
        ->and($student->fresh()->department_id)->toBe($other->department_id)
        ->and($student->fresh()->enrollment_status)->toBe('rejected')
        ->and(session('verification.attempts'))->toBeNull();

    $log = AuditLog::where('action', 'enrollment.course_changed')->sole();
    expect($log->metadata)->toMatchArray(['from_course_id' => testCourse()->id, 'to_course_id' => $other->id]);
});

it('does not change the course while the Student Affairs Office is checking the document', function () {
    $student = rzHeldStudent(EnrollmentVerification::STATUS_PENDING);
    EnrollmentVerification::create(['user_id' => $student->id, 'status' => 'pending']);
    $other = rzOtherCourse();

    $this->withSession(rzSession($student))->get(route('verification.course'))
        ->assertRedirect(route('verification.show'))
        ->assertSessionHas('info');

    $this->withSession(rzSession($student))
        ->post(route('verification.course.update'), ['department_id' => $other->department_id, 'course_id' => $other->id])
        ->assertRedirect(route('verification.show'));

    expect($student->fresh()->course_id)->toBe(testCourse()->id);
});

it('leaves a verified student\'s course alone', function () {
    $student = rzHeldStudent(EnrollmentVerification::STATUS_VERIFIED);
    $other   = rzOtherCourse();

    $this->withSession(rzSession($student))
        ->post(route('verification.course.update'), ['department_id' => $other->department_id, 'course_id' => $other->id])
        ->assertRedirect();

    expect($student->fresh()->course_id)->toBe(testCourse()->id);
});
