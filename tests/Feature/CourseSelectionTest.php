<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\ClubNotification;
use App\Models\Course;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/*
 * A signed-in student with no course on record is held at the course step.
 *
 * The course is what queues a student for their academic club (POLICY.md
 * §Registration), so an account without one belongs to no club and never will until
 * somebody asks. Registration collects it, but an account can still arrive without
 * one — a resumed half-finished registration, a record made outside the wizard, or a
 * course cleared since — and before this it simply carried on, clubless and silent.
 */

function csUser(string $role = 'member', ?int $courseId = null): User
{
    static $n = 0;
    $n++;

    return User::create([
        'first_name'   => 'Course',
        'last_name'    => "Tester{$n}",
        'edp_number'   => str_pad((string) (700 + $n), 10, '0', STR_PAD_LEFT),
        'email'        => "cs{$n}@sccpag.edu.ph",
        'password'     => Hash::make('Str0ng!Pass1'),
        'role'         => $role,
        'course_id'    => $courseId,
        'is_admin'     => false,
        'is_suspended' => false,
    ]);
}

function csSession(User $user): array
{
    $header  = rtrim(strtr(base64_encode(json_encode(['alg' => 'none'])), '+/', '-_'), '=');
    $payload = rtrim(strtr(base64_encode(json_encode(['exp' => time() + 3600])), '+/', '-_'), '=');

    return [
        'firebase_id_token'  => "{$header}.{$payload}.signature",
        'firebase_uid'       => 'uid-' . $user->id,
        'firebase_user_role' => $user->role,
        'firebase_user_id'   => $user->id,
        'firebase_is_admin'  => $user->is_admin,
    ];
}

/** A course, and the academic club mapped to it unless $mapped is false. */
function csCourse(bool $mapped = true): Course
{
    static $n = 0;
    $n++;

    $department = Department::firstOrCreate(['slug' => 'cs-college'], ['name' => 'CS College', 'short_name' => 'CSC']);
    $course     = Course::create(['slug' => "cs-course-{$n}", 'name' => "CS Program {$n}", 'department_id' => $department->id]);

    if ($mapped) {
        $club = Club::create([
            'slug'            => "cs-club-{$n}",
            'acronym'         => "CSC{$n}",
            'club_type'       => 'Academic',
            'type'            => 'academic',
            'course_slug'     => $course->slug,
            'department_slug' => $department->slug,
            'name'            => "CS Academic Club {$n}",
            'is_active'       => true,
        ]);

        $club->courses()->attach($course->id);
    }

    return $course;
}

// ── Who gets held, and who does not ─────────────────────────────────────────

it('sends a signed-in student with no course to the course step', function () {
    $student = csUser('member');

    $this->withSession(csSession($student))
        ->get(route('member.dashboard'))
        ->assertRedirect(route('onboarding.course'));
});

it('holds them there from any page, not just the dashboard', function (string $route) {
    $student = csUser('member');

    $this->withSession(csSession($student))
        ->get(route($route))
        ->assertRedirect(route('onboarding.course'));
})->with(['member.dashboard', 'member.bulletin.index', 'profile']);

it('holds an officer with no course too', function () {
    // Officers are students as well; the club they run is not the club their course
    // maps to.
    $officer = csUser('president');

    $this->withSession(csSession($officer))
        ->get(route('member.dashboard'))
        ->assertRedirect(route('onboarding.course'));
});

it('lets staff through, who have no course and never will', function (string $role) {
    $staff = csUser($role);

    $this->withSession(csSession($staff))
        ->get(route($role === 'dsa' ? 'dsa.dashboard' : 'adviser.dashboard'))
        ->assertOk();
})->with(['dsa', 'adviser']);

it('leaves a student who already has a course alone', function () {
    $student = csUser('member', csCourse()->id);

    $this->withSession(csSession($student))
        ->get(route('member.dashboard'))
        ->assertOk();
});

it('does not trap a student who already has a course on the course step', function () {
    $student = csUser('member', csCourse()->id);

    $this->withSession(csSession($student))
        ->get(route('onboarding.course'))
        ->assertRedirect(route('member.dashboard'));
});

it('leaves guests alone', function () {
    $this->get(route('login'))->assertOk();
    $this->get(route('register'))->assertOk();
});

it('answers a JSON request with a 409 and where to go', function () {
    $student = csUser('member');

    $this->withSession(csSession($student))
        ->getJson(route('live.counts'))
        ->assertStatus(409)
        ->assertJson(['redirect' => route('onboarding.course')]);
});

// ── Answering it ────────────────────────────────────────────────────────────

it('shows the course step with the colleges and their courses', function () {
    $course  = csCourse();
    $student = csUser('member');

    $this->withSession(csSession($student))
        ->get(route('onboarding.course'))
        ->assertOk()
        ->assertSee('One more step')
        ->assertSee($course->department->name)
        ->assertSee($course->name);
});

it('records the course and queues them for the mapped academic club', function () {
    $course  = csCourse();
    $club    = Club::where('club_type', 'Academic')->firstOrFail();
    $student = csUser('member');

    $this->withSession(csSession($student))
        ->post(route('onboarding.course.store'), [
            'department_id' => $course->department_id,
            'course_id'     => $course->id,
        ])->assertRedirect(route('member.dashboard'));

    $student->refresh();

    expect($student->course_id)->toBe($course->id)
        ->and($student->department_id)->toBe($course->department_id)
        ->and($student->pending_club_assignment)->toBeFalse();

    // POLICY: queued, not enrolled — officers accept, then the adviser approves.
    $membership = ClubMember::where('user_id', $student->id)->where('club_id', $club->id)->first();

    expect($membership)->not->toBeNull()
        ->and($membership->status)->toBe('pending');
});

it('stops holding them once the course is recorded', function () {
    $course  = csCourse();
    $student = csUser('member');

    $session = csSession($student);

    $this->withSession($session)->post(route('onboarding.course.store'), [
        'department_id' => $course->department_id,
        'course_id'     => $course->id,
    ]);

    $this->withSession($session)->get(route('member.dashboard'))->assertOk();
});

it('refuses a course that belongs to another college', function () {
    $course = csCourse();
    $other  = Department::create(['slug' => 'other-college', 'name' => 'Other College', 'short_name' => 'OC']);

    $student = csUser('member');

    $this->withSession(csSession($student))
        ->post(route('onboarding.course.store'), [
            'department_id' => $other->id,
            'course_id'     => $course->id,
        ])->assertSessionHasErrors('course_id');

    expect($student->fresh()->course_id)->toBeNull();
});

it('requires a course to be chosen at all', function () {
    $student = csUser('member');

    $this->withSession(csSession($student))
        ->post(route('onboarding.course.store'), [])
        ->assertSessionHasErrors(['department_id', 'course_id']);
});

// ── A course mapped to no club ──────────────────────────────────────────────

it('flags the student and alerts the DSA when the course maps to no club', function () {
    // POLICY.md §Registration: complete registration, flag the profile as Pending
    // Club Assignment, and alert the DSA Admin.
    $course  = csCourse(mapped: false);
    $dsa     = csUser('dsa');
    $student = csUser('member');

    $this->withSession(csSession($student))
        ->post(route('onboarding.course.store'), [
            'department_id' => $course->department_id,
            'course_id'     => $course->id,
        ])->assertRedirect();

    $student->refresh();

    expect($student->course_id)->toBe($course->id)          // registration still completes
        ->and($student->pending_club_assignment)->toBeTrue()
        ->and(ClubMember::where('user_id', $student->id)->count())->toBe(0);

    $alert = ClubNotification::where('recipient_id', $dsa->id)->latest('id')->first();

    expect($alert)->not->toBeNull()
        ->and($alert->title)->toContain('pending club assignment')
        ->and($alert->body)->toContain($course->name);
});

it('clears the flag when the student later moves to a mapped course', function () {
    $unmapped = csCourse(mapped: false);
    $mapped   = csCourse();
    $student  = csUser('member');

    $this->withSession(csSession($student))->post(route('onboarding.course.store'), [
        'department_id' => $unmapped->department_id,
        'course_id'     => $unmapped->id,
    ]);

    expect($student->fresh()->pending_club_assignment)->toBeTrue();

    // An admin clears the course; the student picks a mapped one next time round.
    $student->forceFill(['course_id' => null])->save();

    $this->withSession(csSession($student))->post(route('onboarding.course.store'), [
        'department_id' => $mapped->department_id,
        'course_id'     => $mapped->id,
    ]);

    expect($student->fresh()->pending_club_assignment)->toBeFalse();
});
