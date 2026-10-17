<?php

use App\Events\NotificationCreated;
use App\Jobs\NotifyMemberOfAttendance;
use App\Models\Attendance;
use App\Models\Club;
use App\Models\ClubActivity;
use App\Models\ClubMember;
use App\Models\ClubNotification;
use App\Models\ClubOfficer;
use App\Models\User;
use App\Support\LiveBroadcast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/*
 * Every time an officer records a member's attendance — checked in, checked out, or a
 * record removed — the member is notified, pushed live (NotificationCreated), so they
 * know they were logged. When the activity is marked completed the attendance is
 * concluded, and everyone who was checked in is told what their record finally says.
 */

beforeEach(function () {
    LiveBroadcast::resume();
    Event::fake([NotificationCreated::class]);
    // Completing an activity also queues its AI recap; keep these tests off the real Gemini API.
    config(['gemini.api_key' => '']);
    Http::preventStrayRequests();
});

function atUser(string $role = 'member'): User
{
    static $n = 0;
    $n++;

    return User::create([
        'first_name'   => 'Attend',
        'last_name'    => "Member{$n}",
        'edp_number'   => str_pad((string) (9300 + $n), 10, '0', STR_PAD_LEFT),
        'email'        => "attend.member{$n}@sccpag.edu.ph",
        'password'     => Hash::make('Str0ng!Pass1'),
        'role'         => $role,
        'course_id'    => testCourse()->id,
        'is_admin'     => false,
        'is_suspended' => false,
    ]);
}

function atSession(User $user): array
{
    $part = fn (array $data) => rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');

    return [
        'firebase_id_token'  => $part(['alg' => 'none']) . '.' . $part(['exp' => time() + 3600]) . '.sig',
        'firebase_uid'       => 'uid-' . $user->id,
        'firebase_user_role' => $user->role,
        'firebase_user_id'   => $user->id,
        'firebase_is_admin'  => false,
        'course_selected'    => true,
    ];
}

/** A club with a president, two active members and a scheduled activity: [activity, president, member, other]. */
function atActivity(): array
{
    static $n = 0;
    $n++;

    $club = Club::create([
        'slug' => "attend-club-{$n}", 'acronym' => "ATC{$n}", 'club_type' => 'Academic', 'type' => 'academic',
        'course_slug' => 'all', 'department_slug' => 'all', 'name' => "Attendance Club {$n}", 'is_active' => true,
    ]);

    $president = atUser('president');
    ClubOfficer::create(['club_id' => $club->id, 'user_id' => $president->id, 'position' => 'president']);

    $members = [$president, atUser(), atUser()];
    foreach ($members as $user) {
        ClubMember::create([
            'club_id' => $club->id, 'user_id' => $user->id, 'role' => 'member',
            'status' => 'active', 'registration_status' => 'approved', 'joined_at' => now()->subMonth(),
        ]);
    }

    $activity = ClubActivity::create([
        'club_id' => $club->id, 'title' => 'Parliamentary Procedure Training', 'description' => 'A skills training.',
        'date' => today()->toDateString(), 'time_start' => '13:00', 'time_end' => '16:00', 'venue' => 'Moot Court',
        'purpose' => 'To train members.', 'expected_participants' => 3, 'activity_type' => 'internal_meeting',
        'approval_status' => 'no_approval_needed', 'status' => 'scheduled', 'created_by' => $president->id,
    ]);

    return [$activity, $president, $members[1], $members[2]];
}

function atRecord(ClubActivity $activity, User $officer, User $member, string $action)
{
    return test()->withSession(atSession($officer))
        ->post(route('officer.activities.attendance.record', [$activity, $member]), ['action' => $action]);
}

function atNotices(User $user)
{
    return ClubNotification::where('recipient_id', $user->id)->orderBy('id')->get();
}

it('tells the member, live, that they were checked in', function () {
    [$activity, $president, $member] = atActivity();

    atRecord($activity, $president, $member, 'in')->assertRedirect();

    $notice = atNotices($member)->sole();

    expect($notice->title)->toBe('Checked in to attendance')
        ->and($notice->body)->toContain('"Parliamentary Procedure Training"')->toContain('checked in')
        ->and($notice->club_id)->toBe($activity->club_id)
        ->and($notice->action_url)->toBe(route('member.activity', ['tab' => 'attendance'], false));

    Event::assertDispatched(NotificationCreated::class, fn ($event) => $event->notification->recipient_id === $member->id);
});

it('tells the member they were checked out, with their whole record', function () {
    [$activity, $president, $member] = atActivity();

    atRecord($activity, $president, $member, 'in');
    atRecord($activity, $president, $member, 'out');

    $out = atNotices($member)->last();
    $record = Attendance::where('event_id', $activity->id)->where('user_id', $member->id)->first();

    expect(atNotices($member))->toHaveCount(2)
        ->and($out->title)->toBe('Checked out of attendance')
        ->and($out->body)->toContain('in ' . $record->time_in->format('g:i A'))->toContain('out ' . $record->time_out->format('g:i A'));
});

it('does not announce a check-out for a member who was never checked in', function () {
    [$activity, $president, $member] = atActivity();

    atRecord($activity, $president, $member, 'out');

    expect(atNotices($member))->toBeEmpty();
});

it('tells the member when their record is removed, and says nothing when there was none', function () {
    [$activity, $president, $member, $other] = atActivity();

    atRecord($activity, $president, $member, 'in');
    atRecord($activity, $president, $member, 'undo');
    atRecord($activity, $president, $other, 'undo');

    expect(atNotices($member)->last()->title)->toBe('Attendance record removed')
        ->and(atNotices($other))->toBeEmpty();
});

it('does not notify the officer about their own attendance', function () {
    [$activity, $president] = atActivity();

    atRecord($activity, $president, $president, 'in');
    atRecord($activity, $president, $president, 'out');

    expect(atNotices($president))->toBeEmpty();
});

it('writes from the record as it is when the job runs, so an undone check-in is not announced', function () {
    [$activity, $president, $member] = atActivity();

    // The check-in was undone before the queued notice ran.
    (new NotifyMemberOfAttendance($activity->id, NotifyMemberOfAttendance::CHECKED_IN, $member->id, $president->id))->handle();

    expect(atNotices($member))->toBeEmpty();
});

it('concludes the attendance when the activity is completed: every attendee gets their final record', function () {
    [$activity, $president, $member, $other] = atActivity();
    $absent = atUser();

    atRecord($activity, $president, $member, 'in');
    atRecord($activity, $president, $member, 'out');
    atRecord($activity, $president, $other, 'in');
    atRecord($activity, $president, $president, 'in');

    test()->withSession(atSession($president))->post(route('officer.activities.complete', $activity))->assertRedirect();

    $memberClosed = atNotices($member)->last();
    $otherClosed  = atNotices($other)->last();

    expect($activity->fresh()->status)->toBe('completed')
        ->and($memberClosed->title)->toBe('Attendance concluded')
        ->and($memberClosed->body)->toContain('has closed')->toContain('checked out')
        ->and($otherClosed->title)->toBe('Attendance concluded')
        ->and($otherClosed->body)->toContain('no check-out was recorded')
        ->and(atNotices($president))->toBeEmpty()
        ->and(atNotices($absent))->toBeEmpty();
});

it("only the activity's club officers can record attendance, so no one else can trigger a notice", function () {
    [$activity, , $member] = atActivity();
    [, $outsider] = atActivity();

    atRecord($activity, $outsider, $member, 'in')->assertForbidden();

    expect(atNotices($member))->toBeEmpty();
});
