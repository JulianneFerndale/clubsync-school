<?php

use App\Models\Course;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/*
 * An email address is checked against BOTH stores at the email field, before the
 * registration wizard moves on.
 *
 * `unique:users,email` only covers ClubSync's own table. An address that exists in
 * Firebase but never finished registering passed that rule, and the failure only
 * surfaced three screens later — after a department and a course had been picked —
 * as "We could not complete your registration. Please try again."
 *
 * Firebase will not answer "is this taken?" (email enumeration protection hides it),
 * but it refuses to create a duplicate, so the account is claimed at step 1 and the
 * refusal IS the check.
 */

beforeEach(function () {
    config([
        'firebase.api_key'     => 'test-key',
        'firebase.sign_up_url' => 'https://identitytoolkit.googleapis.com/v1/accounts:signUp',
        'firebase.sign_in_url' => 'https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword',
    ]);
});

/** Firebase accepts the new address. */
function regFirebaseFree(): void
{
    Http::fake([
        '*accounts:signUp*' => Http::response([
            'localId'      => 'new-uid-123',
            'idToken'      => 'id-token-123',
            'refreshToken' => 'refresh-token-123',
        ]),
    ]);
}

/**
 * Firebase already holds the address. $password is the one that opens it, or null
 * when nothing does — i.e. it belongs to somebody else.
 */
function regFirebaseTaken(?string $password = null): void
{
    Http::fake([
        '*accounts:signUp*' => Http::response(['error' => ['message' => 'EMAIL_EXISTS']], 400),

        '*accounts:signInWithPassword*' => function ($request) use ($password) {
            if ($password !== null && ($request->data()['password'] ?? null) === $password) {
                return Http::response([
                    'localId'      => 'orphan-uid-456',
                    'idToken'      => 'id-token-456',
                    'refreshToken' => 'refresh-token-456',
                ]);
            }

            return Http::response(['error' => ['message' => 'INVALID_LOGIN_CREDENTIALS']], 400);
        },
    ]);
}

function regPayload(array $overrides = []): array
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

/**
 * The last step: proof of enrollment, with Gemini reading a COE that matches
 * regPayload(). Registration only creates the account here now.
 */
function regVerify($test, Course $course)
{
    config(['gemini.api_key' => 'test-key']);

    Http::fake(['*generativelanguage*' => Http::response(['candidates' => [[
        'content' => ['parts' => [['text' => json_encode([
            'document_type' => 'certificate_of_enrollment',
            'school_name'   => 'SAINT COLUMBAN COLLEGE',
            'full_name'     => 'DELA CRUZ, JUAN P',
            'edp_number'    => '0123456789',
            'course'        => 'BSIT',
            'course_id'     => $course->id,
            'looks_altered' => false,
            'notes'         => '',
        ])]]],
        'finishReason' => 'STOP',
    ]]])]);

    return $test->post('/register/verify', [
        'document' => UploadedFile::fake()->image('coe.jpg', 600, 800),
    ]);
}

/** An existing ClubSync account, Firebase side included. */
function regExistingUser(string $email = 'taken@sccpag.edu.ph'): User
{
    return User::create([
        'first_name'   => 'Taken',
        'last_name'    => 'Account',
        'edp_number'   => '0999999999',
        'email'        => $email,
        'password'     => Hash::make('Str0ng!Pass1'),
        'role'         => 'member',
        'course_id'    => testCourse()->id,
        'firebase_uid' => 'existing-uid-000',
        'is_admin'     => false,
        'is_suspended' => false,
    ]);
}

// ── The address is free ─────────────────────────────────────────────────────

it('claims the Firebase account at the email step and moves on', function () {
    regFirebaseFree();

    $this->post('/register', regPayload())
        ->assertRedirect(route('register.department'))
        ->assertSessionHasNoErrors();

    // Carried forward, so the last step has nothing left to create.
    expect(session('register.firebase_uid'))->toBe('new-uid-123')
        ->and(session('register.refresh_token'))->toBe('refresh-token-123');
});

// ── The address is taken ────────────────────────────────────────────────────

it('refuses an address that already has a ClubSync account', function () {
    Http::fake();   // must not reach Firebase: the users table already answers
    regExistingUser('juan.delacruz@sccpag.edu.ph');

    $this->from(route('register'))
        ->post('/register', regPayload())
        ->assertRedirect(route('register'))
        ->assertSessionHasErrors('email');

    Http::assertNothingSent();
});

it('refuses an address held in Firebase but never finished, and says so at the email field', function () {
    // The reported bug: nothing in the users table, so `unique` passed and the
    // wizard let them through to department and course before failing.
    regFirebaseTaken();   // no password opens it — somebody else's address

    $response = $this->from(route('register'))->post('/register', regPayload());

    $response->assertRedirect(route('register'))
        ->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))->toContain('already registered');

    // It did not get as far as choosing anything.
    expect(session('register.email'))->toBeNull()
        ->and(session('register.firebase_uid'))->toBeNull()
        ->and(User::count())->toBe(0);
});

it('never lets a taken address reach the department or course step', function () {
    regFirebaseTaken();

    $this->post('/register', regPayload());

    // Both later steps bounce back to the start of the wizard.
    $this->get(route('register.department'))->assertRedirect(route('register'));
    $this->get(route('register.course'))->assertRedirect(route('register.department'));
});

// ── The half-finished account is the person's own ───────────────────────────

it('resumes a registration that created the Firebase half and stopped', function () {
    // Same address, and the password they just typed opens it: their own unfinished
    // attempt, so they are handed it back instead of being turned away.
    regFirebaseTaken('Str0ng!Pass1');

    $this->post('/register', regPayload())
        ->assertRedirect(route('register.department'))
        ->assertSessionHasNoErrors();

    expect(session('register.firebase_uid'))->toBe('orphan-uid-456');
});

// ── Finishing up ────────────────────────────────────────────────────────────

it('finishes registration without creating a second Firebase account', function () {
    regFirebaseFree();

    $department = Department::create(['slug' => 'ccs', 'name' => 'College of Computing Studies', 'short_name' => 'CCS']);
    $course     = Course::create(['slug' => 'bsit', 'name' => 'BS Information Technology', 'department_id' => $department->id]);

    $this->post('/register', regPayload());
    $this->post('/register/department', ['department_id' => $department->id]);

    // Only the step-1 signUp may have been sent.
    Http::assertSentCount(1);

    $this->post('/register/course', ['course_id' => $course->id])->assertRedirect(route('register.verify'));
    regVerify($this, $course);

    $user = User::where('email', 'juan.delacruz@sccpag.edu.ph')->firstOrFail();

    // regVerify() started a fresh recording: the last step sent no signUp of its own.
    expect(Http::recorded(fn ($request) => str_contains($request->url(), 'accounts:signUp')))->toHaveCount(0);

    expect($user->firebase_uid)->toBe('new-uid-123')
        ->and($user->course_id)->toBe($course->id)
        ->and($user->year_level)->toBe(2)
        ->and(session('register.firebase_uid'))->toBeNull();   // cleared on the way out
});

it('tells the new student which academic club they are now a member of', function () {
    regFirebaseFree();

    $department = Department::create(['slug' => 'ccs', 'name' => 'College of Computing Studies', 'short_name' => 'CCS']);
    $course     = Course::create(['slug' => 'bsit', 'name' => 'BS Information Technology', 'department_id' => $department->id]);
    $club       = \App\Models\Club::create([
        'slug' => 'byte', 'acronym' => 'BYTE Org', 'name' => 'Building the Youth through Technology Education',
        'type' => 'academic', 'club_type' => 'Academic', 'course_slug' => 'bsit', 'department_slug' => 'ccs', 'is_active' => true,
    ]);
    $club->courses()->attach($course->id);

    $this->post('/register', regPayload());
    $this->post('/register/department', ['department_id' => $department->id]);

    $this->post('/register/course', ['course_id' => $course->id]);

    regVerify($this, $course)
        ->assertRedirect(route('member.dashboard'))
        ->assertSessionHas('success', fn (string $message) => str_starts_with($message, 'Welcome to ClubSync!')
            && str_contains($message, 'You are now a member of ' . $club->name));

    $user = User::where('email', 'juan.delacruz@sccpag.edu.ph')->firstOrFail();

    expect(\App\Models\ClubNotification::where('recipient_id', $user->id)->sole()->title)
        ->toBe("Welcome to {$club->name}!")
        ->and(\App\Models\ClubMember::where('user_id', $user->id)->where('club_id', $club->id)->sole()->status)
        ->toBe('active');
});

it('tells the new student when their course maps to no club yet (POLICY §Registration)', function () {
    regFirebaseFree();

    $department = Department::create(['slug' => 'ccs', 'name' => 'College of Computing Studies', 'short_name' => 'CCS']);
    $course     = Course::create(['slug' => 'bsit', 'name' => 'BS Information Technology', 'department_id' => $department->id]);

    $this->post('/register', regPayload());
    $this->post('/register/department', ['department_id' => $department->id]);

    $this->post('/register/course', ['course_id' => $course->id]);

    regVerify($this, $course)
        ->assertSessionHas('success', fn (string $message) => str_contains($message, 'asked to review your club assignment'));
});

it('sends them back to the email field when the session lost the claimed account', function () {
    $department = Department::create(['slug' => 'cbe', 'name' => 'College of Business Education', 'short_name' => 'CBE']);
    $course     = Course::create(['slug' => 'bsba', 'name' => 'BS Business Administration', 'department_id' => $department->id]);

    // Straight to the last step with a session that never claimed an account.
    $this->withSession([
        'register.email'         => 'juan.delacruz@sccpag.edu.ph',
        'register.password'      => 'Str0ng!Pass1',
        'register.department_id' => $department->id,
    ])->post('/register/course', ['course_id' => $course->id])
        ->assertRedirect(route('register'))
        ->assertSessionHasErrors('email');

    expect(User::count())->toBe(0);
});

// ── The live check behind the field ─────────────────────────────────────────

it('reports an address that already has an account as it is typed', function () {
    regExistingUser('taken@sccpag.edu.ph');

    $this->postJson(route('register.check-email'), ['email' => 'Taken@sccpag.edu.ph'])
        ->assertOk()
        ->assertJson(['status' => 'taken']);
});

it('reports a non-institutional address as it is typed', function () {
    $this->postJson(route('register.check-email'), ['email' => 'someone@gmail.com'])
        ->assertOk()
        ->assertJson(['status' => 'invalid']);
});

it('says nothing about an address it holds no account for', function () {
    // Deliberately not "available": Firebase cannot be read without side effects,
    // so the only honest answer here is silence.
    $this->postJson(route('register.check-email'), ['email' => 'nobody@sccpag.edu.ph'])
        ->assertOk()
        ->assertJson(['status' => 'pending', 'message' => null]);
});

it('does not reach Firebase for the live check', function () {
    Http::fake();

    $this->postJson(route('register.check-email'), ['email' => 'nobody@sccpag.edu.ph'])->assertOk();

    Http::assertNothingSent();
});
