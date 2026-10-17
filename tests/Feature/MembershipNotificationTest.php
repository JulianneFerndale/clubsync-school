<?php

use App\Models\Club;
use App\Models\ClubMember;
use App\Models\ClubNotification;
use App\Models\ClubOfficer;
use App\Models\User;
use App\Services\AcademicClubEnrollment;
use App\Support\LiveBroadcast;
use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/*
 * Everyone in the membership chain hears about their step as it happens: officers when
 * a student asks to join, the adviser when officers submit, and the student and the
 * officers when the adviser decides. None of it may wait on a queue worker, which the
 * Render image does not run. So the queue here is the database one with no worker:
 * anything merely queued would sit in `jobs`, and the inbox would stay empty.
 */

beforeEach(fn () => config(['queue.default' => 'database']));
afterEach(fn () => LiveBroadcast::resume());

function mnUser(string $role, array $overrides = []): User
{
    static $n = 0;
    $n++;

    return User::create(array_merge([
        'first_name'   => 'Mn',
        'last_name'    => "Person{$n}",
        'edp_number'   => str_pad((string) (800 + $n), 10, '0', STR_PAD_LEFT),
        'email'        => "mn{$n}@sccpag.edu.ph",
        'password'     => Hash::make('Password1!'),
        'role'         => $role,
        'course_id'    => testCourse()->id,
        'is_admin'     => false,
        'is_suspended' => false,
    ], $overrides));
}

function mnSession(User $user): array
{
    $header  = rtrim(strtr(base64_encode(json_encode(['alg' => 'none'])), '+/', '-_'), '=');
    $payload = rtrim(strtr(base64_encode(json_encode(['exp' => time() + 3600])), '+/', '-_'), '=');

    return [
        'firebase_id_token'  => "{$header}.{$payload}.signature",
        'firebase_uid'       => 'uid-' . $user->id,
        'firebase_user_role' => $user->role,
        'firebase_user_id'   => $user->id,
        'firebase_is_admin'  => $user->is_admin,
        'course_selected'    => true,
    ];
}

/** A non-academic club with an adviser and, unless told otherwise, a president: [club, adviser, officer|null]. */
function mnClub(bool $withOfficer = true, string $type = 'non_academic'): array
{
    static $n = 0;
    $n++;

    $adviser = mnUser('adviser');
    $club = Club::create([
        'slug' => "mn-club-{$n}", 'acronym' => "MN{$n}", 'name' => "Debate Varsity {$n}",
        'type' => $type, 'club_type' => $type === 'academic' ? 'Academic' : 'Non-Academic',
        'course_slug' => 'all', 'department_slug' => 'all', 'is_active' => true, 'adviser_id' => $adviser->id,
    ]);

    $officer = null;
    if ($withOfficer) {
        $officer = mnUser('president');
        ClubOfficer::create(['club_id' => $club->id, 'user_id' => $officer->id, 'position' => 'president']);
    }

    return [$club, $adviser, $officer];
}

function mnInbox(User $user)
{
    return ClubNotification::where('recipient_id', $user->id)->get();
}

it('tells the officers when a student asks to join', function () {
    [$club, $adviser, $officer] = mnClub();
    $student = mnUser('member');

    $this->withSession(mnSession($student))->post(route('member.clubs.join', $club))->assertRedirect();

    $notice = mnInbox($officer)->sole();

    expect($notice->title)->toBe('New membership request')
        ->and($notice->body)->toContain($student->name)->toContain($club->name)->toContain('submit it to your adviser')
        ->and($notice->action_url)->toBe(route('clubs.members.index', [], false))
        ->and(mnInbox($adviser))->toBeEmpty();
});

it("tells the adviser instead when the club has no officers", function () {
    [$club, $adviser] = mnClub(withOfficer: false);

    $this->withSession(mnSession(mnUser('member')))->post(route('member.clubs.join', $club));

    expect(mnInbox($adviser)->sole()->body)->toContain('no officers yet, so you can approve it directly');
});

it('tells the adviser the moment the officers submit an applicant', function () {
    [$club, $adviser, $officer] = mnClub();
    $application = ClubMember::create(['club_id' => $club->id, 'user_id' => mnUser('member')->id, 'status' => 'pending']);

    $this->withSession(mnSession($officer))
        ->post(route('clubs.members.store'), ['member_ids' => [$application->id]])
        ->assertRedirect(route('clubs.members.index'));

    $notice = mnInbox($adviser)->sole();

    expect($notice->title)->toBe('Member registrations to review')
        ->and($notice->body)->toContain($officer->name)->toContain('for your approval');
});

it('tells the student and the officers when the adviser approves, and says who decided', function () {
    [$club, $adviser, $officer] = mnClub();
    $student     = mnUser('member');
    $application = ClubMember::create(['club_id' => $club->id, 'user_id' => $student->id, 'status' => 'pending', 'registration_status' => 'pending', 'submitted_by' => $officer->id]);

    $this->withSession(mnSession($adviser))->post(route('clubs.members.approve', $application))->assertRedirect();

    $welcome = mnInbox($student)->sole();
    $officerNotice = mnInbox($officer)->sole();

    expect($welcome->title)->toBe("Welcome to {$club->name}!")
        ->and($welcome->action_url)->toBe(route('member.clubs.show', $club, false))
        ->and($officerNotice->title)->toBe('New member approved')
        ->and($officerNotice->body)->toBe("{$student->name} is now a member of {$club->name}, approved by the club adviser.")
        ->and(mnInbox($adviser))->toBeEmpty() // not told about their own decision
        ->and(ClubNotification::where('body', 'like', '%DSA%')->exists())->toBeFalse();
});

it('tells the student why when the adviser rejects', function () {
    [$club, $adviser, $officer] = mnClub();
    $student     = mnUser('member');
    $application = ClubMember::create(['club_id' => $club->id, 'user_id' => $student->id, 'status' => 'pending', 'registration_status' => 'pending']);

    $this->withSession(mnSession($adviser))
        ->post(route('clubs.members.reject', $application), ['remarks' => 'Membership is full this semester.'])
        ->assertRedirect();

    expect(mnInbox($student)->sole()->body)->toContain('was not approved')->toContain('Reason: Membership is full this semester.')
        ->and(mnInbox($officer)->sole()->title)->toBe('Membership request rejected');
});

it('makes a course member of the academic club active at once, with nothing for officers or the adviser to approve', function () {
    [$club, $adviser, $officer] = mnClub(type: 'academic');
    $club->courses()->attach(testCourse()->id);
    $student = mnUser('member');

    app(AcademicClubEnrollment::class)->enroll($student, testCourse());

    $membership = ClubMember::where('club_id', $club->id)->where('user_id', $student->id)->sole();

    expect($membership->status)->toBe('active')
        ->and($membership->registration_status)->toBe('approved')
        ->and($membership->approved_by)->toBeNull()
        ->and($membership->approved_at)->not->toBeNull()
        ->and($membership->submitted_by)->toBeNull();
});

it('tells the officers and the adviser a course member joined, once, as information only', function () {
    [$club, $adviser, $officer] = mnClub(type: 'academic');
    $club->courses()->attach(testCourse()->id);
    $student = mnUser('member');

    app(AcademicClubEnrollment::class)->enroll($student, testCourse());
    app(AcademicClubEnrollment::class)->enroll($student, testCourse()); // re-running adds nothing

    foreach ([$officer, $adviser] as $staff) {
        expect(mnInbox($staff)->sole())
            ->title->toBe('New member joined')
            ->body->toContain('joined ' . $club->name)->toContain('nothing to approve');
    }
});

it('welcomes the student to their academic club, once', function () {
    [$club] = mnClub(type: 'academic');
    $club->courses()->attach(testCourse()->id);
    $student = mnUser('member');

    app(AcademicClubEnrollment::class)->enroll($student, testCourse());
    app(AcademicClubEnrollment::class)->enroll($student, testCourse());

    $notice = mnInbox($student)->sole();

    expect($notice->title)->toBe("Welcome to {$club->name}!")
        ->and($notice->body)->toContain('you are now a member')
        ->and($notice->club_id)->toBe($club->id)
        ->and($notice->action_url)->toBe(route('member.clubs.show', $club, false));
});

it('turns a Join already pressed for the course\'s club into the membership, but leaves an adviser\'s rejection alone', function () {
    [$club] = mnClub(type: 'academic');
    $club->courses()->attach(testCourse()->id);
    $waiting  = mnUser('member');
    $rejected = mnUser('member');

    ClubMember::create(['club_id' => $club->id, 'user_id' => $waiting->id, 'status' => 'pending', 'registration_status' => 'pending']);
    ClubMember::create(['club_id' => $club->id, 'user_id' => $rejected->id, 'status' => 'pending', 'registration_status' => 'rejected', 'dsa_remarks' => 'Not enrolled in this course.']);

    app(AcademicClubEnrollment::class)->enroll($waiting, testCourse());
    app(AcademicClubEnrollment::class)->enroll($rejected, testCourse());

    expect(ClubMember::where('user_id', $waiting->id)->sole()->status)->toBe('active')
        ->and(ClubMember::where('user_id', $rejected->id)->sole())
            ->status->toBe('pending')
            ->registration_status->toBe('rejected');
});

it('still sends a Join pressed by hand to the officers for approval', function () {
    [$club, , $officer] = mnClub(type: 'academic');
    $student = mnUser('member');

    $this->withSession(mnSession($student))->post(route('member.clubs.join', $club));

    expect(ClubMember::where('club_id', $club->id)->where('user_id', $student->id)->sole()->status)->toBe('pending')
        ->and(mnInbox($officer)->sole()->title)->toBe('New membership request');
});

it('tells the student when the officers send their application on to the adviser, and only once', function () {
    [$club, , $officer] = mnClub();
    $student     = mnUser('member');
    $application = ClubMember::create(['club_id' => $club->id, 'user_id' => $student->id, 'status' => 'pending']);

    $submit = fn () => $this->withSession(mnSession($officer))
        ->post(route('clubs.members.store'), ['member_ids' => [$application->id]]);

    $submit();
    $submit(); // pressed again while it is still with the adviser

    $notice = mnInbox($student)->sole();

    expect($notice->title)->toBe("{$club->name} officers accepted your application")
        ->and($notice->body)->toContain('club adviser for final approval');

    // Sent back rejected, then forwarded again: that is news to the student.
    $application->update(['registration_status' => 'rejected']);
    $submit();

    expect(mnInbox($student))->toHaveCount(2);
});

it('marks a club the student has only applied to as pending on their dashboard', function () {
    [$pendingClub] = mnClub(type: 'academic');
    [$activeClub]  = mnClub();
    $student = mnUser('member');
    ClubMember::create(['club_id' => $pendingClub->id, 'user_id' => $student->id, 'status' => 'pending']);
    ClubMember::create(['club_id' => $activeClub->id, 'user_id' => $student->id, 'status' => 'active']);

    $html = $this->withSession(mnSession($student) + ['course_selected' => true])
        ->get(route('member.dashboard'))
        ->assertOk()
        ->getContent();

    expect(substr_count($html, 'membership pending approval'))->toBe(1)
        ->and($html)->toContain("{$pendingClub->acronym}, membership pending approval");
});

it('gives the polling page the newest unread notification to show', function () {
    $user = mnUser('member');
    ClubNotification::create(['recipient_id' => $user->id, 'sender_type' => 'system', 'title' => 'Older', 'body' => 'a']);
    $newest = ClubNotification::create(['recipient_id' => $user->id, 'sender_type' => 'system', 'title' => 'Welcome!', 'body' => 'b', 'action_url' => '/member/clubs/1']);
    ClubNotification::create(['recipient_id' => $user->id, 'sender_type' => 'system', 'title' => 'Read already', 'body' => 'c', 'is_read' => true]);

    $this->withSession(mnSession($user))->getJson(route('live.counts'))
        ->assertOk()
        ->assertJsonPath('notifications', 2)
        ->assertJsonPath('latest.id', $newest->id)
        ->assertJsonPath('latest.title', 'Welcome!')
        ->assertJsonPath('latest.action_url', '/member/clubs/1');
});

it('stops trying to push for a while once the live server is unreachable', function () {
    $attempts = new ArrayObject();
    Broadcast::extend('unreachable', fn () => new class($attempts) extends Broadcaster {
        public function __construct(private ArrayObject $attempts) {}
        public function auth($request) {}
        public function validAuthenticationResponse($request, $result) {}
        public function broadcast(array $channels, $event, array $payload = [])
        {
            $this->attempts->append($event);
            throw new RuntimeException('Failed to connect to localhost:8080');
        }
    });
    config(['broadcasting.default' => 'unreachable', 'broadcasting.connections.unreachable' => ['driver' => 'unreachable']]);

    $user = mnUser('member');
    foreach (range(1, 3) as $i) {
        ClubNotification::create(['recipient_id' => $user->id, 'sender_type' => 'system', 'title' => "N{$i}", 'body' => 'x']);
    }

    expect(count($attempts))->toBe(1)               // one failed try, then paused
        ->and(mnInbox($user))->toHaveCount(3)      // the notifications themselves are all kept
        ->and(LiveBroadcast::isDown())->toBeTrue();

    LiveBroadcast::resume();
    ClubNotification::create(['recipient_id' => $user->id, 'sender_type' => 'system', 'title' => 'N4', 'body' => 'x']);

    expect(count($attempts))->toBe(2);
});
