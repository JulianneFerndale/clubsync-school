<?php

use App\Models\Club;
use App\Models\ClubActivity;
use App\Models\ClubMember;
use App\Models\ClubOfficer;
use App\Models\Fee;
use App\Models\GoogleCalendarEvent;
use App\Models\Semester;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/*
 * Google Calendar sync — confirmed activities, fee due dates and semester dates
 * are copied into the personal Google Calendar of each user they concern.
 * Google itself is replaced by an in-memory calendar per user.
 */

// ── Fixtures ─────────────────────────────────────────────────────────────────

/** An in-memory Google: one calendar per Workspace user, keyed by email. */
class FakeGoogleCalendarService extends GoogleCalendarService
{
    /** @var array<string, array<string, array>> email => eventId => payload */
    public array $calendars = [];

    /** @var list<array{0: string, 1: string}> [action, email] */
    public array $calls = [];

    /** @var list<string> emails whose API calls fail */
    public array $failingFor = [];

    private int $nextId = 0;

    public function enabled(): bool
    {
        return true;
    }

    public function createEvent(string $subject, array $event): string
    {
        $this->record('create', $subject);
        $id = 'evt' . ++$this->nextId;
        $this->calendars[$subject][$id] = $event;

        return $id;
    }

    public function updateEvent(string $subject, string $eventId, array $event): bool
    {
        $this->record('update', $subject);

        if (! isset($this->calendars[$subject][$eventId])) {
            return false;
        }

        $this->calendars[$subject][$eventId] = $event;

        return true;
    }

    public function deleteEvent(string $subject, string $eventId): void
    {
        $this->record('delete', $subject);
        unset($this->calendars[$subject][$eventId]);
    }

    /** @return list<array> */
    public function eventsOf(User $user): array
    {
        return array_values($this->calendars[$user->email] ?? []);
    }

    public function callsFor(User $user): int
    {
        return count(array_filter($this->calls, fn ($call) => $call[1] === $user->email));
    }

    private function record(string $action, string $subject): void
    {
        $this->calls[] = [$action, $subject];

        if (in_array($subject, $this->failingFor, true)) {
            throw new RuntimeException('Simulated Google outage');
        }
    }
}

function gcFake(): FakeGoogleCalendarService
{
    $fake = new FakeGoogleCalendarService();
    app()->instance(GoogleCalendarService::class, $fake);

    return $fake;
}

function gcUser(string $role = 'member', array $attributes = []): User
{
    static $n = 0;
    $n++;

    return User::create($attributes + [
        'first_name'   => 'Calendar',
        'last_name'    => "Tester{$n}",
        'edp_number'   => str_pad((string) (7000 + $n), 10, '0', STR_PAD_LEFT),
        'email'        => "gc{$n}@sccpag.edu.ph",
        'password'     => Hash::make('Password1!'),
        'role'         => $role,
        'course_id'    => testCourse()->id,
        'is_admin'     => false,
        'is_suspended' => false,
    ]);
}

function gcSession(User $user): array
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

/** A club with an adviser and a president. */
function gcClub(): array
{
    static $n = 0;
    $n++;

    $adviser = gcUser('adviser');
    $officer = gcUser('president');

    $club = Club::create([
        'slug'            => 'calendar-club-' . $n,
        'acronym'         => 'CAL' . $n,
        'club_type'       => 'Non-Academic',
        'type'            => 'non_academic',
        'course_slug'     => 'all',
        'department_slug' => 'all',
        'name'            => 'Calendar Club ' . $n,
        'is_active'       => true,
        'adviser_id'      => $adviser->id,
    ]);

    ClubOfficer::create(['club_id' => $club->id, 'user_id' => $officer->id, 'position' => 'president']);

    return [$club, $officer, $adviser];
}

function gcMember(Club $club, string $status = 'active', array $attributes = []): User
{
    $user = gcUser('member', $attributes);

    ClubMember::create(['club_id' => $club->id, 'user_id' => $user->id, 'status' => $status, 'role' => 'Member']);

    return $user;
}

function gcActivityPayload(array $overrides = []): array
{
    return array_merge([
        'title'                 => 'General Assembly',
        'description'           => 'Semestral general assembly.',
        'date'                  => now()->addDays(10)->toDateString(),
        'time_start'            => '09:00',
        'time_end'              => '11:00',
        'venue'                 => 'AVR',
        'purpose'               => 'Orient members.',
        'expected_participants' => 80,
        'activity_type'         => 'internal_meeting',
        'confirmed'             => 1,
    ], $overrides);
}

function gcBooking(Club $club, User $creator, array $overrides = []): ClubActivity
{
    return ClubActivity::create($overrides + [
        'club_id'               => $club->id,
        'title'                 => 'Existing Meeting',
        'description'           => 'Already on the books.',
        'date'                  => now()->addDays(5)->toDateString(),
        'time_start'            => '13:00',
        'time_end'              => '14:00',
        'venue'                 => 'Moot Court',
        'purpose'               => 'Planning.',
        'expected_participants' => 20,
        'activity_type'         => 'internal_meeting',
        'approval_status'       => 'no_approval_needed',
        'status'                => 'scheduled',
        'created_by'            => $creator->id,
    ]);
}

/** The VPA's account, which approves every activity's room booking letter last. */
function gcVpa(): User
{
    return User::where('role', 'vpa')->first() ?? gcUser('vpa');
}

/** Take an activity through both approvals — the adviser's, then the VPA's. */
function gcApprove(ClubActivity $activity, User $adviser): ClubActivity
{
    test()->withSession(gcSession($adviser))->post(route('adviser.activities.approve', $activity));
    test()->flushSession();
    test()->withSession(gcSession(gcVpa()))->post(route('vpa.letters.approve', $activity));
    test()->flushSession();

    return $activity->fresh();
}

/** Add an activity as $officer and have it approved, so it is confirmed. */
function gcConfirmed(User $officer, User $adviser, array $overrides = []): ClubActivity
{
    test()->withSession(gcSession($officer))
        ->post(route('officer.activities.store'), gcActivityPayload($overrides))
        ->assertSessionHasNoErrors();
    test()->flushSession();

    return gcApprove(ClubActivity::latest('id')->firstOrFail(), $adviser);
}

// ── Activities ───────────────────────────────────────────────────────────────

it('puts a confirmed activity on the calendars of the club\'s active members, officers and adviser only', function () {
    $google = gcFake();
    [$club, $officer, $adviser] = gcClub();

    $member   = gcMember($club);
    $pending  = gcMember($club, 'pending');
    [$otherClub] = gcClub();
    $outsider = gcMember($otherClub);
    $dsa      = gcUser('dsa');

    // Every activity, this internal meeting included, waits for the VPA and the adviser.
    $this->withSession(gcSession($officer))
        ->post(route('officer.activities.store'), gcActivityPayload())
        ->assertSessionHasNoErrors();
    $this->flushSession();

    expect($google->eventsOf($member))->toBeEmpty();

    gcApprove(ClubActivity::firstOrFail(), $adviser);

    foreach ([$member, $officer, $adviser] as $user) {
        expect($google->eventsOf($user))->toHaveCount(1);
    }

    foreach ([$pending, $outsider, $dsa] as $user) {
        expect($google->eventsOf($user))->toBeEmpty();
    }

    $event = $google->eventsOf($member)[0];

    expect($event['summary'])->toBe('General Assembly (' . $club->acronym . ')')
        ->and($event['location'])->toBe('AVR')
        ->and($event['start'])->toBe(['dateTime' => now()->addDays(10)->toDateString() . 'T09:00:00', 'timeZone' => 'Asia/Manila'])
        ->and($event['end']['dateTime'])->toEndWith('T11:00:00')
        ->and($event['description'])->toContain($club->name);

    expect(GoogleCalendarEvent::count())->toBe(3);
});

it('keeps an approval-requiring activity off calendars until the adviser approves it, and removes it on rejection', function () {
    $google = gcFake();
    [$club, $officer, $adviser] = gcClub();
    $member = gcMember($club);

    $this->withSession(gcSession($officer))
        ->post(route('officer.activities.store'), gcActivityPayload(['title' => 'ACLE Outreach', 'activity_type' => 'acle']));

    $activity = ClubActivity::where('title', 'ACLE Outreach')->firstOrFail();
    expect($google->eventsOf($member))->toBeEmpty();

    // The adviser approves the room booking letter first; that alone confirms nothing.
    $this->withSession(gcSession($adviser))->post(route('adviser.activities.approve', $activity));
    expect($activity->fresh()->approval_status)->toBe('pending_vpa')
        ->and($google->eventsOf($member))->toBeEmpty();

    $this->withSession(gcSession(gcVpa()))->post(route('vpa.letters.approve', $activity));
    expect($google->eventsOf($member))->toHaveCount(1);

    $this->withSession(gcSession($adviser))->post(route('adviser.activities.reject', $activity), ['review_remarks' => 'Venue unavailable after all.']);
    expect($google->eventsOf($member))->toBeEmpty()
        ->and(GoogleCalendarEvent::count())->toBe(0);
});

it('takes an edited activity off calendars until it is approved again, then holds one copy, not two', function () {
    $google = gcFake();
    [$club, $officer, $adviser] = gcClub();
    $member = gcMember($club);

    $activity = gcConfirmed($officer, $adviser);
    expect($google->eventsOf($member))->toHaveCount(1);

    $this->withSession(gcSession($officer))
        ->patch(route('officer.activities.update', $activity), gcActivityPayload([
            'venue'       => 'Library',
            'letter_body' => 'We will hold the General Assembly in the Library instead, and keep it clean.',
        ]))
        ->assertSessionHasNoErrors();
    $this->flushSession();

    // Back with the VPA: not confirmed, so off the member's calendar for now.
    expect($google->eventsOf($member))->toBeEmpty();

    gcApprove($activity, $adviser);

    expect($google->eventsOf($member))->toHaveCount(1)
        ->and($google->eventsOf($member)[0]['location'])->toBe('Library')
        ->and(GoogleCalendarEvent::where('user_id', $member->id)->count())->toBe(1);
});

it('makes no Google API call when an activity is saved unchanged', function () {
    $google = gcFake();
    [$club, $officer, $adviser] = gcClub();
    gcMember($club);

    $activity = gcConfirmed($officer, $adviser);
    $callsAfterApproval = count($google->calls);

    $this->withSession(gcSession($officer))
        ->patch(route('officer.activities.update', $activity), gcActivityPayload(['letter_body' => $activity->letter_body]))
        ->assertSessionHasNoErrors();

    expect($activity->fresh()->approval_status)->toBe('approved')
        ->and($google->calls)->toHaveCount($callsAfterApproval);
});

it('recreates the copy if the user deleted it in Google and the activity changes', function () {
    $google = gcFake();
    [$club, $officer, $adviser] = gcClub();
    $member = gcMember($club);

    $activity = gcConfirmed($officer, $adviser);

    $google->calendars[$member->email] = []; // the member deleted it from Google Calendar

    $this->withSession(gcSession($officer))
        ->patch(route('officer.activities.update', $activity), gcActivityPayload([
            'title'       => 'Rescheduled Assembly',
            'letter_body' => 'The club will hold its Rescheduled Assembly at the AVR, and keep the room clean.',
        ]))
        ->assertSessionHasNoErrors();
    $this->flushSession();

    gcApprove($activity, $adviser);

    expect($google->eventsOf($member))->toHaveCount(1)
        ->and($google->eventsOf($member)[0]['summary'])->toStartWith('Rescheduled Assembly')
        ->and(GoogleCalendarEvent::where('user_id', $member->id)->count())->toBe(1);
});

it('follows the activity\'s target year levels, including when an edit retargets it', function () {
    $google = gcFake();
    [$club, $officer, $adviser] = gcClub();
    $firstYear  = gcMember($club, 'active', ['year_level' => 1]);
    $fourthYear = gcMember($club, 'active', ['year_level' => 4]);

    $activity = gcConfirmed($officer, $adviser, ['target_year_levels' => [1, 4]]);

    expect($google->eventsOf($firstYear))->toHaveCount(1)
        ->and($google->eventsOf($fourthYear))->toHaveCount(1);

    $this->withSession(gcSession($officer))
        ->patch(route('officer.activities.update', $activity), gcActivityPayload([
            'target_year_levels' => [1],
            'letter_body'        => $activity->letter_body,
        ]))
        ->assertSessionHasNoErrors();
    $this->flushSession();

    gcApprove($activity, $adviser);

    expect($google->eventsOf($firstYear))->toHaveCount(1)
        ->and($google->eventsOf($fourthYear))->toBeEmpty()
        ->and($google->eventsOf($officer))->toHaveCount(1); // officers are not filtered by year level
});

// ── Membership ───────────────────────────────────────────────────────────────

it('adds the club\'s upcoming activities when a member is approved, and removes them when they leave', function () {
    $google = gcFake();
    [$club, $officer, $adviser] = gcClub();

    gcBooking($club, $officer, ['title' => 'Next Week Meeting']);
    gcBooking($club, $officer, ['title' => 'Last Month Meeting', 'date' => now()->subMonth()->toDateString()]);
    gcBooking($club, $officer, ['title' => 'Unapproved Outreach', 'activity_type' => 'acle', 'approval_status' => 'pending_approval']);

    $applicant  = gcMember($club, 'pending');
    $membership = ClubMember::where('user_id', $applicant->id)->firstOrFail();

    $this->withSession(gcSession($adviser))->post(route('clubs.members.approve', $membership));

    expect($google->eventsOf($applicant))->toHaveCount(1)
        ->and($google->eventsOf($applicant)[0]['summary'])->toStartWith('Next Week Meeting');

    $this->withSession(gcSession($applicant))->post(route('member.clubs.leave', $club));

    expect($google->eventsOf($applicant))->toBeEmpty()
        ->and(GoogleCalendarEvent::where('user_id', $applicant->id)->count())->toBe(0);
});

it('moves a club\'s activities to the new adviser when the DSA replaces the adviser', function () {
    $google = gcFake();
    [$club, $officer, $oldAdviser] = gcClub();
    $newAdviser = gcUser('adviser');
    $dsa        = gcUser('dsa');

    gcConfirmed($officer, $oldAdviser);
    expect($google->eventsOf($oldAdviser))->toHaveCount(1);

    $this->withSession(gcSession($dsa))->patch(route('dsa.clubs.update', $club), [
        'name'       => $club->name,
        'acronym'    => $club->acronym,
        'type'       => 'non_academic',
        'adviser_id' => $newAdviser->id,
        'is_active'  => 1,
    ])->assertSessionHasNoErrors();

    expect($google->eventsOf($newAdviser))->toHaveCount(1)
        ->and($google->eventsOf($oldAdviser))->toBeEmpty();
});

// ── Fees ─────────────────────────────────────────────────────────────────────

it('puts a fee due date on the calendar of each member who owes it, and removes it once paid', function () {
    $google = gcFake();
    [$club, $officer] = gcClub();
    $member  = gcMember($club);
    $pending = gcMember($club, 'pending');
    $dueDate = now()->addDays(20)->toDateString();

    $this->withSession(gcSession($officer))->post(route('officer.fees.store'), [
        'title'           => 'Membership Fee',
        'amount'          => 150,
        'due_date'        => $dueDate,
        'academic_period' => '1st Semester 2026-2027',
    ])->assertSessionHasNoErrors();

    $fee = Fee::firstOrFail();

    expect($google->eventsOf($pending))->toBeEmpty()
        ->and($google->eventsOf($member))->toHaveCount(1);

    $event = $google->eventsOf($member)[0];

    expect($event['summary'])->toBe('Fee due: Membership Fee (' . $club->acronym . ')')
        ->and($event['start'])->toBe(['date' => $dueDate])
        ->and($event['end'])->toBe(['date' => now()->addDays(21)->toDateString()])
        ->and($event['transparency'])->toBe('transparent')
        ->and($event['description'])->toContain('₱150.00');

    $this->withSession(gcSession($officer))->post(route('officer.fees.paid', [$fee, $member]));
    expect($google->eventsOf($member))->toBeEmpty();

    $this->withSession(gcSession($officer))->post(route('officer.fees.unpaid', [$fee, $member]));
    expect($google->eventsOf($member))->toHaveCount(1);
});

// ── Semesters ────────────────────────────────────────────────────────────────

it('puts semester start and end dates on every institutional user\'s calendar, skipping suspended and outside accounts', function () {
    $google = gcFake();
    $dsa       = gcUser('dsa');
    $student   = gcUser('member');
    $suspended = gcUser('member', ['is_suspended' => true]);
    // 'admin' is not a role but the is_admin flag; the users.role check refuses it.
    $outside   = gcUser('member', ['email' => 'someone@gmail.com', 'is_admin' => true]);

    $this->withSession(gcSession($dsa))->post(route('dsa.semesters.store'), [
        'label'      => '1st Semester 2026-2027',
        'start_date' => '2026-08-10',
        'end_date'   => '2026-12-18',
    ])->assertSessionHasNoErrors();

    $events = collect($google->eventsOf($student))->keyBy('summary');

    expect($events->keys()->sort()->values()->all())->toBe(['1st Semester 2026-2027 begins', '1st Semester 2026-2027 ends'])
        ->and($events['1st Semester 2026-2027 begins']['start'])->toBe(['date' => '2026-08-10'])
        ->and($events['1st Semester 2026-2027 ends']['start'])->toBe(['date' => '2026-12-18'])
        ->and($google->eventsOf($dsa))->toHaveCount(2)
        ->and($google->eventsOf($suspended))->toBeEmpty()
        ->and($google->callsFor($outside))->toBe(0);
});

it('moves the semester copies when the DSA changes the dates', function () {
    $google = gcFake();
    $dsa     = gcUser('dsa');
    $student = gcUser('member');

    $this->withSession(gcSession($dsa))->post(route('dsa.semesters.store'), [
        'label' => '2nd Semester', 'start_date' => '2027-01-11', 'end_date' => '2027-05-21',
    ]);

    $this->withSession(gcSession($dsa))->patch(route('dsa.semesters.update', Semester::firstOrFail()), [
        'label' => '2nd Semester', 'start_date' => '2027-01-18', 'end_date' => '2027-05-21',
    ])->assertSessionHasNoErrors();

    $starts = collect($google->eventsOf($student))->pluck('start.date')->sort()->values()->all();

    expect($starts)->toBe(['2027-01-18', '2027-05-21'])
        ->and(GoogleCalendarEvent::where('user_id', $student->id)->count())->toBe(2);
});

// ── Failure handling & switches ──────────────────────────────────────────────

it('never lets one user\'s Google failure block the action or anyone else\'s copy, and gives up after three tries', function () {
    Log::spy();

    $google = gcFake();
    [$club, $officer, $adviser] = gcClub();
    $healthy = gcMember($club);
    $broken  = gcMember($club);
    $google->failingFor = [$broken->email];

    $activity = gcConfirmed($officer, $adviser);

    expect($activity->approval_status)->toBe('approved')
        ->and(ClubActivity::count())->toBe(1)
        ->and($google->eventsOf($healthy))->toHaveCount(1)
        ->and($google->callsFor($broken))->toBe(3);

    Log::shouldHaveReceived('error')->withArgs(fn ($message) => str_contains($message, 'gave up'))->once();
});

it('does nothing, and calls nothing, while sync is switched off', function () {
    Http::fake();
    config(['google.calendar.enabled' => false]);

    [$club, $officer, $adviser] = gcClub();
    gcMember($club);

    gcConfirmed($officer, $adviser);

    Http::assertNotSent(fn (HttpRequest $request) => str_contains($request->url(), 'googleapis.com'));
    expect(GoogleCalendarEvent::count())->toBe(0);
});

it('backfills existing upcoming records with the google-calendar:sync command', function () {
    $google = gcFake();
    [$club, $officer] = gcClub();
    $member = gcMember($club);

    gcBooking($club, $officer, ['title' => 'Made Before Sync Was On']);
    Semester::create(['label' => 'Current Semester', 'start_date' => now()->subMonth(), 'end_date' => now()->addMonths(3)]);

    $this->artisan('google-calendar:sync')->assertSuccessful();

    expect(collect($google->eventsOf($member))->pluck('summary')->all())
        ->toContain('Made Before Sync Was On (' . $club->acronym . ')')
        ->toContain('Current Semester begins');
});

it('refuses to backfill while sync is switched off', function () {
    config(['google.calendar.enabled' => false]);

    $this->artisan('google-calendar:sync')->assertFailed();
});

// ── The real client's contract with Google ───────────────────────────────────

it('acts as the user through domain-wide delegation and writes to their primary calendar', function () {
    // Windows PHP builds ship their openssl.cnf beside the binary rather than where OpenSSL looks.
    $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
    $bundled = dirname(PHP_BINARY) . '/extras/ssl/openssl.cnf';

    if (is_file($bundled)) {
        $options['config'] = $bundled;
    }

    $key = @openssl_pkey_new($options);

    if (! $key) {
        $this->markTestSkipped('OpenSSL cannot generate a key on this machine.');
    }

    openssl_pkey_export($key, $privateKey, null, $options);
    $keyFile = tempnam(sys_get_temp_dir(), 'gsa');
    file_put_contents($keyFile, json_encode([
        'client_email' => 'clubsync@scc-project.iam.gserviceaccount.com',
        'private_key'  => $privateKey,
        'token_uri'    => 'https://oauth2.googleapis.com/token',
    ]));

    config(['google.calendar.enabled' => true, 'google.calendar.credentials' => $keyFile]);

    Http::fake([
        'oauth2.googleapis.com/token' => Http::response(['access_token' => 'token-for-student']),
        '*/events/gone'               => Http::response([], 410),
        '*/events'                    => Http::response(['id' => 'google-event-1']),
    ]);

    $service = new GoogleCalendarService();

    expect($service->enabled())->toBeTrue()
        ->and($service->canSyncFor('Student@SCCPAG.edu.ph'))->toBeTrue()
        ->and($service->canSyncFor('student@gmail.com'))->toBeFalse()
        ->and($service->createEvent('student@sccpag.edu.ph', ['summary' => 'Test']))->toBe('google-event-1')
        ->and($service->updateEvent('student@sccpag.edu.ph', 'gone', ['summary' => 'Test']))->toBeFalse();

    Http::assertSent(function (HttpRequest $request) {
        if (! str_contains($request->url(), 'oauth2.googleapis.com/token')) {
            return false;
        }

        $claims = json_decode(base64_decode(strtr(explode('.', $request['assertion'])[1], '-_', '+/')), true);

        return $claims['sub'] === 'student@sccpag.edu.ph'
            && $claims['scope'] === 'https://www.googleapis.com/auth/calendar.events';
    });

    Http::assertSent(fn (HttpRequest $request) => $request->method() === 'POST'
        && $request->url() === 'https://www.googleapis.com/calendar/v3/calendars/primary/events'
        && $request->hasHeader('Authorization', 'Bearer token-for-student'));

    @unlink($keyFile);
});
