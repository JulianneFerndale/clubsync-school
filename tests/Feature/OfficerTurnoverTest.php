<?php

use App\Jobs\NotifyOfficerTurnoverDue;
use App\Models\Club;
use App\Models\ClubNotification;
use App\Models\ClubOfficer;
use App\Models\Semester;
use App\Models\User;
use App\Services\OfficerTurnover;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/*
 * Officers turn over with the school year: the summer semester closes one and the
 * next begins, and each club appoints a new set.
 *
 * Nothing is revoked on a date. The sitting officers keep working until the club
 * actually replaces them, so a club is never left unable to run itself because a term
 * rolled over — what changes is that the club is asked, and the DSA can see who has
 * not answered.
 */

function otUser(string $role = 'adviser'): User
{
    static $n = 0;
    $n++;

    return User::create([
        'first_name'   => 'Turnover',
        'last_name'    => "Tester{$n}",
        'edp_number'   => str_pad((string) (800 + $n), 10, '0', STR_PAD_LEFT),
        'email'        => "ot{$n}@sccpag.edu.ph",
        'password'     => Hash::make('Str0ng!Pass1'),
        'role'         => $role,
        'course_id'    => testCourse()->id,
        'is_admin'     => false,
        'is_suspended' => false,
    ]);
}

function otClub(?User $adviser = null): Club
{
    static $n = 0;
    $n++;

    return Club::create([
        'slug'            => "turnover-club-{$n}",
        'acronym'         => "TO{$n}",
        'club_type'       => 'Non-Academic',
        'type'            => 'non_academic',
        'course_slug'     => 'all',
        'department_slug' => 'all',
        'name'            => "Turnover Club {$n}",
        'is_active'       => true,
        'adviser_id'      => $adviser?->id,
    ]);
}

/** The school year: 1st and 2nd semesters, then the summer that closes it. */
function otYear(string $academicYear, int $startYear, bool $currentTerm = true): Semester
{
    Semester::create([
        'label' => "1st Semester, A.Y. {$academicYear}", 'term' => 'first', 'academic_year' => $academicYear,
        'start_date' => "{$startYear}-08-10", 'end_date' => "{$startYear}-12-18", 'is_current' => false,
    ]);

    Semester::create([
        'label' => "2nd Semester, A.Y. {$academicYear}", 'term' => 'second', 'academic_year' => $academicYear,
        'start_date' => ($startYear + 1) . '-01-08', 'end_date' => ($startYear + 1) . '-05-20', 'is_current' => false,
    ]);

    return Semester::create([
        'label' => "Summer, A.Y. {$academicYear}", 'term' => 'summer', 'academic_year' => $academicYear,
        'start_date' => ($startYear + 1) . '-06-01', 'end_date' => ($startYear + 1) . '-07-25',
        'is_current' => $currentTerm,
    ]);
}

function otAppoint(Club $club, string $assignedAt): ClubOfficer
{
    return ClubOfficer::create([
        'club_id'     => $club->id,
        'user_id'     => otUser('president')->id,
        'position'    => 'president',
        'assigned_at' => $assignedAt,
    ]);
}

function otService(): OfficerTurnover
{
    return app(OfficerTurnover::class);
}

// ── Which school year are we in ─────────────────────────────────────────────

it('reads the school year and its start off the current semester', function () {
    otYear('2026-2027', 2026);   // summer flagged current

    expect(otService()->academicYear())->toBe('2026-2027')
        // The year began with its first semester, not with the term we are in.
        ->and(otService()->yearStart()->toDateString())->toBe('2026-08-10');
});

it('knows summer is the term that closes the year', function () {
    $summer = otYear('2026-2027', 2026);

    expect($summer->isSummer())->toBeTrue()
        ->and(Semester::where('term', 'first')->first()->isSummer())->toBeFalse();
});

// ── Who still owes a new set ────────────────────────────────────────────────

it('leaves a club alone once it has appointed anyone this school year', function () {
    otYear('2026-2027', 2026);

    $club = otClub();
    otAppoint($club, '2026-09-01');   // after the year began

    expect(otService()->isPending($club))->toBeFalse()
        ->and(otService()->pendingClubs())->toBeEmpty();
});

it('flags a club still running last school year officers', function () {
    otYear('2026-2027', 2026);

    $club = otClub();
    otAppoint($club, '2025-09-01');   // appointed a school year ago

    expect(otService()->isPending($club))->toBeTrue()
        ->and(otService()->pendingClubs()->pluck('id'))->toContain($club->id);
});

it('flags a club with no officers at all', function () {
    otYear('2026-2027', 2026);

    expect(otService()->isPending(otClub()))->toBeTrue();
});

it('flags nobody when no semester is flagged current', function () {
    // Not knowing the year is not the same as everyone being overdue — flagging all
    // 42 clubs off a missing setting would be worse than saying nothing.
    otYear('2026-2027', 2026, currentTerm: false);

    $club = otClub();
    otAppoint($club, '2020-01-01');

    expect(otService()->academicYear())->toBeNull()
        ->and(otService()->isPending($club))->toBeFalse()
        ->and(otService()->pendingClubs())->toBeEmpty();
});

it('leaves inactive clubs out of the list', function () {
    otYear('2026-2027', 2026);

    $club = otClub();
    $club->update(['is_active' => false]);

    expect(otService()->pendingClubs())->toBeEmpty();
});

// ── The sitting officers keep working ───────────────────────────────────────

it('takes nothing away from the officers it is waiting on', function () {
    // The whole point of the chosen design: a club that has not appointed this
    // year's officers still has working ones.
    otYear('2026-2027', 2026);

    $club    = otClub();
    $officer = otAppoint($club, '2025-09-01');

    (new NotifyOfficerTurnoverDue)->handle(otService());

    expect(otService()->isPending($club))->toBeTrue()
        ->and($officer->fresh())->not->toBeNull()
        ->and(User::find($officer->user_id)->role)->toBe('president');
});

// ── Asking ──────────────────────────────────────────────────────────────────

it('asks the adviser of each club that still owes officers', function () {
    otYear('2026-2027', 2026);

    $adviser = otUser('adviser');
    $club    = otClub($adviser);
    otAppoint($club, '2025-09-01');

    (new NotifyOfficerTurnoverDue)->handle(otService());

    $notification = ClubNotification::where('recipient_id', $adviser->id)->first();

    expect($notification)->not->toBeNull()
        ->and($notification->title)->toBe('New officers needed')
        ->and($notification->body)->toContain('2026-2027')
        ->and($notification->body)->toContain('keep their access')
        ->and($notification->action_url)->toBe(route('clubs.officers.index'));
});

it('gives the DSA one summary rather than one per club', function () {
    otYear('2026-2027', 2026);

    $dsa = otUser('dsa');

    foreach (range(1, 3) as $i) {
        otAppoint(otClub(otUser('adviser')), '2025-09-01');
    }

    (new NotifyOfficerTurnoverDue)->handle(otService());

    $summaries = ClubNotification::where('recipient_id', $dsa->id)->get();

    expect($summaries)->toHaveCount(1)
        ->and($summaries->first()->body)->toContain('3 club(s)');
});

it('asks only once per school year, however often it runs', function () {
    otYear('2026-2027', 2026);

    $adviser = otUser('adviser');
    $dsa     = otUser('dsa');
    otAppoint(otClub($adviser), '2025-09-01');

    foreach (range(1, 3) as $run) {
        (new NotifyOfficerTurnoverDue)->handle(otService());
    }

    expect(ClubNotification::where('recipient_id', $adviser->id)->count())->toBe(1)
        ->and(ClubNotification::where('recipient_id', $dsa->id)->count())->toBe(1);
});

it('says nothing when every club has appointed its officers', function () {
    otYear('2026-2027', 2026);

    otUser('dsa');
    otAppoint(otClub(otUser('adviser')), '2026-09-01');

    (new NotifyOfficerTurnoverDue)->handle(otService());

    expect(ClubNotification::count())->toBe(0);
});

it('runs on a daily schedule', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($e) => str_contains((string) $e->description, 'NotifyOfficerTurnoverDue'));

    expect($events)->not->toBeEmpty()
        ->and($events->first()->expression)->toBe('0 7 * * *');
});

// ── What the adviser and the DSA see ────────────────────────────────────────

function otSession(User $user): array
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

it('shows the adviser the ask on their own officers page', function () {
    otYear('2026-2027', 2026);

    $adviser = otUser('adviser');
    otAppoint(otClub($adviser), '2025-09-01');

    $this->withSession(otSession($adviser))
        ->get(route('clubs.officers.index'))
        ->assertOk()
        ->assertSee('New officers needed for A.Y. 2026-2027')
        ->assertSee("Record this year's officers", false);   // literal apostrophe, not the entity
});

it('does not show the ask to an adviser whose club has appointed officers', function () {
    otYear('2026-2027', 2026);

    $adviser = otUser('adviser');
    otAppoint(otClub($adviser), '2026-09-01');

    $this->withSession(otSession($adviser))
        ->get(route('clubs.officers.index'))
        ->assertOk()
        ->assertDontSee('New officers needed');
});

it('lists the clubs still owing officers on the DSA dashboard', function () {
    otYear('2026-2027', 2026);

    $club = otClub(otUser('adviser'));
    otAppoint($club, '2025-09-01');

    $this->withSession(otSession(otUser('dsa')))
        ->get(route('dsa.dashboard'))
        ->assertOk()
        ->assertSee('have not appointed officers for A.Y. 2026-2027')
        ->assertSee($club->name);
});

it('accepts summer on an officer record, which the form used to refuse', function () {
    otYear('2026-2027', 2026);

    $adviser = otUser('adviser');
    otClub($adviser);

    $this->withSession(otSession($adviser))
        ->post(route('clubs.officers.store'), [
            'full_name'         => 'Maria Santos',
            'position'          => 'President',
            'student_id_number' => '0123456789',
            'contact_email'     => 'maria.santos@sccpag.edu.ph',
            'academic_year'     => '2026-2027',
            'semester'          => 'summer',
        ])->assertSessionHasNoErrors();
});
