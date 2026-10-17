<?php

use App\Models\AiNotificationQueue;
use App\Models\Attendance;
use App\Models\Club;
use App\Models\ClubActivity;
use App\Models\ClubMember;
use App\Models\Fee;
use App\Models\FeePayment;
use App\Models\MemberSemesterStatus;
use App\Models\Semester;
use App\Models\User;
use App\Services\ClubPerformanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/*
 * The DSA follows how each club is doing over the semester. This replaced the Churn
 * Risk Engine on the DSA side (POLICY.md §Role-Based Access): the figures are
 * club-level, and the churn engine itself is now officer-only.
 */

beforeEach(function () {
    // Mid-semester: 30 September, in a semester that runs August to December.
    Carbon::setTestNow('2026-09-30 10:00:00');

    $this->semester = Semester::create([
        'label'         => '1st Semester, A.Y. 2026–2027',
        'term'          => 'first',
        'academic_year' => '2026-2027',
        'start_date'    => '2026-08-10',
        'end_date'      => '2026-12-18',
        'is_current'    => true,
    ]);
});

afterEach(fn () => Carbon::setTestNow());

function cpUser(string $role): User
{
    static $n = 0;
    $n++;

    return User::create([
        'first_name'   => 'Cp',
        'last_name'    => "Tester{$n}",
        'edp_number'   => str_pad((string) (600 + $n), 10, '0', STR_PAD_LEFT),
        'email'        => "cp{$n}@sccpag.edu.ph",
        'password'     => Hash::make('Password1!'),
        'role'         => $role,
        'course_id'    => testCourse()->id,
        'is_admin'     => false,
        'is_suspended' => false,
    ]);
}

function cpSession(User $user): array
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

function cpClub(string $name): Club
{
    static $n = 0;
    $n++;

    return Club::create([
        'slug'            => 'cp-club-' . $n,
        'acronym'         => 'CP' . $n,
        'club_type'       => 'Non-Academic',
        'type'            => 'non_academic',
        'course_slug'     => 'all',
        'department_slug' => 'all',
        'name'            => $name,
        'is_active'       => true,
    ]);
}

/** @return array<int, ClubMember> */
function cpMembers(Club $club, int $count): array
{
    return collect(range(1, $count))->map(fn () => ClubMember::create([
        'club_id' => $club->id,
        'user_id' => cpUser('member')->id,
        'role'    => 'Member',
        'status'  => 'active',
    ]))->all();
}

/** A completed activity on $date, attended by $attendees. */
function cpHeld(Club $club, string $date, array $attendees = []): ClubActivity
{
    $activity = ClubActivity::create([
        'club_id'               => $club->id,
        'title'                 => 'Assembly ' . $date,
        'description'           => 'Held.',
        'date'                  => $date,
        'time_start'            => '09:00',
        'time_end'              => '11:00',
        'venue'                 => 'AVR',
        'purpose'               => 'Held.',
        'expected_participants' => 30,
        'activity_type'         => 'internal_meeting',
        'approval_status'       => 'no_approval_needed',
        'status'                => 'completed',
        'completed_at'          => $date . ' 11:00:00',
        'created_by'            => cpUser('president')->id,
    ]);

    foreach ($attendees as $member) {
        Attendance::create(['event_id' => $activity->id, 'user_id' => $member->user_id, 'time_in' => $date . ' 09:05:00']);
    }

    return $activity;
}

function cpRow(Club $club): array
{
    return app(ClubPerformanceService::class)->forClub($club, Semester::current()->first());
}

it('scores a club from its activities, turnout and presence updates', function () {
    $club    = cpClub('Busy Club');
    $members = cpMembers($club, 4);

    // Two held activities (the two expected by 30 September), 3 of 4 members at each.
    cpHeld($club, '2026-08-20', array_slice($members, 0, 3));
    cpHeld($club, '2026-09-15', array_slice($members, 1, 3));

    // Presence given to half the members.
    foreach (array_slice($members, 0, 2) as $member) {
        MemberSemesterStatus::create([
            'club_member_id' => $member->id, 'semester_id' => $this->semester->id,
            'semester_status' => 'active', 'updated_by' => $member->user_id,
        ]);
    }

    $row = cpRow($club);

    // activities 1.0×30 + turnout 0.75×25 + presence 0.5×15 = 56.25 of 70 (fees and
    // reports don't apply yet) → 80.
    expect($row['held'])->toBe(2)
        ->and($row['turnout'])->toBe(0.75)
        ->and($row['components']['fees']['applies'])->toBeFalse()
        ->and($row['components']['reports']['applies'])->toBeFalse()
        ->and($row['score'])->toBe(80)
        ->and($row['standing'])->toBe('strong')
        ->and($row['monthly']['2026-08']['held'])->toBe(1)
        ->and($row['monthly']['2026-09']['held'])->toBe(1)
        ->and($row['monthly']['2026-10']['future'])->toBeTrue();
});

it('marks a club that has held nothing as having no activities, and ranks it last', function () {
    $idle = cpClub('Idle Club');
    cpMembers($idle, 3);

    $busy    = cpClub('Busy Club');
    $members = cpMembers($busy, 2);
    cpHeld($busy, '2026-09-01', $members);

    $rows = app(ClubPerformanceService::class)->forClubs(collect([$idle, $busy]), $this->semester);

    expect($rows->pluck('club.id')->all())->toBe([$busy->id, $idle->id])
        ->and($rows[1]['standing'])->toBe('inactive')
        ->and($rows[1]['score'])->toBe(0)
        ->and($rows[1]['turnout'])->toBeNull();
});

it('counts fee collection only for fees already due', function () {
    $club    = cpClub('Fee Club');
    $members = cpMembers($club, 2);
    cpHeld($club, '2026-09-01', $members);

    $due   = Fee::create(['club_id' => $club->id, 'title' => 'Membership', 'amount' => 100, 'due_date' => '2026-09-20', 'academic_period' => '1st Sem']);
    $later = Fee::create(['club_id' => $club->id, 'title' => 'Shirt', 'amount' => 300, 'due_date' => '2026-11-20', 'academic_period' => '1st Sem']);

    FeePayment::create(['fee_id' => $due->id, 'user_id' => $members[0]->user_id, 'status' => 'paid']);
    FeePayment::create(['fee_id' => $due->id, 'user_id' => $members[1]->user_id, 'status' => 'pending']);
    FeePayment::create(['fee_id' => $later->id, 'user_id' => $members[0]->user_id, 'status' => 'pending']);

    $fees = cpRow($club)['components']['fees'];

    expect($fees['applies'])->toBeTrue()
        ->and($fees['value'])->toBe(0.5)
        ->and($fees['detail'])->toContain('1 of 2');
});

it('does not expect presence updates in the first two weeks of the semester', function () {
    Carbon::setTestNow('2026-08-15 10:00:00');

    $club = cpClub('New Club');
    cpMembers($club, 2);

    expect(cpRow($club)['components']['presence']['applies'])->toBeFalse();
});

it('takes points off for each compliance notice the club has not resolved', function () {
    $club    = cpClub('Noticed Club');
    $members = cpMembers($club, 4);
    cpHeld($club, '2026-08-20', array_slice($members, 0, 3));
    cpHeld($club, '2026-09-15', array_slice($members, 1, 3));
    $before = cpRow($club)['score'];

    AiNotificationQueue::create(['club_id' => $club->id, 'gap_type' => 'overdue_presence_update', 'status' => 'edited_sent', 'is_resolved' => false]);
    // Neither a resolved notice nor one the DSA never sent counts against the club.
    AiNotificationQueue::create(['club_id' => $club->id, 'gap_type' => 'overdue_activity_request', 'status' => 'edited_sent', 'is_resolved' => true]);
    AiNotificationQueue::create(['club_id' => $club->id, 'gap_type' => 'unupdated_officer_info', 'status' => 'pending_review', 'is_resolved' => false]);

    $row = cpRow($club);

    expect($row['open_notices'])->toBe(1)
        ->and($row['score'])->toBe($before - ClubPerformanceService::NOTICE_PENALTY);
});

it('gives no score before the semester has started', function () {
    Carbon::setTestNow('2026-08-01 10:00:00');

    $row = cpRow(cpClub('Early Club'));

    expect($row['score'])->toBeNull()->and($row['standing'])->toBe('not_started');
});

it('shows the DSA every active club, best first, with no member-level risk data', function () {
    $busy    = cpClub('Busy Club');
    $members = cpMembers($busy, 2);
    $members[0]->user->update(['last_name' => 'Quillfeather']);
    cpHeld($busy, '2026-09-01', $members);
    cpClub('Idle Club');

    $this->withSession(cpSession(cpUser('dsa')))
        ->get(route('dsa.club-performance.index'))
        ->assertOk()
        ->assertSeeInOrder(['Busy Club', 'Idle Club'])
        ->assertSee('No activities held')
        ->assertDontSee('Churn')
        ->assertDontSee('Quillfeather');
});

it("shows the DSA one club's semester, month by month, with its activities", function () {
    $club    = cpClub('Busy Club');
    $members = cpMembers($club, 2);
    cpHeld($club, '2026-09-01', $members);

    $this->withSession(cpSession(cpUser('dsa')))
        ->get(route('dsa.club-performance.show', $club))
        ->assertOk()
        ->assertSee('Activities held each month')
        ->assertSee('Assembly 2026-09-01')
        ->assertSee('2 attended');
});

it('is for the DSA only', function (string $role) {
    $this->withSession(cpSession(cpUser($role)))
        ->get(route('dsa.club-performance.index'))
        ->assertForbidden();
})->with(['member', 'president', 'adviser']);

it('puts Club Performance, not the Churn Risk Engine, in the DSA navigation', function () {
    $this->withSession(cpSession(cpUser('dsa')))
        ->get(route('dsa.dashboard'))
        ->assertOk()
        ->assertSee(route('dsa.club-performance.index'), false)
        ->assertDontSee(route('churn-risk'), false)
        ->assertDontSee('Churn Risk Engine');
});
