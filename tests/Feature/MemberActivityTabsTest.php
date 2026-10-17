<?php

use App\Models\Attendance;
use App\Models\Club;
use App\Models\ClubActivity;
use App\Models\ClubMember;
use App\Models\ClubOfficer;
use App\Models\Fee;
use App\Models\FeePayment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/*
 * The member Activity page: an Upcoming panel (the default), an Attendance panel and a
 * Fees panel.
 *
 * The tab lives in the URL rather than in browser state, because the page opts into
 * live refresh — it re-fetches its own URL and swaps the markup in, which would throw
 * away a tab held only on the client.
 */

function matUser(string $role = 'member'): User
{
    static $n = 0;
    $n++;

    return User::create([
        'first_name'   => 'Tab',
        'last_name'    => "Member{$n}",
        'edp_number'   => str_pad((string) (700 + $n), 10, '0', STR_PAD_LEFT),
        'email'        => "mat{$n}@sccpag.edu.ph",
        'password'     => Hash::make('Str0ng!Pass1'),
        'role'         => $role,
        'course_id'    => testCourse()->id,
        'is_admin'     => false,
        'is_suspended' => false,
    ]);
}

function matSession(User $user): array
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

function matClub(): Club
{
    static $n = 0;
    $n++;

    return Club::create([
        'slug'            => "tab-club-{$n}",
        'acronym'         => "TBC{$n}",
        'club_type'       => 'Non-Academic',
        'type'            => 'non_academic',
        'course_slug'     => 'all',
        'department_slug' => 'all',
        'name'            => "Tab Club {$n}",
        'is_active'       => true,
    ]);
}

/** A club, a member in it, and a treasurer who can confirm payments. */
function matSetup(): array
{
    $club   = matClub();
    $member = matUser();
    $officer = matUser('treasurer');

    ClubMember::create(['club_id' => $club->id, 'user_id' => $member->id, 'status' => 'active', 'role' => 'Member']);
    ClubOfficer::create(['club_id' => $club->id, 'user_id' => $officer->id, 'position' => 'treasurer']);

    return [$club, $member, $officer];
}

function matActivity(Club $club, User $creator, string $title, string $date): ClubActivity
{
    return ClubActivity::create([
        'club_id'               => $club->id,
        'title'                 => $title,
        'description'           => 'A club activity.',
        'date'                  => $date,
        'time_start'            => '09:00',
        'time_end'              => '11:00',
        'venue'                 => 'Others',
        'purpose'               => 'A club activity.',
        'expected_participants' => 30,
        'activity_type'         => 'internal_meeting',
        'approval_status'       => 'no_approval_needed',
        'status'                => 'scheduled',
        'created_by'            => $creator->id,
    ]);
}

function matFee(Club $club, User $member, float $amount, ?string $due = null): FeePayment
{
    static $n = 0;
    $n++;

    $fee = Fee::create([
        'club_id'         => $club->id,
        'title'           => "Club Fee {$n}",
        'amount'          => $amount,
        'due_date'        => $due ?? now()->addDays(10)->toDateString(),
        'academic_period' => '1st Semester, A.Y. 2026-2027',
    ]);

    return FeePayment::create(['fee_id' => $fee->id, 'user_id' => $member->id, 'status' => 'pending']);
}

// ── Switching tabs ──────────────────────────────────────────────────────────

it('opens on the upcoming tab', function () {
    [$club, $member, $officer] = matSetup();

    matActivity($club, $officer, 'Planning Huddle', now()->addDays(4)->toDateString());
    $activity = matActivity($club, $officer, 'Leadership Seminar', now()->subDays(3)->toDateString());
    Attendance::create([
        'event_id' => $activity->id,
        'user_id'  => $member->id,
        'time_in'  => now()->subDays(3)->setTime(9, 5),
    ]);
    matFee($club, $member, 200);

    $this->withSession(matSession($member))
        ->get(route('member.activity'))
        ->assertOk()
        ->assertSee('Planning Huddle')
        // The other panels are not rendered at all, so nothing from them can show through.
        ->assertDontSee('Leadership Seminar')
        ->assertDontSee('Total due');
});

it('shows the attendance panel when the tab is in the url', function () {
    [$club, $member, $officer] = matSetup();

    $activity = matActivity($club, $officer, 'Leadership Seminar', now()->subDays(3)->toDateString());
    Attendance::create([
        'event_id' => $activity->id,
        'user_id'  => $member->id,
        'time_in'  => now()->subDays(3)->setTime(9, 5),
    ]);

    $this->withSession(matSession($member))
        ->get(route('member.activity', ['tab' => 'attendance']))
        ->assertOk()
        ->assertSee('Leadership Seminar')
        ->assertDontSee('Total due');
});

it('shows the fees panel when the tab is in the url', function () {
    [$club, $member, $officer] = matSetup();

    $activity = matActivity($club, $officer, 'Leadership Seminar', now()->subDays(3)->toDateString());
    Attendance::create([
        'event_id' => $activity->id,
        'user_id'  => $member->id,
        'time_in'  => now()->subDays(3)->setTime(9, 5),
    ]);
    matFee($club, $member, 200);

    $this->withSession(matSession($member))
        ->get(route('member.activity', ['tab' => 'fees']))
        ->assertOk()
        ->assertSee('Total due')
        ->assertSee('Club Fee')
        ->assertDontSee('Leadership Seminar');
});

it('falls back to the upcoming tab for an unknown tab', function () {
    [, $member] = matSetup();

    $this->withSession(matSession($member))
        ->get(route('member.activity', ['tab' => 'nonsense']))
        ->assertOk()
        ->assertSee('No upcoming activities')
        ->assertDontSee('No attendance records yet')
        ->assertDontSee('No pending fees.');
});

it('links each tab to the other panel', function () {
    [, $member] = matSetup();
    $session = matSession($member);

    $this->withSession($session)->get(route('member.activity'))
        ->assertSee(route('member.activity', ['tab' => 'attendance']), false)
        ->assertSee(route('member.activity', ['tab' => 'fees']), false);

    $this->withSession($session)->get(route('member.activity', ['tab' => 'fees']))
        ->assertSee(route('member.activity'), false);
});

// ── What each panel says ────────────────────────────────────────────────────

it('totals what is still owed', function () {
    [$club, $member] = matSetup();

    matFee($club, $member, 150.50);
    matFee($club, $member, 99.50);

    $this->withSession(matSession($member))
        ->get(route('member.activity', ['tab' => 'fees']))
        ->assertOk()
        ->assertSee('250.00')
        ->assertSee('2 unpaid fees');
});

it('leaves a paid fee out of what is owed', function () {
    [$club, $member, $officer] = matSetup();

    $unpaid = matFee($club, $member, 150.00);
    $paid   = matFee($club, $member, 80.00);

    $this->withSession(matSession($officer))
        ->post(route('officer.fees.paid', [$paid->fee_id, $member->id]));

    $this->withSession(matSession($member))
        ->get(route('member.activity', ['tab' => 'fees']))
        ->assertOk()
        ->assertSee('150.00')
        ->assertSee('1 unpaid fee')
        ->assertDontSee('2 unpaid');

    expect($unpaid->fresh()->status)->toBe('pending');
});

it('lists attendance newest activity first', function () {
    [$club, $member, $officer] = matSetup();

    foreach ([['Older Assembly', 40], ['Newer Assembly', 2]] as [$title, $daysAgo]) {
        $activity = matActivity($club, $officer, $title, now()->subDays($daysAgo)->toDateString());
        Attendance::create([
            'event_id' => $activity->id,
            'user_id'  => $member->id,
            'time_in'  => now()->subDays($daysAgo)->setTime(8, 30),
        ]);
    }

    $html = $this->withSession(matSession($member))->get(route('member.activity', ['tab' => 'attendance']))->getContent();

    expect(strpos($html, 'Newer Assembly'))->toBeLessThan(strpos($html, 'Older Assembly'));
});

it('drops attendance for an activity that was deleted', function () {
    // attendance.event_id is NOT NULL and cascades, so a record never outlives its
    // activity — the panel has nothing dangling to render.
    [$club, $member, $officer] = matSetup();

    $activity = matActivity($club, $officer, 'Cancelled Outreach', now()->subDays(5)->toDateString());
    Attendance::create([
        'event_id' => $activity->id,
        'user_id'  => $member->id,
        'time_in'  => now()->subDays(5)->setTime(10, 0),
    ]);

    $activity->delete();

    expect(Attendance::count())->toBe(0);

    $this->withSession(matSession($member))
        ->get(route('member.activity', ['tab' => 'attendance']))
        ->assertOk()
        ->assertSee('No attendance records yet')
        ->assertDontSee('Cancelled Outreach');
});

it('shows only the signed-in member\'s own records', function () {
    [$club, $member, $officer] = matSetup();
    $other = matUser();
    ClubMember::create(['club_id' => $club->id, 'user_id' => $other->id, 'status' => 'active', 'role' => 'Member']);

    $activity = matActivity($club, $officer, 'Members Only Session', now()->subDay()->toDateString());
    Attendance::create(['event_id' => $activity->id, 'user_id' => $other->id, 'time_in' => now()->subDay()]);
    matFee($club, $other, 500);

    $session = matSession($member);

    $this->withSession($session)->get(route('member.activity', ['tab' => 'attendance']))
        ->assertOk()
        ->assertSee('No attendance records yet');

    $this->withSession($session)->get(route('member.activity', ['tab' => 'fees']))
        ->assertOk()
        ->assertSee('No pending fees.')
        ->assertDontSee('500.00');
});

// ── The receipt a member can take away ──────────────────────────────────────

it('offers a receipt only once the payment is confirmed', function () {
    [$club, $member, $officer] = matSetup();
    $payment = matFee($club, $member, 120);
    $session = matSession($member);

    $this->withSession($session)->get(route('member.activity', ['tab' => 'fees']))
        ->assertDontSee(route('member.activity.receipt', $payment), false);

    $this->withSession(matSession($officer))
        ->post(route('officer.fees.paid', [$payment->fee_id, $member->id]));

    $this->withSession($session)->get(route('member.activity', ['tab' => 'fees']))
        ->assertSee($payment->fresh()->receipt_number)
        ->assertSee(route('member.activity.receipt', $payment), false);
});

it('downloads the member\'s own receipt as a pdf', function () {
    [$club, $member, $officer] = matSetup();
    $payment = matFee($club, $member, 120);

    $this->withSession(matSession($officer))
        ->post(route('officer.fees.paid', [$payment->fee_id, $member->id]));

    $number = $payment->fresh()->receipt_number;

    $this->withSession(matSession($member))
        ->get(route('member.activity.receipt', $payment))
        ->assertOk()
        ->assertHeader('content-type', 'application/pdf')
        ->assertDownload("receipt-{$number}.pdf");
});

it('never hands a member someone else\'s receipt', function () {
    [$club, $member, $officer] = matSetup();
    $other = matUser();
    ClubMember::create(['club_id' => $club->id, 'user_id' => $other->id, 'status' => 'active', 'role' => 'Member']);

    $payment = matFee($club, $other, 120);

    $this->withSession(matSession($officer))
        ->post(route('officer.fees.paid', [$payment->fee_id, $other->id]));

    $this->withSession(matSession($member))
        ->get(route('member.activity.receipt', $payment))
        ->assertForbidden();
});

it('has no receipt to give for an unconfirmed payment', function () {
    [$club, $member] = matSetup();
    $payment = matFee($club, $member, 120);

    $this->withSession(matSession($member))
        ->get(route('member.activity.receipt', $payment))
        ->assertNotFound();
});

test('receipts are never cached by the service worker', function () {
    // A receipt PDF carries the member's name and EDP number; the network-first
    // handler would otherwise leave it in Cache Storage on a shared computer.
    expect(file_get_contents(public_path('sw.js')))
        ->toContain('/\\/receipt(?:\\/|$)/.test(url.pathname)');
});
