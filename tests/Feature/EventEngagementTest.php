<?php

use App\Events\NotificationCreated;
use Carbon\Carbon;
use App\Models\Attendance;
use App\Models\Club;
use App\Models\ClubActivity;
use App\Models\ClubMember;
use App\Models\ClubNotification;
use App\Models\ClubOfficer;
use App\Models\MemberSemesterStatus;
use App\Models\Semester;
use App\Models\User;
use App\Services\ChurnRiskService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/*
 * A.2 Event & Engagement Cluster — see docs/test.docx and TASK/tests-logs.md.
 *
 * Five test procedures:
 *   1. Officer creates + schedules an activity with complete details
 *   2. Overlapping venue/time booking is rejected
 *   3. New/updated activity is broadcast in real time via Laravel Reverb
 *   4. Churn Risk Engine scores members and is reachable only by club Officers
 *   5. AI-assisted activity proposal checks venue availability before submission
 */

// ── Fixtures ─────────────────────────────────────────────────────────────────

function evUser(string $role, ?string $email = null): User
{
    static $n = 0;
    $n++;

    return User::create([
        'first_name'   => 'Event',
        'last_name'    => "Tester{$n}",
        'edp_number'   => str_pad((string) (900 + $n), 10, '0', STR_PAD_LEFT),
        'email'        => $email ?? "ev{$n}@sccpag.edu.ph",
        'password'     => Hash::make('Password1!'),
        'role'         => $role,
        'course_id'    => testCourse()->id,
        'is_admin'     => false,
        'is_suspended' => false,
    ]);
}

function evClub(string $name = 'Test Club'): Club
{
    static $n = 0;
    $n++;

    return Club::create([
        'slug'            => 'test-club-' . $n,
        'acronym'         => 'TC' . $n,
        'club_type'       => 'Non-Academic',
        'type'            => 'non_academic',
        'course_slug'     => 'all',
        'department_slug' => 'all',
        'name'            => $name . ' ' . $n,
        'is_active'       => true,
    ]);
}

/** An officer (president) of a fresh club, plus the club itself. */
function evOfficer(?Club $club = null): array
{
    $club ??= evClub();
    $user = evUser('president');

    ClubOfficer::create([
        'club_id'  => $club->id,
        'user_id'  => $user->id,
        'position' => 'president',
    ]);

    return [$user, $club];
}

function evSession(User $user): array
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

/** A complete, valid activity payload. */
function evPayload(array $overrides = []): array
{
    return array_merge([
        'title'                 => 'General Assembly',
        'description'           => 'Semestral general assembly for all members.',
        'date'                  => now()->addDays(10)->toDateString(),
        'time_start'            => '09:00',
        'time_end'              => '11:00',
        'venue'                 => 'AVR',
        'purpose'               => 'Orient members on the semester calendar.',
        'expected_participants' => 80,
        'activity_type'         => 'internal_meeting',
        'confirmed'             => 1,
    ], $overrides);
}

/** Persist an already-booked activity (bypasses the controller). */
function evBooking(Club $club, string $date, string $start, string $end, string $venue, string $title, ?User $creator = null): ClubActivity
{
    $creator ??= evUser('president');

    return ClubActivity::create([
        'club_id'               => $club->id,
        'title'                 => $title,
        'description'           => 'Existing booking.',
        'date'                  => $date,
        'time_start'            => $start,
        'time_end'              => $end,
        'venue'                 => $venue,
        'purpose'               => 'Existing booking.',
        'expected_participants' => 50,
        'activity_type'         => 'internal_meeting',
        'approval_status'       => 'no_approval_needed',
        'status'                => 'scheduled',
        'created_by'            => $creator->id,
    ]);
}

/**
 * The venue validation message from a redirect response.
 *
 * The array session driver flashes the error bag as a plain array
 * (errors.default.messages.venue), so handle that shape as well as a live bag.
 */
function evVenueError($response): string
{
    return evFieldError($response, 'venue');
}

/** The same, for any field. */
function evFieldError($response, string $field): string
{
    $errors = $response->getSession()->get('errors');

    if ($errors instanceof \Illuminate\Support\ViewErrorBag || $errors instanceof \Illuminate\Support\MessageBag) {
        return (string) $errors->first($field);
    }

    return (string) \Illuminate\Support\Arr::first(
        \Illuminate\Support\Arr::wrap(data_get($errors, "default.messages.{$field}", ''))
    );
}

// ── Procedure 1 — Officer creates and schedules an activity ──────────────────

it('P1: lets an Officer create and schedule an activity with complete details', function () {
    [$officer, $club] = evOfficer();

    $response = $this->withSession(evSession($officer))
        ->post(route('officer.activities.store'), evPayload());

    $activity = ClubActivity::where('club_id', $club->id)->first();

    $response->assertRedirect(route('officer.activities.show', $activity));
    $response->assertSessionHas('success');
    $response->assertSessionHasNoErrors();

    expect($activity)->not->toBeNull()
        // Scheduled, but confirmed only once the VPA and the adviser approve it.
        ->and($activity->approval_status)->toBe('pending_vpa')
        ->and($activity->title)->toBe('General Assembly')
        ->and($activity->venue)->toBe('AVR')
        ->and($activity->date->toDateString())->toBe(now()->addDays(10)->toDateString())
        ->and($activity->time_start)->toStartWith('09:00')
        ->and($activity->time_end)->toStartWith('11:00')
        ->and($activity->status)->toBe('scheduled')
        ->and($activity->created_by)->toBe($officer->id);
});

it('P1: rejects an activity that is missing title, venue, date or time', function () {
    [$officer] = evOfficer();

    $response = $this->withSession(evSession($officer))
        ->from(route('officer.activities.create'))
        ->post(route('officer.activities.store'), evPayload([
            'title'      => '',
            'venue'      => '',
            'date'       => '',
            'time_start' => '',
            'time_end'   => '',
        ]));

    $response->assertSessionHasErrors(['title', 'venue', 'date', 'time_start', 'time_end']);
    expect(ClubActivity::count())->toBe(0);
});

// ── Procedure 2 — Venue/time double-booking must be rejected ─────────────────

it('P2: rejects a booking that overlaps an existing activity in the same venue and time slot', function () {
    $date = now()->addDays(14)->toDateString();

    // Club A already holds the gym 09:00–11:00.
    [, $clubA] = evOfficer();
    evBooking($clubA, $date, '09:00', '11:00', 'AVR', 'Leadership Seminar');

    // Club B tries to take the same gym 10:00–12:00 — a real overlap.
    [$officerB] = evOfficer();

    $response = $this->withSession(evSession($officerB))
        ->from(route('officer.activities.create'))
        ->post(route('officer.activities.store'), evPayload([
            'title'      => 'Sportsfest Briefing',
            'date'       => $date,
            'time_start' => '10:00',
            'time_end'   => '12:00',
            'venue'      => 'AVR',
        ]));

    $response->assertSessionHasErrors('venue');
    expect(ClubActivity::where('title', 'Sportsfest Briefing')->exists())->toBeFalse();
});

it('P2: does not leak the other club\'s activity title in the conflict message', function () {
    // POLICY.md: cross-club data access is blocked. An officer may learn that a
    // venue is taken and when, but not what the other club is running.
    $date = now()->addDays(14)->toDateString();

    [, $clubA] = evOfficer();
    evBooking($clubA, $date, '09:00', '11:00', 'AVR', 'Confidential Club Retreat');

    [$officerB] = evOfficer();

    $response = $this->withSession(evSession($officerB))
        ->from(route('officer.activities.create'))
        ->post(route('officer.activities.store'), evPayload([
            'title' => 'Sportsfest Briefing', 'date' => $date,
            'time_start' => '10:00', 'time_end' => '12:00', 'venue' => 'AVR',
        ]));

    $message = evVenueError($response);

    expect($message)->toContain('another club')
        ->and($message)->toContain('09:00 to 11:00')
        ->and($message)->not->toContain('Confidential Club Retreat');
});

it('P2: names the activity when the clash is with the officer\'s own club', function () {
    $date = now()->addDays(14)->toDateString();

    [$officer, $club] = evOfficer();
    evBooking($club, $date, '09:00', '11:00', 'AVR', 'Our Own Seminar');

    $response = $this->withSession(evSession($officer))
        ->from(route('officer.activities.create'))
        ->post(route('officer.activities.store'), evPayload([
            'title' => 'Sportsfest Briefing', 'date' => $date,
            'time_start' => '10:00', 'time_end' => '12:00', 'venue' => 'AVR',
        ]));

    expect(evVenueError($response))->toContain('Our Own Seminar');
});

it('P2: allows a back-to-back booking in the same venue that does not overlap', function () {
    $date = now()->addDays(14)->toDateString();

    [, $clubA] = evOfficer();
    evBooking($clubA, $date, '09:00', '11:00', 'AVR', 'Leadership Seminar');

    [$officerB] = evOfficer();

    $response = $this->withSession(evSession($officerB))
        ->post(route('officer.activities.store'), evPayload([
            'title'      => 'Afternoon Practice',
            'date'       => $date,
            'time_start' => '11:00',
            'time_end'   => '13:00',
            'venue'      => 'AVR',
        ]));

    $response->assertSessionHasNoErrors();
    expect(ClubActivity::where('title', 'Afternoon Practice')->exists())->toBeTrue();
});

it('P2: allows a booking that ends exactly when an existing one starts', function () {
    // The mirror of the back-to-back case, and the one a naive string comparison
    // of 'H:i' against 'H:i:s' gets wrong.
    $date = now()->addDays(14)->toDateString();

    [, $clubA] = evOfficer();
    evBooking($clubA, $date, '09:00', '11:00', 'AVR', 'Leadership Seminar');

    [$officerB] = evOfficer();

    $this->withSession(evSession($officerB))
        ->post(route('officer.activities.store'), evPayload([
            'title' => 'Early Practice', 'date' => $date,
            'time_start' => '07:00', 'time_end' => '09:00', 'venue' => 'AVR',
        ]))->assertSessionHasNoErrors();

    expect(ClubActivity::where('title', 'Early Practice')->exists())->toBeTrue();
});

it('P2: compares correctly when stored times use the H:i:s shape PostgreSQL writes', function () {
    $date = now()->addDays(14)->toDateString();

    [, $clubA] = evOfficer();
    evBooking($clubA, $date, '09:00:00', '11:00:00', 'AVR', 'Leadership Seminar');

    [$officerB] = evOfficer();

    // Overlapping — must be blocked.
    $this->withSession(evSession($officerB))
        ->from(route('officer.activities.create'))
        ->post(route('officer.activities.store'), evPayload([
            'title' => 'Overlapping', 'date' => $date,
            'time_start' => '10:00', 'time_end' => '12:00', 'venue' => 'AVR',
        ]))->assertSessionHasErrors('venue');

    // Exactly back-to-back — must be allowed.
    $this->withSession(evSession($officerB))
        ->post(route('officer.activities.store'), evPayload([
            'title' => 'Back To Back', 'date' => $date,
            'time_start' => '11:00', 'time_end' => '13:00', 'venue' => 'AVR',
        ]))->assertSessionHasNoErrors();

    expect(ClubActivity::where('title', 'Overlapping')->exists())->toBeFalse()
        ->and(ClubActivity::where('title', 'Back To Back')->exists())->toBeTrue();
});

it('P2: allows the same slot in a different venue', function () {
    $date = now()->addDays(14)->toDateString();

    [, $clubA] = evOfficer();
    evBooking($clubA, $date, '09:00', '11:00', 'AVR', 'Leadership Seminar');

    [$officerB] = evOfficer();

    $this->withSession(evSession($officerB))
        ->post(route('officer.activities.store'), evPayload([
            'title' => 'Parallel Session', 'date' => $date,
            'time_start' => '09:00', 'time_end' => '11:00', 'venue' => 'Library',
        ]))->assertSessionHasNoErrors();

    expect(ClubActivity::where('title', 'Parallel Session')->exists())->toBeTrue();
});

it('P2: allows the same venue and slot on a different date', function () {
    [, $clubA] = evOfficer();
    evBooking($clubA, now()->addDays(14)->toDateString(), '09:00', '11:00', 'AVR', 'Leadership Seminar');

    [$officerB] = evOfficer();

    $this->withSession(evSession($officerB))
        ->post(route('officer.activities.store'), evPayload([
            'title' => 'Next Day Session', 'date' => now()->addDays(15)->toDateString(),
            'time_start' => '09:00', 'time_end' => '11:00', 'venue' => 'AVR',
        ]))->assertSessionHasNoErrors();

    expect(ClubActivity::where('title', 'Next Day Session')->exists())->toBeTrue();
});

it('P2: offers exactly the main-campus room list', function () {
    expect(ClubActivity::VENUES)->toHaveCount(40)
        ->toContain('AVR', 'EB 4-5', 'Moot Court', 'JD 2', 'PC 2-6', 'PC Lab C', 'SN 3-2', 'ST 1-2', 'Library', 'YDC')
        ->not->toContain('Others')
        ->not->toContain('SCC Gymnasium')
        ->and(array_unique(ClubActivity::VENUES))->toHaveCount(40);

    [$officer] = evOfficer();

    $this->withSession(evSession($officer))
        ->get(route('officer.activities.create'))
        ->assertOk()
        ->assertSee('<option value="PC Lab B"', false)
        ->assertSee('<option value="YDC"', false)
        ->assertDontSee('<option value="Others"', false)
        ->assertSee('Main campus rooms only.');
});

it('P2: refuses a venue that is not a main-campus room', function (string $venue) {
    [$officer] = evOfficer();

    $this->withSession(evSession($officer))
        ->post(route('officer.activities.store'), evPayload([
            'title' => 'Off-list Booking', 'venue' => $venue,
        ]))->assertSessionHasErrors(['venue' => 'Choose a room from the main campus list.']);

    expect(ClubActivity::where('title', 'Off-list Booking')->exists())->toBeFalse();
})->with([
    'the retired catch-all'  => ['Others'],
    'a retired venue'        => ['SCC Gymnasium'],
    'free text'              => ['Barangay Hall, Tiguma'],
]);

it('P2: blocks a second club from a newly listed room at an overlapping time', function (string $room) {
    $date = now()->addDays(14)->toDateString();

    [, $clubA] = evOfficer();
    evBooking($clubA, $date, '09:00', '11:00', $room, 'Room Holder');

    [$officerB] = evOfficer();

    $this->withSession(evSession($officerB))
        ->post(route('officer.activities.store'), evPayload([
            'title' => 'Room Clash', 'date' => $date,
            'time_start' => '10:00', 'time_end' => '12:00', 'venue' => $room,
        ]))->assertSessionHasErrors('venue');

    // The room next door is still free for the same slot.
    $this->withSession(evSession($officerB))
        ->post(route('officer.activities.store'), evPayload([
            'title' => 'Room Next Door', 'date' => $date,
            'time_start' => '10:00', 'time_end' => '12:00', 'venue' => 'Library',
        ]))->assertSessionHasNoErrors();

    expect(ClubActivity::where('title', 'Room Clash')->exists())->toBeFalse()
        ->and(ClubActivity::where('title', 'Room Next Door')->exists())->toBeTrue();
})->with(['EB 2-1', 'PC Lab A', 'SN 3-2', 'YDC']);

it('P2: an activity booked under a retired venue can still be edited without moving it', function () {
    [$officer, $club] = evOfficer();
    $old = evBooking($club, now()->addDays(14)->toDateString(), '09:00', '11:00', 'SCC Gymnasium', 'Legacy Booking', $officer);

    $this->withSession(evSession($officer))
        ->get(route('officer.activities.edit', $old))
        ->assertOk()
        ->assertSee('SCC Gymnasium (no longer on the room list)');

    $this->withSession(evSession($officer))
        ->patch(route('officer.activities.update', $old), evPayload([
            'title' => 'Legacy Booking Renamed', 'date' => $old->date->toDateString(),
            'time_start' => '09:00', 'time_end' => '11:00', 'venue' => 'SCC Gymnasium',
        ]))->assertSessionHasNoErrors();

    expect($old->fresh()->title)->toBe('Legacy Booking Renamed')
        ->and($old->fresh()->venue)->toBe('SCC Gymnasium');

    // …but it cannot be moved to a different venue that is off the list.
    $this->withSession(evSession($officer))
        ->patch(route('officer.activities.update', $old), evPayload([
            'title' => 'Legacy Booking Renamed', 'date' => $old->date->toDateString(),
            'time_start' => '09:00', 'time_end' => '11:00', 'venue' => 'Covered Court',
        ]))->assertSessionHasErrors('venue');
});

it('P2: activities booked under the retired "Others" catch-all never clash with each other', function () {
    $date = now()->addDays(14)->toDateString();
    [, $clubA] = evOfficer();
    evBooking($clubA, $date, '09:00', '11:00', 'Others', 'Off-campus Outreach');

    expect(ClubActivity::findVenueConflict('Others', $date, '09:00', '11:00'))->toBeNull();
});

it('P2: a cancelled or DSA-rejected activity releases its venue slot', function (string $field, string $value) {
    $date = now()->addDays(14)->toDateString();

    [, $clubA] = evOfficer();
    evBooking($clubA, $date, '09:00', '11:00', 'AVR', 'Dropped Booking')
        ->update([$field => $value]);

    [$officerB] = evOfficer();

    $this->withSession(evSession($officerB))
        ->post(route('officer.activities.store'), evPayload([
            'title' => 'Replacement Booking', 'date' => $date,
            'time_start' => '09:00', 'time_end' => '11:00', 'venue' => 'AVR',
        ]))->assertSessionHasNoErrors();

    expect(ClubActivity::where('title', 'Replacement Booking')->exists())->toBeTrue();
})->with([
    'cancelled'    => ['status', 'cancelled'],
    'DSA-rejected' => ['approval_status', 'rejected'],
]);

it('P2: blocks an edit that moves an activity onto an occupied slot', function () {
    $date = now()->addDays(14)->toDateString();

    [, $clubA] = evOfficer();
    evBooking($clubA, $date, '09:00', '11:00', 'AVR', 'Leadership Seminar');

    [$officerB, $clubB] = evOfficer();
    $mine = evBooking($clubB, $date, '14:00', '16:00', 'Library', 'My Activity', $officerB);

    $this->withSession(evSession($officerB))
        ->from(route('officer.activities.edit', $mine))
        ->patch(route('officer.activities.update', $mine), evPayload([
            'title' => 'My Activity', 'date' => $date,
            'time_start' => '10:00', 'time_end' => '12:00', 'venue' => 'AVR',
        ]))->assertSessionHasErrors('venue');

    $mine->refresh();
    expect($mine->venue)->toBe('Library')->and($mine->time_start)->toStartWith('14:00');
});

it('P2: lets an activity keep its own slot when edited', function () {
    // The activity must not collide with itself: editing only the title while
    // leaving venue, date and time alone has to succeed.
    $date = now()->addDays(14)->toDateString();

    [$officer, $club] = evOfficer();
    $mine = evBooking($club, $date, '09:00', '11:00', 'AVR', 'Original Title', $officer);

    $this->withSession(evSession($officer))
        ->patch(route('officer.activities.update', $mine), evPayload([
            'title' => 'Renamed Only', 'date' => $date,
            'time_start' => '09:00', 'time_end' => '11:00', 'venue' => 'AVR',
        ]))->assertSessionHasNoErrors();

    expect($mine->fresh()->title)->toBe('Renamed Only');
});

// ── Procedure 3 — Real-time broadcast via Laravel Reverb ────────────────────

/** A club with an officer, an active member, and a DSA — all "connected users". */
function evAudience(): array
{
    [$officer, $club] = evOfficer();
    $adviser = evUser('adviser');
    $member  = evUser('member');

    $club->update(['adviser_id' => $adviser->id]);

    ClubMember::create([
        'club_id' => $club->id, 'user_id' => $member->id,
        'status'  => 'active', 'role' => 'Member',
    ]);

    return [$officer, $club, $adviser, $member];
}

/**
 * Take an activity through both approvals — the VPA's, then the adviser's — the way
 * every new or changed activity is confirmed (POLICY.md §Event Creation).
 */
function evApproveAll(ClubActivity $activity, User $adviser): ClubActivity
{
    test()->withSession(evSession(evVpa()))->post(route('vpa.letters.approve', $activity));
    test()->withSession(evSession($adviser))->post(route('adviser.activities.approve', $activity));
    test()->flushSession();

    return $activity->fresh();
}

/** Recipient ids of every NotificationCreated broadcast captured by Event::fake(). */
function evBroadcastRecipients(): array
{
    return Event::dispatched(NotificationCreated::class)
        ->map(fn ($d) => $d[0]->notification->recipient_id)
        ->all();
}

it('P3: broadcasts a real-time event to club members when a new activity is approved', function () {
    // POLICY: every new activity, an internal meeting included, waits for the VPA and
    // the adviser; members hear of it the moment it is confirmed, not before.
    Event::fake([NotificationCreated::class]);

    [$officer, , $adviser, $member] = evAudience();

    $this->withSession(evSession($officer))
        ->post(route('officer.activities.store'), evPayload());

    expect(evBroadcastRecipients())->not->toContain($member->id);

    evApproveAll(ClubActivity::where('title', 'General Assembly')->firstOrFail(), $adviser);

    expect(evBroadcastRecipients())->toContain($member->id);
});

it('P3: broadcasts a real-time event to club members when an updated activity is approved again', function () {
    Event::fake([NotificationCreated::class]);

    [$officer, $club, $adviser, $member] = evAudience();

    // A meeting scheduled before every activity needed approval.
    $activity = evBooking($club, now()->addDays(9)->toDateString(), '09:00', '11:00', 'Moot Court', 'Original Title');

    $this->withSession(evSession($officer))
        ->patch(route('officer.activities.update', $activity), evPayload([
            'title' => 'Rescheduled Title',
            'venue' => 'Library',
        ]));

    expect($activity->fresh()->title)->toBe('Rescheduled Title')
        ->and($activity->fresh()->approval_status)->toBe('pending_vpa');

    evApproveAll($activity, $adviser);

    expect(evBroadcastRecipients())->toContain($member->id);
});

it('P3: broadcasts the review request in real time for an approval-requiring activity', function () {
    // The room booking letter reaches the VPA live; the adviser hears once the VPA approves.
    Event::fake([NotificationCreated::class]);

    [$officer, , $adviser] = evAudience();
    $vpa = evVpa();

    $this->withSession(evSession($officer))
        ->post(route('officer.activities.store'), evPayload([
            'title'         => 'ACLE Outreach',
            'activity_type' => 'acle',
        ]));

    expect(evBroadcastRecipients())->toContain($vpa->id)
        ->not->toContain($adviser->id);

    $this->withSession(evSession($vpa))
        ->post(route('vpa.letters.approve', ClubActivity::where('title', 'ACLE Outreach')->firstOrFail()));

    expect(evBroadcastRecipients())->toContain($adviser->id);
});

it('P3: holds the member broadcast until the DSA approves an approval-requiring activity', function () {
    // POLICY: an activity awaiting sign-off is not confirmed, so members are not
    // told about it yet.
    Event::fake([NotificationCreated::class]);

    [$officer, , , $member] = evAudience();

    $this->withSession(evSession($officer))
        ->post(route('officer.activities.store'), evPayload([
            'title'         => 'ACLE Outreach',
            'activity_type' => 'acle',
        ]));

    expect(evBroadcastRecipients())->not->toContain($member->id);
});

it('P3: broadcasts to members the moment the adviser approves the activity', function () {
    [$officer, $club, $adviser, $member] = evAudience();

    $this->withSession(evSession($officer))
        ->post(route('officer.activities.store'), evPayload([
            'title'         => 'ACLE Outreach',
            'activity_type' => 'acle',
        ]));

    $activity = ClubActivity::where('title', 'ACLE Outreach')->firstOrFail();
    expect($activity->approval_status)->toBe('pending_vpa');

    $this->withSession(evSession(evVpa()))
        ->post(route('vpa.letters.approve', $activity));

    expect($activity->fresh()->approval_status)->toBe('pending_approval');

    // Only now does the member hear about it.
    Event::fake([NotificationCreated::class]);

    $this->withSession(evSession($adviser))
        ->post(route('adviser.activities.approve', $activity));

    expect($activity->fresh()->approval_status)->toBe('approved')
        ->and(evBroadcastRecipients())->toContain($member->id);
});

it('P3: does not broadcast to the officer who made the change', function () {
    Event::fake([NotificationCreated::class]);

    [$officer, $club] = evAudience();

    // The officer is also an active member of their own club.
    ClubMember::create([
        'club_id' => $club->id, 'user_id' => $officer->id,
        'status'  => 'active', 'role' => 'Member',
    ]);

    $this->withSession(evSession($officer))
        ->post(route('officer.activities.store'), evPayload());

    expect(evBroadcastRecipients())->not->toContain($officer->id);
});

it('P3: does not broadcast an immaterial edit', function () {
    // Reworded prose is not worth interrupting a member for.
    Event::fake([NotificationCreated::class]);

    [$officer, $club, , $member] = evAudience();

    $activity = evBooking($club, now()->addDays(9)->toDateString(), '09:00', '11:00', 'Moot Court', 'Steady Title', $officer);

    $this->withSession(evSession($officer))
        ->patch(route('officer.activities.update', $activity), evPayload([
            'title'       => 'Steady Title',
            'date'        => now()->addDays(9)->toDateString(),
            'venue'       => 'Moot Court',
            'description' => 'Completely reworded description, same schedule.',
        ]));

    expect($activity->fresh()->description)->toContain('Completely reworded')
        ->and(evBroadcastRecipients())->not->toContain($member->id);
});

it('P3: does not broadcast to inactive or pending members', function () {
    Event::fake([NotificationCreated::class]);

    [$officer, $club, $adviser, $member] = evAudience();

    $pending = evUser('member');
    ClubMember::create([
        'club_id' => $club->id, 'user_id' => $pending->id,
        'status'  => 'pending', 'role' => 'Member',
    ]);

    $this->withSession(evSession($officer))
        ->post(route('officer.activities.store'), evPayload());

    evApproveAll(ClubActivity::where('title', 'General Assembly')->firstOrFail(), $adviser);

    expect(evBroadcastRecipients())->toContain($member->id)
        ->not->toContain($pending->id);
});

it('P3: respects the activity\'s target year levels', function () {
    Event::fake([NotificationCreated::class]);

    [$officer, $club, $adviser] = evAudience();

    $firstYear  = evUser('member');
    $fourthYear = evUser('member');
    $firstYear->update(['year_level' => 1]);
    $fourthYear->update(['year_level' => 4]);

    foreach ([$firstYear, $fourthYear] as $user) {
        ClubMember::create([
            'club_id' => $club->id, 'user_id' => $user->id,
            'status'  => 'active', 'role' => 'Member',
        ]);
    }

    $this->withSession(evSession($officer))
        ->post(route('officer.activities.store'), evPayload([
            'title'              => 'First Years Only',
            'target_year_levels' => [1],
        ]));

    evApproveAll(ClubActivity::where('title', 'First Years Only')->firstOrFail(), $adviser);

    expect(evBroadcastRecipients())->toContain($firstYear->id)
        ->and(evBroadcastRecipients())->not->toContain($fourthYear->id);
});

it('P3: the member broadcast carries the activity schedule and a link', function () {
    [$officer, $club, $adviser, $member] = evAudience();

    $this->withSession(evSession($officer))
        ->post(route('officer.activities.store'), evPayload([
            'title' => 'General Assembly',
            'venue' => 'AVR',
        ]));

    evApproveAll(ClubActivity::where('title', 'General Assembly')->firstOrFail(), $adviser);

    $notification = ClubNotification::where('recipient_id', $member->id)->latest('id')->firstOrFail();

    expect($notification->title)->toContain($club->name)
        ->and($notification->body)->toContain('General Assembly')
        ->and($notification->body)->toContain('AVR')
        ->and($notification->body)->toContain('09:00–11:00')
        ->and($notification->action_url)->toBe(route('member.clubs.show', $club, false));
});

it('P3: the broadcast event targets a private Reverb channel with a payload', function () {
    // NotificationCreated is the only broadcastable event in the app; confirm the
    // wiring itself is sound (private channel, named event, JSON payload).
    $user = evUser('member');
    $club = evClub();

    $notification = ClubNotification::create([
        'recipient_id' => $user->id,
        'sender_type'  => 'system',
        'club_id'      => $club->id,
        'title'        => 'Activity scheduled',
        'body'         => 'A new activity was scheduled.',
        'action_url'   => '/member/dashboard',
    ]);

    $event = new NotificationCreated($notification);

    expect($event)->toBeInstanceOf(\Illuminate\Contracts\Broadcasting\ShouldBroadcastNow::class)
        ->and($event->broadcastOn()->name)->toBe('private-notifications.' . $user->id)
        ->and($event->broadcastAs())->toBe('notification.created')
        ->and($event->broadcastWith())->toHaveKeys(['id', 'title', 'body', 'unread']);
});

// ── Procedure 4 — Churn Risk Engine ─────────────────────────────────────────

it('P4: produces a risk score and tag for every active member from engagement data', function () {
    [, $club] = evOfficer();

    $semester = Semester::create([
        'label'      => 'Test Semester',
        'start_date' => now()->subMonths(2)->toDateString(),
        'end_date'   => now()->addMonths(2)->toDateString(),
        'is_current' => true,
    ]);

    // A completed activity for the club, so attendance is measurable.
    $activity = evBooking($club, now()->subDays(7)->toDateString(), '09:00', '11:00', 'AVR', 'Past Assembly');
    $activity->update(['status' => 'completed', 'completed_at' => now()->subDays(7)]);

    // Engaged member — attended, marked active.
    $engaged = evUser('member');
    $engagedMembership = ClubMember::create([
        'club_id' => $club->id, 'user_id' => $engaged->id,
        'status'  => 'active', 'role' => 'Member',
    ]);
    Attendance::create([
        'event_id' => $activity->id, 'user_id' => $engaged->id, 'time_in' => now()->subDays(7),
    ]);
    MemberSemesterStatus::create([
        'club_member_id' => $engagedMembership->id, 'semester_id' => $semester->id,
        'semester_status' => 'active', 'updated_by' => $engaged->id,
    ]);

    // At-risk member — no attendance, marked dropped.
    $atRisk = evUser('member');
    $atRiskMembership = ClubMember::create([
        'club_id' => $club->id, 'user_id' => $atRisk->id,
        'status'  => 'active', 'role' => 'Member',
    ]);
    MemberSemesterStatus::create([
        'club_member_id' => $atRiskMembership->id, 'semester_id' => $semester->id,
        'semester_status' => 'dropped', 'updated_by' => $atRisk->id,
    ]);

    $rows = app(ChurnRiskService::class)->forClub($club->fresh(), $semester);

    expect($rows)->toHaveCount(2);

    foreach ($rows as $row) {
        expect($row['score'])->toBeInt()->toBeGreaterThanOrEqual(0)->toBeLessThanOrEqual(100)
            ->and($row['tag'])->toBeIn(['low', 'medium', 'high'])
            ->and($row['factors'])->not->toBeEmpty();
    }

    $byUser = $rows->keyBy(fn ($r) => $r['user']->id);

    expect($byUser[$atRisk->id]['score'])->toBeGreaterThan($byUser[$engaged->id]['score'])
        ->and($byUser[$engaged->id]['score'])->toBe(0)
        ->and($byUser[$engaged->id]['tag'])->toBe('low')
        ->and($byUser[$atRisk->id]['tag'])->toBe('high');

    $summary = app(ChurnRiskService::class)->summarize($rows);
    expect($summary['total'])->toBe(2)->and($summary['high'])->toBe(1)->and($summary['low'])->toBe(1);
});

it('P4: is reachable by an Officer', function () {
    [$officer] = evOfficer();

    $this->withSession(evSession($officer))
        ->get(route('churn-risk'))
        ->assertOk()
        ->assertSee('Churn Risk Engine', false);
});

it('P4: is forbidden to Members, Advisers and the DSA', function (string $role) {
    $user = evUser($role);

    // The DSA follows clubs, not members, on dsa.club-performance (ClubPerformanceTest).
    $this->withSession(evSession($user))
        ->get(route('churn-risk'))
        ->assertForbidden();
})->with(['member', 'adviser', 'dsa']);

it('P4: forbids an Officer from reading another club risk data', function () {
    [$officer] = evOfficer();
    $otherClub = evClub('Other');

    $this->withSession(evSession($officer))
        ->get(route('churn-risk', ['club_id' => $otherClub->id]))
        ->assertForbidden();
});

// ── Activity review belongs to the club adviser ─────────────────────────────

/*
 * POLICY.md §Event Creation: internal meetings are scheduled outright; ACLE,
 * community involvement, campus resource use and other external activities wait for
 * the CLUB ADVISER. The DSA watches and reports, but no longer decides.
 */

/** An officer, their club and the club's adviser. */
function evWithAdviser(): array
{
    [$officer, $club] = evOfficer();
    $adviser = evUser('adviser');
    $club->update(['adviser_id' => $adviser->id]);

    return [$officer, $club->fresh(), $adviser];
}

/** The Vice President for Administration's account, which approves room booking letters first. */
function evVpa(): User
{
    return User::where('role', 'vpa')->first() ?? evUser('vpa');
}

/** Send an approval-requiring activity with its room booking letter, and return it (with the VPA). */
function evSendToVpa(User $officer, string $title = 'ACLE Outreach'): ClubActivity
{
    evVpa();

    test()->withSession(evSession($officer))
        ->post(route('officer.activities.store'), evPayload([
            'title'         => $title,
            'activity_type' => 'acle',
        ]));

    return ClubActivity::where('title', $title)->firstOrFail();
}

/**
 * Submit an approval-requiring activity and have the VPA approve its letter, so it
 * reaches the adviser's queue (POLICY.md §Event Creation: VPA first, then adviser).
 */
function evSubmitForReview(User $officer, string $title = 'ACLE Outreach'): ClubActivity
{
    $activity = evSendToVpa($officer, $title);

    test()->withSession(evSession(evVpa()))
        ->post(route('vpa.letters.approve', $activity));

    // The VPA's "Approved and signed" flash names the activity; it belongs to their
    // session, not to whoever the test signs in next.
    test()->flushSession();

    return $activity->fresh();
}

it('A1: puts an approval-requiring activity in its own adviser queue', function () {
    [$officer, , $adviser] = evWithAdviser();

    $activity = evSubmitForReview($officer);

    expect($activity->approval_status)->toBe('pending_approval');

    $this->withSession(evSession($adviser))
        ->get(route('adviser.activities.review'))
        ->assertOk()
        ->assertSee('ACLE Outreach');
});

it('A1: sends an internal meeting through the VPA and then the adviser too', function () {
    // POLICY: every activity an officer adds needs both approvals — meetings included.
    [$officer, , $adviser] = evWithAdviser();
    $vpa = evVpa();

    $this->withSession(evSession($officer))
        ->post(route('officer.activities.store'), evPayload(['title' => 'Weekly Huddle']));
    $this->flushSession();

    $activity = ClubActivity::where('title', 'Weekly Huddle')->firstOrFail();

    expect($activity->activity_type)->toBe('internal_meeting')
        ->and($activity->approval_status)->toBe('pending_vpa')
        ->and($activity->isConfirmed())->toBeFalse()
        ->and($activity->hasBookingLetter())->toBeTrue();

    $this->withSession(evSession($vpa))->post(route('vpa.letters.approve', $activity));
    $this->flushSession();

    expect($activity->fresh()->approval_status)->toBe('pending_approval');

    $this->withSession(evSession($adviser))
        ->get(route('adviser.activities.review'))
        ->assertOk()
        ->assertSee('Weekly Huddle');
});

it('A1: notifies the adviser that an activity is waiting', function () {
    [$officer, $club, $adviser] = evWithAdviser();

    $activity = evSubmitForReview($officer);

    $notification = ClubNotification::where('recipient_id', $adviser->id)->latest('id')->first();

    expect($notification)->not->toBeNull()
        ->and($notification->club_id)->toBe($club->id)
        ->and($notification->title)->toContain('review')
        ->and($notification->body)->toContain($activity->title)
        ->and($notification->action_url)->toBe(route('adviser.activities.review', [], false));
});

it('A1: tells the DSA instead when the club has no adviser', function () {
    // POLICY's announcement rule, carried over: with nobody to review it, the
    // activity still waits — but the DSA is told so it is not waiting on no one.
    [$officer] = evOfficer();
    $dsa = evUser('dsa');

    $activity = evSubmitForReview($officer);

    expect($activity->approval_status)->toBe('pending_approval');

    $notification = ClubNotification::where('recipient_id', $dsa->id)->latest('id')->first();

    expect($notification)->not->toBeNull()
        ->and($notification->title)->toContain('no adviser');
});

it('A2: confirms the activity when the adviser approves it', function () {
    [$officer, , $adviser] = evWithAdviser();
    $activity = evSubmitForReview($officer);

    $this->withSession(evSession($adviser))
        ->post(route('adviser.activities.approve', $activity))
        ->assertRedirect();

    $fresh = $activity->fresh();

    expect($fresh->approval_status)->toBe('approved')
        ->and($fresh->approved_by)->toBe($adviser->id)
        ->and($fresh->approved_at)->not->toBeNull()
        ->and($fresh->isConfirmed())->toBeTrue();
});

it('A2: records the adviser remarks on a rejection', function () {
    [$officer, , $adviser] = evWithAdviser();
    $activity = evSubmitForReview($officer);

    $this->withSession(evSession($adviser))
        ->post(route('adviser.activities.reject', $activity), [
            'review_remarks' => 'The venue clashes with the college assembly.',
        ])->assertRedirect();

    $fresh = $activity->fresh();

    expect($fresh->approval_status)->toBe('rejected')
        ->and($fresh->review_remarks)->toContain('college assembly')
        ->and($fresh->isConfirmed())->toBeFalse();
});

it('A2: will not reject without a reason', function () {
    [$officer, , $adviser] = evWithAdviser();
    $activity = evSubmitForReview($officer);

    $this->withSession(evSession($adviser))
        ->post(route('adviser.activities.reject', $activity), ['review_remarks' => ''])
        ->assertSessionHasErrors('review_remarks');

    expect($activity->fresh()->approval_status)->toBe('pending_approval');
});

it('A2: tells the officers the adviser decided, naming the adviser not the DSA', function () {
    [$officer, , $adviser] = evWithAdviser();
    $activity = evSubmitForReview($officer);

    $this->withSession(evSession($adviser))
        ->post(route('adviser.activities.approve', $activity));

    $notification = ClubNotification::where('recipient_id', $officer->id)
        ->where('title', 'Activity approved')
        ->latest('id')
        ->first();

    expect($notification)->not->toBeNull()
        ->and($notification->body)->toContain('club adviser')
        ->and($notification->body)->not->toContain('DSA');
});

it('A2: shows the adviser the activity with its approve and reject controls', function () {
    [$officer, , $adviser] = evWithAdviser();
    $activity = evSubmitForReview($officer);

    $this->withSession(evSession($adviser))
        ->get(route('adviser.activities.show', $activity))
        ->assertOk()
        ->assertSee('ACLE Outreach')
        ->assertSee('Approve')
        ->assertSee('Reject')
        ->assertSee(route('adviser.activities.approve', $activity), false)
        ->assertSee(route('adviser.activities.reject', $activity), false);
});

it('A3: blocks the adviser of another club from seeing or deciding it', function () {
    // POLICY.md §Data Privacy — cross-club access is blocked.
    [$officer] = evWithAdviser();
    $activity = evSubmitForReview($officer);

    [, , $otherAdviser] = evWithAdviser();

    $this->withSession(evSession($otherAdviser))
        ->get(route('adviser.activities.review'))
        ->assertOk()
        ->assertDontSee('ACLE Outreach');

    $this->withSession(evSession($otherAdviser))
        ->get(route('adviser.activities.show', $activity))
        ->assertForbidden();

    $this->withSession(evSession($otherAdviser))
        ->post(route('adviser.activities.approve', $activity))
        ->assertForbidden();

    expect($activity->fresh()->approval_status)->toBe('pending_approval');
});

it('A3: leaves the DSA no way to approve an activity', function () {
    // The DSA keeps monitoring and reporting; deciding moved to the adviser.
    $decisionRoutes = collect(app('router')->getRoutes()->getRoutes())
        ->map(fn ($r) => (string) $r->getName())
        ->filter(fn ($n) => str_starts_with($n, 'dsa.activities.')
            && (str_contains($n, 'approve') || str_contains($n, 'reject') || str_contains($n, 'review')));

    expect($decisionRoutes)->toBeEmpty();
});

it('A3: still lets the DSA monitor every club activity', function () {
    [$officer] = evWithAdviser();
    evSubmitForReview($officer);

    $dsa = evUser('dsa');

    $this->withSession(evSession($dsa))
        ->get(route('dsa.activities.monitor'))
        ->assertOk()
        ->assertSee('ACLE Outreach');
});

it('A3: is not reachable by an officer or a member', function (string $role) {
    [$officer] = evWithAdviser();
    $activity = evSubmitForReview($officer);

    $user = $role === 'officer' ? $officer : evUser('member');

    $this->withSession(evSession($user))
        ->post(route('adviser.activities.approve', $activity))
        ->assertForbidden();

    expect($activity->fresh()->approval_status)->toBe('pending_approval');
})->with(['officer', 'member']);

it('A4: sends an edited approved activity back to the VPA, then the adviser', function () {
    // POLICY: editing an approved non-internal activity resets it, and the letter goes
    // back to the first reviewer — the VPA — signed again, before the adviser sees it.
    [$officer, , $adviser] = evWithAdviser();
    $activity = evSubmitForReview($officer);

    $this->withSession(evSession($adviser))
        ->post(route('adviser.activities.approve', $activity));

    expect($activity->fresh()->approval_status)->toBe('approved');

    $vpa = evVpa();
    ClubNotification::whereIn('recipient_id', [$adviser->id, $vpa->id])->delete();

    $this->withSession(evSession($officer))
        ->patch(route('officer.activities.update', $activity), evPayload([
            'title'         => 'ACLE Outreach',
            'activity_type' => 'acle',
            'venue'         => 'Library',
            'letter_body'   => 'We will hold our ACLE Outreach in the Library instead. We will keep the Library clean and orderly.',
        ]));

    $fresh = $activity->fresh();

    expect($fresh->approval_status)->toBe('pending_vpa')
        ->and($fresh->letterSignature('vpa'))->toBeNull()
        ->and($fresh->letterSignature('adviser'))->toBeNull()
        ->and(ClubNotification::where('recipient_id', $vpa->id)->count())->toBeGreaterThan(0)
        ->and(ClubNotification::where('recipient_id', $adviser->id)->where('title', 'Activity to review')->count())->toBe(0);
});

// ── An activity cannot be scheduled into the past ───────────────────────────

/*
 * The date rule only reaches midnight, so "today" alone still lets an officer book a
 * slot that has already gone by. These freeze the clock at 2 PM so "earlier today"
 * and "later today" both exist no matter when the suite runs.
 */

it('P1: refuses an activity starting earlier today, and says so', function () {
    $this->travelTo(Carbon::parse('2026-10-15 14:00:00'));

    [$officer] = evOfficer();

    $response = $this->withSession(evSession($officer))
        ->post(route('officer.activities.store'), evPayload([
            'date'       => '2026-10-15',   // today
            'time_start' => '08:00',        // six hours ago
            'time_end'   => '09:00',
        ]));

    $error = evFieldError($response, 'time_start');

    expect($error)->toContain('already passed')
        ->and($error)->toContain('2:00 PM')      // tells them what the time is now
        ->and(ClubActivity::count())->toBe(0);   // and nothing was scheduled
});

it('P1: still accepts an activity starting later today', function () {
    // The point is to block the past, not same-day scheduling.
    $this->travelTo(Carbon::parse('2026-10-15 14:00:00'));

    [$officer] = evOfficer();

    $this->withSession(evSession($officer))
        ->post(route('officer.activities.store'), evPayload([
            'date'       => '2026-10-15',
            'time_start' => '16:00',
            'time_end'   => '17:00',
        ]))
        ->assertSessionHasNoErrors();

    expect(ClubActivity::count())->toBe(1);
});

it('P1: refuses a past date with the date message', function () {
    $this->travelTo(Carbon::parse('2026-10-15 14:00:00'));

    [$officer] = evOfficer();

    $response = $this->withSession(evSession($officer))
        ->post(route('officer.activities.store'), evPayload(['date' => '2026-10-14']));

    expect(evFieldError($response, 'date'))->toContain('today or in the future')
        ->and(ClubActivity::count())->toBe(0);
});

it('P1: the proposal button reports a past start before the form is filled in', function () {
    $this->travelTo(Carbon::parse('2026-10-15 14:00:00'));
    evFakeGemini();

    [$officer] = evOfficer();

    $response = $this->withSession(evSession($officer))
        ->postJson(route('officer.activities.ai-proposal'), evProposal([
            'date'       => '2026-10-15',
            'time_start' => '08:00',
            'time_end'   => '09:00',
        ]));

    $response->assertStatus(422);

    expect($response->json('errors.time_start.0'))->toContain('already passed');

    // No point drafting prose for a slot nobody can use.
    Http::assertNothingSent();
});

it('P1: lets an officer edit an activity that has already happened', function () {
    // POLICY: "Officers may edit any activity at any time." The past-start rule is for
    // creating an activity, and must not lock officers out of their own history.
    $this->travelTo(Carbon::parse('2026-10-15 14:00:00'));

    [$officer, $club] = evOfficer();
    $past = evBooking($club, '2026-10-01', '09:00', '11:00', 'Moot Court', 'Finished Assembly', $officer);

    $this->withSession(evSession($officer))
        ->patch(route('officer.activities.update', $past), evPayload([
            'title'      => 'Finished Assembly (corrected)',
            'date'       => '2026-10-01',
            'time_start' => '09:00',
            'time_end'   => '11:00',
            'venue'      => 'Moot Court',
        ]))
        ->assertSessionHasNoErrors();

    expect($past->fresh()->title)->toBe('Finished Assembly (corrected)');
});

// ── Procedure 5 — AI-assisted activity proposal checks venue availability ────

it('P5: exposes an AI-assisted activity proposal endpoint', function () {
    $names = collect(app('router')->getRoutes()->getRoutes())
        ->map(fn ($r) => $r->getName())
        ->filter()
        ->values();

    $match = $names->first(fn ($n) => str_contains($n, 'proposal')
        || (str_contains($n, 'activities') && str_contains($n, 'ai')));

    expect($match)->not->toBeNull();
});

/** A proposal payload for the AI endpoint. */
function evProposal(array $overrides = []): array
{
    return array_merge([
        'title'      => 'AI Drafted Activity',
        'date'       => now()->addDays(21)->toDateString(),
        'time_start' => '14:00',
        'time_end'   => '15:00',
        'venue'      => 'AVR',
        'context'    => 'A sportsfest kickoff for the club.',
    ], $overrides);
}

/** Fake a successful Gemini proposal response. */
function evFakeGemini(array $draft = []): void
{
    config(['gemini.api_key' => 'test-key']);

    $draft = array_merge([
        'description'           => 'A drafted description of the activity.',
        'purpose'               => 'A drafted purpose and objectives statement.',
        'expected_participants' => 20,
        'target_year_levels'    => [],
    ], $draft);

    Http::fake(['*generativelanguage*' => Http::response([
        'candidates' => [[
            'content' => ['parts' => [[
                'text' => json_encode($draft),
            ]]],
        ]],
    ])]);
}

/** Put $count members of $yearLevel on the club roster, with the given membership status. */
function evRoster(Club $club, int $yearLevel, int $count, string $status = 'active'): void
{
    for ($i = 0; $i < $count; $i++) {
        $user = evUser('member');
        $user->update(['year_level' => $yearLevel]);

        ClubMember::create([
            'club_id' => $club->id, 'user_id' => $user->id,
            'status'  => $status, 'role' => 'Member',
        ]);
    }
}

/** The prompt text the proposal endpoint sent to Gemini. */
function evSentPrompt(): string
{
    $prompt = '';

    Http::assertSent(function ($request) use (&$prompt) {
        $prompt = (string) data_get($request->data(), 'contents.0.parts.0.text', '');

        return true;
    });

    return $prompt;
}

it('P5: the AI proposal reports a venue conflict before final submission', function () {
    $date = now()->addDays(21)->toDateString();
    evFakeGemini();

    [, $clubA] = evOfficer();
    evBooking($clubA, $date, '13:00', '16:00', 'AVR', 'Existing Gym Booking');

    [$officerB] = evOfficer();

    $response = $this->withSession(evSession($officerB))
        ->postJson(route('officer.activities.ai-proposal'), evProposal(['date' => $date]));

    $response->assertOk();

    expect($response->json('venue_available'))->toBeFalse()
        ->and($response->json('can_submit'))->toBeFalse()
        ->and($response->json('conflict.time_start'))->toBe('13:00')
        ->and($response->json('conflict.time_end'))->toBe('16:00');
});

it('P5: reports the venue as available when the slot is free', function () {
    evFakeGemini();
    [$officer] = evOfficer();

    $response = $this->withSession(evSession($officer))
        ->postJson(route('officer.activities.ai-proposal'), evProposal());

    $response->assertOk();

    expect($response->json('venue_available'))->toBeTrue()
        ->and($response->json('can_submit'))->toBeTrue()
        ->and($response->json('conflict'))->toBeNull();
});

it('P5: withholds another club\'s activity title in the proposal conflict', function () {
    $date = now()->addDays(21)->toDateString();
    evFakeGemini();

    [, $clubA] = evOfficer();
    evBooking($clubA, $date, '13:00', '16:00', 'AVR', 'Secret Retreat');

    [$officerB] = evOfficer();

    $response = $this->withSession(evSession($officerB))
        ->postJson(route('officer.activities.ai-proposal'), evProposal(['date' => $date]));

    expect($response->json('conflict.own_club'))->toBeFalse()
        ->and($response->json('conflict.title'))->toBeNull()
        ->and($response->json('conflict.message'))->not->toContain('Secret Retreat');
});

it('P5: suggests alternative venues that are free in the same slot', function () {
    $date = now()->addDays(21)->toDateString();
    evFakeGemini();

    [, $clubA] = evOfficer();
    evBooking($clubA, $date, '13:00', '16:00', 'AVR', 'Existing Gym Booking');
    evBooking($clubA, $date, '13:00', '16:00', 'Library', 'Existing Hall Booking');

    [$officerB] = evOfficer();

    $response = $this->withSession(evSession($officerB))
        ->postJson(route('officer.activities.ai-proposal'), evProposal(['date' => $date]));

    $alternatives = $response->json('alternative_venues');

    expect($alternatives)->toContain('Moot Court')
        ->and($alternatives)->toContain('YDC')
        ->and($alternatives)->not->toContain('AVR')
        ->and($alternatives)->not->toContain('Library')
        ->and($alternatives)->not->toContain('Others'); // non-exclusive, never suggested
});

it('P5: returns the AI draft for the officer to review, and creates nothing', function () {
    evFakeGemini();
    [$officer] = evOfficer();

    $response = $this->withSession(evSession($officer))
        ->postJson(route('officer.activities.ai-proposal'), evProposal());

    expect($response->json('ai_available'))->toBeTrue()
        ->and($response->json('draft.description'))->toBe('A drafted description of the activity.')
        ->and($response->json('draft.purpose'))->toBe('A drafted purpose and objectives statement.')
        ->and($response->json('notice'))->toContain('review and edit before submitting');

    // POLICY: AI output is never dispatched automatically — nothing is persisted.
    expect(ClubActivity::count())->toBe(0);
});

it('P5: drafts the participant estimate and the target year levels too', function () {
    evFakeGemini(['expected_participants' => 5, 'target_year_levels' => [1]]);

    [$officer, $club] = evOfficer();
    evRoster($club, 1, 2);
    evRoster($club, 2, 4);

    $response = $this->withSession(evSession($officer))
        ->postJson(route('officer.activities.ai-proposal'), evProposal());

    expect($response->json('draft.expected_participants'))->toBe(5)
        ->and($response->json('draft.target_year_levels'))->toBe([1])
        // Reported back so the form can say what the estimate was measured against.
        ->and($response->json('roster_total'))->toBe(6);

    // The estimate is anchored to the club's own roster, not invented: the figures
    // it has to answer to are in the prompt.
    expect(evSentPrompt())->toContain('6 active member(s)')
        ->toContain('1st Year: 2')
        ->toContain('2nd Year: 4');

    expect(ClubActivity::count())->toBe(0);
});

it('P5: never drafts a turnout larger than the club roster', function () {
    // A model that answers 500 for a club of 3 must not put 500 in front of the officer.
    evFakeGemini(['expected_participants' => 500]);

    [$officer, $club] = evOfficer();
    evRoster($club, 3, 3);

    $response = $this->withSession(evSession($officer))
        ->postJson(route('officer.activities.ai-proposal'), evProposal());

    expect($response->json('draft.expected_participants'))->toBe(3);
});

it('P5: counts only active members in the roster the estimate answers to', function () {
    // Pending applicants are not yet part of the club and are not announced to,
    // so they cannot inflate the turnout an officer is shown.
    evFakeGemini(['expected_participants' => 9]);

    [$officer, $club] = evOfficer();
    evRoster($club, 1, 2);
    evRoster($club, 1, 7, 'pending');

    $response = $this->withSession(evSession($officer))
        ->postJson(route('officer.activities.ai-proposal'), evProposal());

    expect($response->json('roster_total'))->toBe(2)
        ->and($response->json('draft.expected_participants'))->toBe(2)
        ->and(evSentPrompt())->toContain('2 active member(s)');
});

it('P5: offers no estimate when there is no roster to anchor one to', function () {
    // An empty field the officer must fill beats a number they might not check.
    evFakeGemini(['expected_participants' => 120]);

    [$officer] = evOfficer();

    $response = $this->withSession(evSession($officer))
        ->postJson(route('officer.activities.ai-proposal'), evProposal());

    expect($response->json('draft.expected_participants'))->toBeNull()
        ->and($response->json('draft.description'))->toBe('A drafted description of the activity.')
        ->and($response->json('roster_total'))->toBe(0)
        ->and(evSentPrompt())->toContain('not available');
});

it('P5: reads an all-year-levels draft as open to everyone, not as targeting', function () {
    evFakeGemini(['target_year_levels' => [1, 2, 3, 4]]);

    [$officer, $club] = evOfficer();
    evRoster($club, 1, 3);

    $response = $this->withSession(evSession($officer))
        ->postJson(route('officer.activities.ai-proposal'), evProposal());

    // The form says "open to all" with nothing ticked, which is what this is.
    expect($response->json('draft.target_year_levels'))->toBe([]);
});

it('P5: discards drafted year levels that are not 1 to 4', function () {
    evFakeGemini(['target_year_levels' => [3, 1, '2', 9, 0, 1, 'first']]);

    [$officer, $club] = evOfficer();
    evRoster($club, 1, 3);

    $response = $this->withSession(evSession($officer))
        ->postJson(route('officer.activities.ai-proposal'), evProposal());

    expect($response->json('draft.target_year_levels'))->toBe([1, 2, 3]);
});

it('P5: still returns the prose when the model omits the two form fields', function () {
    // The prose is what makes a draft usable; the rest degrades to an empty field.
    config(['gemini.api_key' => 'test-key']);

    Http::fake(['*generativelanguage*' => Http::response([
        'candidates' => [[
            'content' => ['parts' => [[
                'text' => json_encode([
                    'description' => 'A drafted description of the activity.',
                    'purpose'     => 'A drafted purpose and objectives statement.',
                ]),
            ]]],
        ]],
    ])]);

    [$officer, $club] = evOfficer();
    evRoster($club, 2, 4);

    $response = $this->withSession(evSession($officer))
        ->postJson(route('officer.activities.ai-proposal'), evProposal());

    expect($response->json('draft.purpose'))->toBe('A drafted purpose and objectives statement.')
        ->and($response->json('draft.expected_participants'))->toBeNull()
        ->and($response->json('draft.target_year_levels'))->toBe([]);
});

it('P5: still answers the availability question when Gemini is unavailable', function () {
    // POLICY: "keep manual alternatives fully functional" and flag the draft.
    config(['gemini.api_key' => '']);
    Http::fake();

    $date = now()->addDays(21)->toDateString();

    [, $clubA] = evOfficer();
    evBooking($clubA, $date, '13:00', '16:00', 'AVR', 'Existing Gym Booking');

    [$officerB] = evOfficer();

    $response = $this->withSession(evSession($officerB))
        ->postJson(route('officer.activities.ai-proposal'), evProposal(['date' => $date]));

    $response->assertOk();

    expect($response->json('ai_available'))->toBeFalse()
        ->and($response->json('draft'))->toBeNull()
        ->and($response->json('notice'))->toBe('AI Unavailable — Manual Draft Required.')
        // The part that matters: availability is still answered correctly.
        ->and($response->json('venue_available'))->toBeFalse();

    Http::assertNothingSent();
});

it('P5: degrades gracefully when the Gemini call itself fails', function () {
    config(['gemini.api_key' => 'test-key']);
    Http::fake(['*generativelanguage*' => Http::response('upstream error', 500)]);

    [$officer] = evOfficer();

    $response = $this->withSession(evSession($officer))
        ->postJson(route('officer.activities.ai-proposal'), evProposal());

    $response->assertOk();

    expect($response->json('draft'))->toBeNull()
        ->and($response->json('notice'))->toBe('AI Unavailable — Manual Draft Required.')
        ->and($response->json('venue_available'))->toBeTrue();
});

it('P5: the submission gate is enforced server-side, not just advised', function () {
    // The proposal saying "unavailable" is backed by store() actually refusing —
    // an officer who ignores the endpoint still cannot book the slot.
    $date = now()->addDays(21)->toDateString();
    evFakeGemini();

    [, $clubA] = evOfficer();
    evBooking($clubA, $date, '13:00', '16:00', 'AVR', 'Existing Gym Booking');

    [$officerB] = evOfficer();

    $advice = $this->withSession(evSession($officerB))
        ->postJson(route('officer.activities.ai-proposal'), evProposal(['date' => $date]));

    expect($advice->json('can_submit'))->toBeFalse();

    $this->withSession(evSession($officerB))
        ->from(route('officer.activities.create'))
        ->post(route('officer.activities.store'), evPayload([
            'title'      => 'AI Drafted Activity',
            'date'       => $date,
            'time_start' => '14:00',
            'time_end'   => '15:00',
            'venue'      => 'AVR',
        ]))->assertSessionHasErrors('venue');

    expect(ClubActivity::where('title', 'AI Drafted Activity')->exists())->toBeFalse();
});

it('P5: rejects a venue outside the SCC list', function () {
    // POLICY: venues must come from the configured list, not free text.
    evFakeGemini();
    [$officer] = evOfficer();

    $this->withSession(evSession($officer))
        ->postJson(route('officer.activities.ai-proposal'), evProposal(['venue' => 'Someone\'s Garage']))
        ->assertStatus(422);
});

it('P5: is not reachable by a member or adviser', function (string $role) {
    evFakeGemini();
    $user = evUser($role);

    $this->withSession(evSession($user))
        ->postJson(route('officer.activities.ai-proposal'), evProposal())
        ->assertForbidden();
})->with(['member', 'adviser']);
