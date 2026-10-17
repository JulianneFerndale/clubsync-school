<?php

use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubMember;
use App\Models\ClubNotification;
use App\Models\Course;
use App\Models\Department;
use App\Models\EnrollmentVerification;
use App\Models\User;
use App\Services\EnrollmentVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

uses(RefreshDatabase::class);

/*
 * A new student proves they are enrolled at SCC before their account opens
 * (POLICY.md §Registration): a Certificate of Enrollment or the front of their school
 * ID, showing the same full name, EDP number and course they registered with.
 *
 * Gemini only READS the document. The comparison happens in EnrollmentVerifier, so
 * these tests fake what Gemini reads and check what ClubSync decides from it.
 */

beforeEach(function () {
    Storage::fake('local');

    config([
        'firebase.api_key'     => 'test-key',
        'firebase.sign_up_url' => 'https://identitytoolkit.googleapis.com/v1/accounts:signUp',
        'firebase.sign_in_url' => 'https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword',
        'gemini.api_key'       => 'test-key',
    ]);
});

/** An unsigned token that is not expired, so nothing tries to refresh it. */
function vfToken(): string
{
    $part = fn (array $data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');

    return $part(['alg' => 'none']) . '.' . $part(['exp' => time() + 3600]) . '.sig';
}

function vfCourse(): Course
{
    $department = Department::firstOrCreate(['slug' => 'ccs'], ['name' => 'College of Computing Studies', 'short_name' => 'CCS']);

    return Course::firstOrCreate(
        ['slug' => 'course_bsit'],
        ['name' => 'Bachelor of Science in Information Technology', 'department_id' => $department->id],
    );
}

function vfOtherCourse(): Course
{
    return Course::firstOrCreate(
        ['slug' => 'course_bscs'],
        ['name' => 'Bachelor of Science in Computer Science', 'department_id' => vfCourse()->department_id],
    );
}

/** What Gemini reads off a genuine COE for the student vfRegister() registers. */
function vfMatchingRead(array $overrides = []): array
{
    return array_merge([
        'document_type' => 'certificate_of_enrollment',
        'school_name'   => 'SAINT COLUMBAN COLLEGE',
        'full_name'     => 'FLORES, JULIANNE FERNDALE T',
        'edp_number'    => '199508',
        'course'        => 'BSIT',
        'course_id'     => vfCourse()->id,
        'looks_altered' => false,
        'notes'         => '',
    ], $overrides);
}

/** Firebase accepts the address; Gemini answers with $read (null = Gemini is down). */
function vfFake(?array $read): void
{
    Http::fake([
        '*accounts:signUp*' => Http::response([
            'localId'      => 'new-uid-123',
            'idToken'      => vfToken(),
            'refreshToken' => 'refresh-token-123',
        ]),
        '*generativelanguage*' => $read === null
            ? Http::response('upstream error', 503)
            : Http::response(['candidates' => [[
                'content'      => ['parts' => [['text' => json_encode($read)]]],
                'finishReason' => 'STOP',
            ]]]),
    ]);
}

/** Steps 1–3 of the wizard, leaving the student at the verification step. */
function vfRegister($test): void
{
    $course = vfCourse();

    $test->post('/register', [
        'first_name'            => 'Julianne Ferndale',
        'last_name'             => 'Flores',
        'edp_number'            => '199508',
        'email'                 => 'jf.flores@sccpag.edu.ph',
        'password'              => 'Str0ng!Pass1',
        'password_confirmation' => 'Str0ng!Pass1',
        'mobile_number'         => '09171234567',
        'year_level'            => 4,
        'terms'                 => 'on',
    ])->assertRedirect(route('register.department'));

    $test->post('/register/department', ['department_id' => $course->department_id]);
    $test->post('/register/course', ['course_id' => $course->id])->assertRedirect(route('register.verify'));
}

function vfPhoto(): UploadedFile
{
    return UploadedFile::fake()->image('coe.jpg', 600, 800);
}

function vfStudent(?string $status = EnrollmentVerification::STATUS_PENDING, string $email = 'jf.flores@sccpag.edu.ph'): User
{
    return User::create([
        'first_name'        => 'Julianne Ferndale',
        'last_name'         => 'Flores',
        'edp_number'        => '199508',
        'email'             => $email,
        'password'          => Hash::make('Str0ng!Pass1'),
        'firebase_uid'      => 'uid-' . $email,
        'role'              => 'member',
        'department_id'     => vfCourse()->department_id,
        'course_id'         => vfCourse()->id,
        'year_level'        => 4,
        'enrollment_status' => $status,
        'is_admin'          => false,
        'is_suspended'      => false,
    ]);
}

function vfStaff(string $role, bool $isAdmin = false): User
{
    static $n = 0;
    $n++;

    return User::create([
        'first_name'   => 'Staff',
        'last_name'    => "Member{$n}",
        'email'        => "staff{$n}@sccpag.edu.ph",
        'password'     => Hash::make('Str0ng!Pass1'),
        'firebase_uid' => "staff-uid-{$n}",
        'role'         => $role,
        'is_admin'     => $isAdmin,
        'is_suspended' => false,
    ]);
}

function vfSession(User $user): array
{
    return [
        'firebase_id_token'  => vfToken(),
        'firebase_uid'       => $user->firebase_uid,
        'firebase_user_role' => $user->role,
        'firebase_user_id'   => $user->id,
        'firebase_is_admin'  => $user->is_admin,
        'course_selected'    => true,
    ];
}

/** A case waiting for a person, as the verifier leaves it. */
function vfPendingCase(User $student): EnrollmentVerification
{
    $path = EnrollmentVerifier::ROOT . '/case.enc';
    Storage::disk('local')->put($path, Crypt::encryptString('fake-jpeg-bytes'));

    return EnrollmentVerification::create([
        'user_id'       => $student->id,
        'status'        => EnrollmentVerification::STATUS_PENDING,
        'review_reason' => EnrollmentVerification::REASON_AI_UNAVAILABLE,
        'document_path' => $path,
        'document_mime' => 'image/jpeg',
    ]);
}

function vfAcademicClub(): Club
{
    $club = Club::create([
        'slug' => 'byte', 'acronym' => 'BYTE', 'name' => 'Building the Youth through Technology Education',
        'type' => 'academic', 'club_type' => 'Academic', 'course_slug' => 'bsit', 'department_slug' => 'ccs', 'is_active' => true,
    ]);
    $club->courses()->attach(vfCourse()->id);

    return $club;
}

// ── The wizard asks for it ──────────────────────────────────────────────────

it('asks for proof of enrollment after the course instead of creating the account', function () {
    vfFake(vfMatchingRead());
    vfRegister($this);

    expect(User::count())->toBe(0);

    $this->get(route('register.verify'))
        ->assertOk()
        ->assertSee('Verify your enrollment')
        ->assertSee('199508')
        ->assertSee('Bachelor of Science in Information Technology');
});

it('does not let the verification step be reached without the earlier steps', function () {
    $this->get(route('register.verify'))->assertRedirect(route('register'));
    $this->post(route('register.verify'), ['document' => vfPhoto()])->assertRedirect(route('register'));

    expect(User::count())->toBe(0);
});

// ── A matching document ─────────────────────────────────────────────────────

it('verifies a matching COE, opens the account and keeps nothing of the document', function () {
    vfFake(vfMatchingRead());
    $club = vfAcademicClub();
    vfRegister($this);

    $this->post(route('register.verify'), ['document' => vfPhoto()])
        ->assertRedirect(route('member.dashboard'))
        ->assertSessionHas('success', fn ($m) => str_contains($m, 'enrollment has been verified'));

    $user = User::where('email', 'jf.flores@sccpag.edu.ph')->sole();

    expect($user->enrollment_status)->toBe('verified')
        ->and($user->firebase_uid)->toBe('new-uid-123')
        ->and(EnrollmentVerification::sole())
            ->status->toBe('verified')
            ->method->toBe('automatic')
            ->document_type->toBe('certificate_of_enrollment')
            ->document_path->toBeNull()
        ->and(Storage::disk('local')->allFiles())->toBe([])
        // A member of the academic club only now that they are verified — and at once.
        ->and(ClubMember::where('user_id', $user->id)->where('club_id', $club->id)->value('status'))->toBe('active')
        ->and(session('register.firebase_uid'))->toBeNull();

    $this->get(route('member.dashboard'))->assertOk();
});

it('accepts the front of the school ID, which prints the name first-name-first', function () {
    vfFake(vfMatchingRead(['document_type' => 'school_id', 'full_name' => 'JULIANNE FERNDALE T. FLORES']));
    vfRegister($this);

    $this->post(route('register.verify'), ['document' => vfPhoto()])->assertRedirect(route('member.dashboard'));

    expect(User::sole()->enrollment_status)->toBe('verified');
});

it('accepts a PDF certificate', function () {
    vfFake(vfMatchingRead());
    vfRegister($this);

    $pdf = UploadedFile::fake()->createWithContent('coe.pdf', "%PDF-1.4\n% fake certificate\n");

    $this->post(route('register.verify'), ['document' => $pdf])->assertRedirect(route('member.dashboard'));

    Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'generativelanguage')
        && $r['contents'][0]['parts'][0]['inline_data']['mime_type'] === 'application/pdf');
});

it('never tells Gemini what the student registered with', function () {
    vfFake(vfMatchingRead());
    vfRegister($this);

    $this->post(route('register.verify'), ['document' => vfPhoto()]);

    // It is asked to read the document, not to confirm an answer it was given.
    Http::assertSent(function (HttpRequest $r) {
        if (! str_contains($r->url(), 'generativelanguage')) {
            return false;
        }

        $prompt = $r['contents'][0]['parts'][1]['text'];

        return ! str_contains($prompt, '199508') && ! str_contains(strtolower($prompt), 'flores');
    });
});

// ── A document that does not match ──────────────────────────────────────────

it('refuses a document whose EDP number differs, and says which', function () {
    vfFake(vfMatchingRead(['edp_number' => '199509']));
    vfRegister($this);

    $this->from(route('register.verify'))
        ->post(route('register.verify'), ['document' => vfPhoto()])
        ->assertRedirect(route('register.verify'))
        ->assertSessionHasErrors('document');

    expect(session('errors')->get('document')[0])->toContain('EDP number on the document (199509)')
        ->and(User::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('refuses a different name', function () {
    vfFake(vfMatchingRead(['full_name' => 'DELA CRUZ, JUAN P']));
    vfRegister($this);

    $this->post(route('register.verify'), ['document' => vfPhoto()])->assertSessionHasErrors('document');

    expect(session('errors')->get('document')[0])->toContain('name on the document')
        ->and(User::count())->toBe(0);
});

it('refuses a different course', function () {
    vfFake(vfMatchingRead(['course' => 'BSCS', 'course_id' => vfOtherCourse()->id]));
    vfRegister($this);

    $this->post(route('register.verify'), ['document' => vfPhoto()])->assertSessionHasErrors('document');

    expect(session('errors')->get('document')[0])->toContain('course on the document (BSCS)')
        ->and(User::count())->toBe(0);
});

it('lists every field that did not match, not just the first', function () {
    vfFake(vfMatchingRead(['edp_number' => '111111', 'full_name' => 'SANTOS, MARIA']));
    vfRegister($this);

    $this->post(route('register.verify'), ['document' => vfPhoto()])->assertSessionHasErrors('document');

    expect(session('errors')->get('document'))->toHaveCount(2);
});

it('tells the student when they uploaded the back of their ID', function () {
    vfFake(vfMatchingRead(['document_type' => 'school_id_back', 'full_name' => 'GEMMA T. FLORES']));
    vfRegister($this);

    $this->post(route('register.verify'), ['document' => vfPhoto()])->assertSessionHasErrors('document');

    expect(session('errors')->get('document'))->toBe([
        'That is the back of your school ID. Upload the front, where your name, course and EDP number are printed.',
    ]);
});

it('refuses anything that is not an SCC COE or school ID', function () {
    vfFake(vfMatchingRead(['document_type' => 'other']));
    vfRegister($this);

    $this->post(route('register.verify'), ['document' => vfPhoto()])->assertSessionHasErrors('document');

    expect(session('errors')->get('document')[0])->toContain('does not look like a Saint Columban College')
        ->and(User::count())->toBe(0);
});

it('refuses another school\'s document', function () {
    vfFake(vfMatchingRead(['school_name' => 'WESTERN MINDANAO STATE UNIVERSITY']));
    vfRegister($this);

    $this->post(route('register.verify'), ['document' => vfPhoto()])->assertSessionHasErrors('document');

    expect(session('errors')->get('document')[0])->toContain('issued by Saint Columban College');
});

it('refuses a file that is not an image or a PDF', function () {
    vfFake(vfMatchingRead());
    vfRegister($this);

    $this->post(route('register.verify'), ['document' => UploadedFile::fake()->create('notes.docx', 20)])
        ->assertSessionHasErrors('document');

    Http::assertNotSent(fn (HttpRequest $r) => str_contains($r->url(), 'generativelanguage'));
});

// ── When a person has to decide ─────────────────────────────────────────────

it('sends the document to the DSA when the automatic check is unavailable', function () {
    vfFake(null);
    $dsa = vfStaff('dsa');
    vfRegister($this);

    $this->post(route('register.verify'), ['document' => vfPhoto()])
        ->assertRedirect(route('verification.show'));

    $user = User::where('role', 'member')->sole();
    $case = EnrollmentVerification::sole();

    expect($user->enrollment_status)->toBe('pending')
        ->and($case->status)->toBe('pending')
        ->and($case->review_reason)->toBe(EnrollmentVerification::REASON_AI_UNAVAILABLE)
        ->and(Storage::disk('local')->exists($case->document_path))->toBeTrue()
        // Encrypted at rest: what is on disk is not the image.
        ->and(Storage::disk('local')->get($case->document_path))->not->toStartWith("\xFF\xD8")
        ->and(EnrollmentVerifier::documentContents($case))->toStartWith("\xFF\xD8")
        ->and(ClubNotification::where('recipient_id', $dsa->id)->sole()->title)->toBe('Proof of enrollment to review')
        // Not put forward to any club until a person verifies them.
        ->and(ClubMember::where('user_id', $user->id)->count())->toBe(0);

    // Held on the verification page, whatever they try to open.
    $this->get(route('member.dashboard'))->assertRedirect(route('verification.show'));
    $this->get(route('verification.show'))->assertOk()->assertSee('being checked');
});

it('treats a document that looks edited as one for a person, not a pass', function () {
    vfFake(vfMatchingRead(['looks_altered' => true]));
    vfRegister($this);

    $this->post(route('register.verify'), ['document' => vfPhoto()])->assertRedirect(route('verification.show'));

    expect(EnrollmentVerification::sole()->review_reason)->toBe(EnrollmentVerification::REASON_FLAGGED)
        ->and(User::sole()->enrollment_status)->toBe('pending');
});

it('lets the student ask for a person only after the check has failed them twice', function () {
    vfFake(vfMatchingRead(['edp_number' => '199509']));
    vfRegister($this);

    // Asking straight away is just another check.
    $this->post(route('register.verify'), ['document' => vfPhoto(), 'action' => 'review'])->assertSessionHasErrors('document');
    $this->get(route('register.verify'))->assertDontSee('Send to the Student Affairs Office instead');

    $this->post(route('register.verify'), ['document' => vfPhoto()])->assertSessionHasErrors('document');
    $this->get(route('register.verify'))->assertSee('Send to the Student Affairs Office instead');

    $this->post(route('register.verify'), ['document' => vfPhoto(), 'action' => 'review'])
        ->assertRedirect(route('verification.show'));

    $case = EnrollmentVerification::sole();

    expect($case->review_reason)->toBe(EnrollmentVerification::REASON_STUDENT_REQUEST)
        // The reviewer sees what the check read and why it refused.
        ->and($case->findings['edp_number'])->toBe('199509')
        ->and($case->findings['problems'][0])->toContain('EDP number');

    $dsa = vfStaff('dsa');
    $this->flushSession();

    $this->withSession(vfSession($dsa))->get(route('dsa.verifications.show', $case))
        ->assertOk()
        ->assertSee('What the automatic check read')
        ->assertSee('199509')
        ->assertSee('Did not match');
});

it('holds a pending student at the verification page when they sign in', function () {
    $student = vfStudent();

    Http::fake(['*accounts:signInWithPassword*' => Http::response([
        'localId' => $student->firebase_uid, 'idToken' => vfToken(), 'refreshToken' => 'r',
    ])]);

    $this->post('/login', ['email' => $student->email, 'password' => 'Str0ng!Pass1'])
        ->assertRedirect(route('verification.show'));
});

it('does not hold students who registered before verification existed, or staff', function () {
    $legacy = vfStudent(status: null);
    $this->withSession(vfSession($legacy))->get(route('member.dashboard'))->assertOk();

    $dsa = vfStaff('dsa');
    $this->withSession(vfSession($dsa))->get(route('dsa.dashboard'))->assertOk();
});

// ── The DSA and administrators decide ───────────────────────────────────────

it('lets the DSA approve: the account opens, the document goes, the academic club membership starts', function () {
    $dsa     = vfStaff('dsa');
    $student = vfStudent();
    $case    = vfPendingCase($student);
    $club    = vfAcademicClub();

    $this->withSession(vfSession($dsa))->get(route('dsa.verifications.index'))->assertOk()->assertSee('Julianne Ferndale Flores');
    $this->withSession(vfSession($dsa))->get(route('dsa.verifications.show', $case))->assertOk();
    $document = $this->withSession(vfSession($dsa))->get(route('dsa.verifications.document', $case))
        ->assertOk()
        ->assertContent('fake-jpeg-bytes');

    expect($document->headers->get('Cache-Control'))->toContain('no-store');

    $this->withSession(vfSession($dsa))->post(route('dsa.verifications.approve', $case))
        ->assertRedirect(route('dsa.verifications.index'));

    $case->refresh();

    expect($student->fresh()->enrollment_status)->toBe('verified')
        ->and($case->status)->toBe('verified')
        ->and($case->method)->toBe('dsa')
        ->and($case->reviewed_by)->toBe($dsa->id)
        ->and($case->document_path)->toBeNull()
        ->and(Storage::disk('local')->allFiles())->toBe([])
        ->and(ClubMember::where('user_id', $student->id)->where('club_id', $club->id)->value('status'))->toBe('active')
        ->and(ClubNotification::where('recipient_id', $student->id)->where('title', 'Your enrollment is verified')->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'enrollment.approved')->where('actor_id', $dsa->id)->exists())->toBeTrue();

    // The document is gone for good.
    $this->withSession(vfSession($dsa))->get(route('dsa.verifications.document', $case))->assertNotFound();

    // And the student is in.
    $this->withSession(vfSession($student))->get(route('member.dashboard'))->assertOk();
});

it('requires a reason to reject, deletes the document, and lets the student try again', function () {
    $dsa     = vfStaff('dsa');
    $student = vfStudent();
    $case    = vfPendingCase($student);

    $this->withSession(vfSession($dsa))->post(route('dsa.verifications.reject', $case), ['remarks' => ''])
        ->assertSessionHasErrors('remarks');

    expect($case->fresh()->status)->toBe('pending');

    $this->withSession(vfSession($dsa))->post(route('dsa.verifications.reject', $case), [
        'remarks' => 'The photo is too blurry to read the EDP number',
    ])->assertRedirect(route('dsa.verifications.index'));

    expect($case->fresh())
            ->status->toBe('rejected')
            ->remarks->toBe('The photo is too blurry to read the EDP number.')
        ->and($student->fresh()->enrollment_status)->toBe('rejected')
        ->and(Storage::disk('local')->allFiles())->toBe([]);

    // The student sees why, and uploads a better one, which passes.
    $this->withSession(vfSession($student))->get(route('verification.show'))
        ->assertOk()
        ->assertSee('too blurry to read the EDP number');

    vfFake(vfMatchingRead());

    $this->withSession(vfSession($student))->post(route('verification.store'), ['document' => vfPhoto()])
        ->assertRedirect(route('member.dashboard'));

    expect($student->fresh()->enrollment_status)->toBe('verified');
});

it('lets an administrator decide too, recorded as the admin', function () {
    $admin   = vfStaff('adviser', isAdmin: true);
    $student = vfStudent();
    $case    = vfPendingCase($student);

    $this->withSession(vfSession($admin))->get(route('admin.verifications.show', $case))->assertOk();
    $this->withSession(vfSession($admin))->post(route('admin.verifications.approve', $case))
        ->assertRedirect(route('admin.verifications.index'));

    expect($case->fresh()->method)->toBe('admin')
        ->and($student->fresh()->enrollment_status)->toBe('verified');
});

it('shows a document to nobody but the DSA and administrators', function () {
    $student = vfStudent();
    $case    = vfPendingCase($student);
    $adviser = vfStaff('adviser');
    $other   = vfStudent('verified', 'other.student@sccpag.edu.ph');

    $this->withSession(vfSession($adviser))->get(route('dsa.verifications.document', $case))->assertForbidden();
    $this->withSession(vfSession($adviser))->get(route('admin.verifications.document', $case))->assertForbidden();
    $this->withSession(vfSession($other))->get(route('dsa.verifications.document', $case))->assertForbidden();

    // Not even their own, once it is with the DSA. (A fresh session: the one above
    // belonged to a verified student.)
    $this->flushSession();
    $this->withSession(vfSession($student))->get(route('dsa.verifications.document', $case))->assertRedirect(route('verification.show'));
});

it('does not decide the same case twice', function () {
    $dsa     = vfStaff('dsa');
    $student = vfStudent();
    $case    = vfPendingCase($student);

    $this->withSession(vfSession($dsa))->post(route('dsa.verifications.approve', $case));
    $this->withSession(vfSession($dsa))->post(route('dsa.verifications.reject', $case), ['remarks' => 'Changed my mind about it'])
        ->assertSessionHas('info');

    expect($case->fresh()->status)->toBe('verified');
});

// ── Matching ────────────────────────────────────────────────────────────────

it('matches names in any order, without initials, suffixes or exact spacing', function (string $printed, string $first, string $last, bool $expected) {
    expect(EnrollmentVerifier::nameMatches($printed, $first, $last))->toBe($expected);
})->with([
    'COE order'              => ['FLORES, JULIANNE FERNDALE T', 'Julianne Ferndale', 'Flores', true],
    'ID order'               => ['JULIANNE FERNDALE T. FLORES', 'Julianne Ferndale', 'Flores', true],
    'case and accents'       => ['PEÑA, JOSÉ MARI', 'Jose Mari', 'Peña', true],
    'spacing in surname'     => ['DELA CRUZ, JUAN P', 'Juan', 'Delacruz', true],
    'spacing the other way'  => ['DELACRUZ, JUAN P', 'Juan', 'De la Cruz', true],
    'initial typed in first' => ['FLORES, JULIANNE FERNDALE T', 'Julianne Ferndale T.', 'Flores', true],
    'suffix not printed'     => ['SANTOS, PEDRO', 'Pedro Jr.', 'Santos', true],
    'different surname'      => ['FLORES, JULIANNE FERNDALE T', 'Julianne Ferndale', 'Santos', false],
    'missing first name'     => ['FLORES, FERNDALE T', 'Julianne Ferndale', 'Flores', false],
    'surname inside another' => ['JULIANNE LI', 'Julianne', 'Lim', false],
    'short surname no token' => ['JULIANNE FLORES', 'Julianne', 'Li', false],
    'nothing printed'        => ['', 'Julianne', 'Flores', false],
]);

it('matches EDP numbers by their digits', function (string $printed, string $entered, bool $expected) {
    expect(EnrollmentVerifier::edpMatches($printed, $entered))->toBe($expected);
})->with([
    ['199508', '199508', true],
    ['199 508', '199508', true],
    ['0199508', '199508', true],
    ['199508', '199509', false],
    ['', '199508', false],
]);

it('matches a printed course abbreviation even when Gemini mapped it to nothing', function () {
    $course = vfCourse();

    expect(EnrollmentVerifier::courseMatches(['course' => 'BSIT', 'course_id' => null], $course))->toBeTrue()
        ->and(EnrollmentVerifier::courseMatches(['course' => 'BS-IT', 'course_id' => null], $course))->toBeTrue()
        ->and(EnrollmentVerifier::courseMatches(['course' => 'BSCS', 'course_id' => null], $course))->toBeFalse()
        ->and(EnrollmentVerifier::courseMatches(['course' => 'BSIT', 'course_id' => vfOtherCourse()->id], $course))->toBeFalse()
        ->and(EnrollmentVerifier::courseMatches(['course' => '', 'course_id' => null], $course))->toBeFalse();
});

// ── Pending Verification, re-checked once the AI is back ───────────────────

it('saves a document as Pending Verification when the check is unavailable, and says it will be re-checked', function () {
    Sleep::fake();
    vfFake(null);
    vfRegister($this);

    $this->post('/register/verify', ['document' => vfPhoto()])
        ->assertRedirect(route('verification.show'))
        ->assertSessionHas('success', fn (string $message) => str_contains($message, 'saved as Pending Verification'));

    $this->get(route('verification.show'))
        ->assertOk()->assertSee('Pending Verification')->assertSee('so it was saved as Pending Verification.');
});

it('re-checks a Pending Verification once the AI is back, and verifies a match', function () {
    $club    = vfAcademicClub();
    $student = vfStudent();
    $case    = vfPendingCase($student);
    $path    = $case->document_path;
    vfFake(vfMatchingRead());

    $this->artisan('ai:catch-up')->assertSuccessful();
    $case->refresh();

    expect($case->status)->toBe(EnrollmentVerification::STATUS_VERIFIED)
        ->and($case->method)->toBe('automatic')
        ->and($case->reviewed_by)->toBeNull()
        ->and($case->document_type)->toBe('certificate_of_enrollment')
        ->and($case->document_path)->toBeNull()
        ->and(Storage::disk('local')->exists($path))->toBeFalse()
        ->and($student->fresh()->enrollment_status)->toBe(EnrollmentVerification::STATUS_VERIFIED)
        ->and(ClubMember::where('club_id', $club->id)->where('user_id', $student->id)->where('status', 'active')->exists())->toBeTrue()
        ->and(ClubNotification::where('recipient_id', $student->id)->where('title', 'Your enrollment is verified')
            ->where('body', 'like', '%as soon as the automatic check was available again%')->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'enrollment.verified')->latest('id')->first()->metadata['recheck'] ?? null)->toBeTrue();

    // The re-check is sent the stored document, never what the student registered with.
    Http::assertSent(fn (HttpRequest $request) => str_contains($request->url(), 'generativelanguage')
        && ! str_contains(json_encode($request->data()), '199508'));
});

it('sends a re-check that does not match back to the student, without repeating what was read', function () {
    $student = vfStudent();
    $case    = vfPendingCase($student);
    vfFake(vfMatchingRead(['edp_number' => '777777']));

    $this->artisan('ai:catch-up')->assertSuccessful();
    $case->refresh();

    expect($case->status)->toBe(EnrollmentVerification::STATUS_REJECTED)
        ->and($case->method)->toBe('automatic')
        ->and($case->remarks)->toBe('The EDP number on the document does not match the one you entered.')
        ->and($case->document_path)->toBeNull()
        ->and($student->fresh()->enrollment_status)->toBe(EnrollmentVerification::STATUS_REJECTED)
        ->and(ClubNotification::where('recipient_id', $student->id)->where('title', 'Your proof of enrollment did not match')->exists())->toBeTrue()
        ->and(json_encode(AuditLog::where('action', 'enrollment.rejected')->latest('id')->first()->metadata))->not->toContain('777777');

    // They can upload another one straight away.
    $this->withSession(vfSession($student))->get(route('verification.show'))
        ->assertOk()->assertSee('Your last document was not accepted')->assertSee('Verify my enrollment');
});

it('keeps a re-checked document that looks edited with the DSA, and tells them', function () {
    $dsa     = vfStaff('dsa');
    $student = vfStudent();
    $case    = vfPendingCase($student);
    vfFake(vfMatchingRead(['looks_altered' => true]));

    $this->artisan('ai:catch-up')->assertSuccessful();
    $case->refresh();

    expect($case->status)->toBe(EnrollmentVerification::STATUS_PENDING)
        ->and($case->review_reason)->toBe(EnrollmentVerification::REASON_FLAGGED)
        ->and($case->document_path)->not->toBeNull()
        ->and($student->fresh()->enrollment_status)->toBe(EnrollmentVerification::STATUS_PENDING)
        ->and(ClubNotification::where('recipient_id', $dsa->id)->where('title', 'Proof of enrollment to review')
            ->where('body', 'like', '%flagged%')->exists())->toBeTrue();
});

it('leaves a Pending Verification waiting while the AI is still down', function () {
    Sleep::fake();
    $student = vfStudent();
    $case    = vfPendingCase($student);
    vfFake(null);

    $this->artisan('ai:catch-up')->assertSuccessful();

    expect($case->fresh()->status)->toBe(EnrollmentVerification::STATUS_PENDING)
        ->and($case->fresh()->review_reason)->toBe(EnrollmentVerification::REASON_AI_UNAVAILABLE)
        ->and(Storage::disk('local')->exists($case->document_path))->toBeTrue();
});

it('never re-checks a case a person has to decide', function (string $reason) {
    $case = vfPendingCase(vfStudent());
    $case->update(['review_reason' => $reason]);
    vfFake(vfMatchingRead());

    $this->artisan('ai:catch-up')->assertSuccessful();

    expect($case->fresh()->status)->toBe(EnrollmentVerification::STATUS_PENDING);
    Http::assertNotSent(fn (HttpRequest $request) => str_contains($request->url(), 'generativelanguage'));
})->with([EnrollmentVerification::REASON_STUDENT_REQUEST, EnrollmentVerification::REASON_FLAGGED]);

it('lets a person who decided while the re-check ran keep their decision', function () {
    $student = vfStudent();
    $case    = vfPendingCase($student);
    vfFake(vfMatchingRead());

    // The DSA rejects it while the AI is still reading the copy the re-check started from.
    EnrollmentVerification::whereKey($case->id)->update(['status' => EnrollmentVerification::STATUS_REJECTED, 'remarks' => 'Blurry.']);

    expect(app(EnrollmentVerifier::class)->recheck($case))->toBeNull()
        ->and($case->fresh()->status)->toBe(EnrollmentVerification::STATUS_REJECTED)
        ->and($case->fresh()->remarks)->toBe('Blurry.');
});

it('lets the DSA run the re-check from the queue, and shows what it decided', function () {
    $dsa     = vfStaff('dsa');
    $student = vfStudent();
    vfPendingCase($student);
    vfFake(vfMatchingRead());

    $this->withSession(vfSession($dsa))->get(route('dsa.verifications.index'))
        ->assertOk()->assertSee('Pending Verification only because the automatic check was unavailable')->assertSee('Check them now');

    $this->withSession(vfSession($dsa))->post(route('dsa.verifications.recheck'))
        ->assertRedirect(route('dsa.verifications.index'))
        ->assertSessionHas('success', fn (string $message) => str_contains($message, '1 verified'));

    $this->withSession(vfSession($dsa))->get(route('dsa.verifications.index'))
        ->assertOk()->assertSee('Automatic re-check, once the AI was back')->assertDontSee('Check them now');
});

it('tells the DSA when the check is still unavailable instead of trying', function () {
    $dsa = vfStaff('dsa');
    vfPendingCase(vfStudent());
    config(['gemini.api_key' => '']);
    Http::fake();

    $this->withSession(vfSession($dsa))->post(route('dsa.verifications.recheck'))
        ->assertSessionHas('info', fn (string $message) => str_contains($message, 'still unavailable'));

    Http::assertNothingSent();
});

it('lets an administrator run the re-check too, and nobody else', function () {
    $admin = vfStaff('adviser', isAdmin: true);
    vfPendingCase(vfStudent());
    vfFake(vfMatchingRead());

    $this->withSession(vfSession($admin))->post(route('admin.verifications.recheck'))
        ->assertRedirect(route('admin.verifications.index'));

    $this->withSession(vfSession(vfStaff('adviser')))->post(route('dsa.verifications.recheck'))->assertForbidden();
});
