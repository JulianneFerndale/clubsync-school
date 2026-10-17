<?php

use App\Events\NotificationCreated;
use App\Models\Club;
use App\Models\ClubActivity;
use App\Models\ClubMember;
use App\Models\ClubNotification;
use App\Models\ClubOfficer;
use App\Models\Fee;
use App\Models\FeePayment;
use App\Models\GoogleCalendarEvent;
use App\Models\Semester;
use App\Models\User;
use App\Services\GoogleCalendarSync;
use App\Services\NarrativeReportData;
use App\Support\LiveBroadcast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

/*
 * What an officer does to an activity or a fee reaches the members it concerns as a
 * notification, pushed live (NotificationCreated). A member with My Activity open has
 * the page refreshed in place by that push (resources/js/app.js), so the new activity
 * or the changed fee is on screen without a reload.
 *
 * Fees can be postponed and cancelled; a cancelled fee is owed by no one.
 */

beforeEach(function () {
    LiveBroadcast::resume();
    Event::fake([NotificationCreated::class]);
});

function mlUser(string $role = 'member', ?int $yearLevel = null): User
{
    static $n = 0;
    $n++;

    return User::create([
        'first_name'   => 'Live',
        'last_name'    => "Member{$n}",
        'edp_number'   => str_pad((string) (9100 + $n), 10, '0', STR_PAD_LEFT),
        'email'        => "live.member{$n}@sccpag.edu.ph",
        'password'     => Hash::make('Str0ng!Pass1'),
        'role'         => $role,
        'course_id'    => testCourse()->id,
        'year_level'   => $yearLevel,
        'is_admin'     => false,
        'is_suspended' => false,
    ]);
}

function mlSession(User $user): array
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

/** A club with a treasurer and two active members. */
function mlClub(): array
{
    static $n = 0;
    $n++;

    $club = Club::create([
        'slug' => "live-club-{$n}", 'acronym' => "LVC{$n}", 'club_type' => 'Non-Academic', 'type' => 'non_academic',
        'course_slug' => 'all', 'department_slug' => 'all', 'name' => "Live Club {$n}", 'is_active' => true,
    ]);

    $treasurer = mlUser('treasurer');
    ClubOfficer::create(['club_id' => $club->id, 'user_id' => $treasurer->id, 'position' => 'treasurer']);
    ClubMember::create(['club_id' => $club->id, 'user_id' => $treasurer->id, 'status' => 'active', 'role' => 'Officer']);

    $members = collect([mlUser(), mlUser()])->each(fn (User $m) => ClubMember::create([
        'club_id' => $club->id, 'user_id' => $m->id, 'status' => 'active', 'role' => 'Member',
    ]));

    return [$club, $treasurer, $members];
}

function mlCreateFee(User $treasurer, array $overrides = []): Fee
{
    test()->withSession(mlSession($treasurer))->post(route('officer.fees.store'), array_merge([
        'title'           => 'Org Shirt',
        'amount'          => '250',
        'due_date'        => now()->addDays(10)->toDateString(),
        'academic_period' => '1st Semester, A.Y. 2026-2027',
    ], $overrides))->assertRedirect();

    return Fee::latest('id')->firstOrFail();
}

/** @return \Illuminate\Support\Collection<int, ClubNotification> */
function mlNotes(User $user)
{
    return ClubNotification::where('recipient_id', $user->id)->orderBy('id')->get();
}

function mlActivity(Club $club, User $creator, array $overrides = []): ClubActivity
{
    return ClubActivity::create(array_merge([
        'club_id' => $club->id, 'title' => 'Club Assembly', 'description' => 'An assembly.',
        'date' => now()->addDays(5)->toDateString(), 'time_start' => '09:00', 'time_end' => '11:00',
        'venue' => 'AVR', 'purpose' => 'Assemble.', 'expected_participants' => 30,
        'activity_type' => 'internal_meeting', 'approval_status' => 'no_approval_needed',
        'status' => 'scheduled', 'created_by' => $creator->id,
    ], $overrides));
}

// ── A new activity reaches the member's Upcoming tab, live ──────────────────

it('pushes a new activity to the members and lists it on their Upcoming tab', function () {
    [$club, $treasurer, $members] = mlClub();
    $member = $members->first();

    $this->withSession(mlSession($treasurer))->post(route('officer.activities.store'), [
        'title' => 'Planning Huddle', 'description' => 'Plan the semester.', 'date' => now()->addDays(6)->toDateString(),
        'time_start' => '13:00', 'time_end' => '14:00', 'venue' => 'AVR', 'purpose' => 'Plan the semester.',
        'expected_participants' => 20, 'activity_type' => 'internal_meeting', 'confirmed' => 1,
    ])->assertRedirect();

    // The push carries the club, which is what refreshes an open page (app.js).
    Event::assertDispatched(NotificationCreated::class, fn (NotificationCreated $e) => $e->notification->recipient_id === $member->id
        && $e->broadcastWith()['club_id'] === $club->id);

    $this->withSession(mlSession($member))->get(route('member.activity'))
        ->assertOk()
        ->assertSee('Planning Huddle')
        ->assertSee($club->acronym);
});

it('lists only confirmed, upcoming activities of the member\'s own clubs aimed at their year level', function () {
    [$club, $treasurer] = mlClub();
    $member = mlUser('member', 2);
    ClubMember::create(['club_id' => $club->id, 'user_id' => $member->id, 'status' => 'active', 'role' => 'Member']);
    [$otherClub, $otherTreasurer] = mlClub();

    mlActivity($club, $treasurer, ['title' => 'Confirmed Meeting']);
    mlActivity($club, $treasurer, ['title' => 'Awaiting Adviser', 'activity_type' => 'acle', 'approval_status' => 'pending_approval']);
    mlActivity($club, $treasurer, ['title' => 'Called Off', 'status' => 'cancelled']);
    mlActivity($club, $treasurer, ['title' => 'Already Held', 'date' => now()->subDays(2)->toDateString()]);
    mlActivity($club, $treasurer, ['title' => 'Seniors Only', 'target_year_levels' => [4]]);
    mlActivity($otherClub, $otherTreasurer, ['title' => 'Another Club Meeting']);

    $this->withSession(mlSession($member))->get(route('member.activity'))
        ->assertOk()
        ->assertSee('Confirmed Meeting')
        ->assertDontSee('Awaiting Adviser')
        ->assertDontSee('Called Off')
        ->assertDontSee('Already Held')
        ->assertDontSee('Seniors Only')
        ->assertDontSee('Another Club Meeting');
});

// ── A new fee ───────────────────────────────────────────────────────────────

it('tells every billed member about a new fee, live, but not the officer who added it', function () {
    [$club, $treasurer, $members] = mlClub();

    mlCreateFee($treasurer);

    foreach ($members as $member) {
        $note = mlNotes($member)->sole();
        expect($note->title)->toBe("{$club->acronym} added a new fee")
            ->and($note->body)->toContain('"Org Shirt" — ₱250.00, due')
            ->and($note->club_id)->toBe($club->id)
            ->and($note->action_url)->toBe(route('member.activity', ['tab' => 'fees'], false));
    }

    expect(mlNotes($treasurer))->toBeEmpty();
    Event::assertDispatchedTimes(NotificationCreated::class, 2);

    $this->withSession(mlSession($members->first()))->get(route('member.activity', ['tab' => 'fees']))
        ->assertSee('Org Shirt')->assertSee('250.00');
});

// ── A payment's status ──────────────────────────────────────────────────────

it('tells only that member when their payment is recorded or undone', function () {
    [, $treasurer, $members] = mlClub();
    [$payer, $other] = $members->all();
    $fee = mlCreateFee($treasurer);
    ClubNotification::query()->delete();

    $this->withSession(mlSession($treasurer))->post(route('officer.fees.paid', [$fee, $payer]));

    $receipt = FeePayment::where('fee_id', $fee->id)->where('user_id', $payer->id)->value('receipt_number');
    expect(mlNotes($payer)->sole())
        ->title->toBe('Payment confirmed')
        ->body->toContain("Receipt {$receipt} is ready");
    expect(mlNotes($other))->toBeEmpty();

    $this->withSession(mlSession($treasurer))->post(route('officer.fees.unpaid', [$fee, $payer]));

    expect(mlNotes($payer)->last()->title)->toBe('Payment marked unpaid');
    expect(mlNotes($other))->toBeEmpty();
});

// ── Editing a fee ───────────────────────────────────────────────────────────

it('tells the members who still owe a fee when its amount or due date changes, and only then', function () {
    [, $treasurer, $members] = mlClub();
    [$owing, $paid] = $members->all();
    $fee = mlCreateFee($treasurer);
    $this->withSession(mlSession($treasurer))->post(route('officer.fees.paid', [$fee, $paid]));
    ClubNotification::query()->delete();

    $edit = fn (array $changes) => $this->withSession(mlSession($treasurer))->patch(route('officer.fees.update', $fee), array_merge([
        'title' => $fee->title, 'amount' => '250.00', 'due_date' => $fee->due_date->toDateString(), 'academic_period' => $fee->academic_period,
    ], $changes));

    $edit(['title' => 'Org Shirt (Navy)']);
    expect(ClubNotification::count())->toBe(0);

    $edit(['amount' => '300']);
    expect(mlNotes($owing)->sole()->body)->toContain('is now ₱300.00')
        ->and(mlNotes($paid))->toBeEmpty();
});

// ── Postponing a fee ────────────────────────────────────────────────────────

it('postpones a fee to a later date and tells the members who still owe it', function () {
    [$club, $treasurer, $members] = mlClub();
    [$owing, $paid] = $members->all();
    $fee = mlCreateFee($treasurer, ['due_date' => now()->addDays(3)->toDateString()]);
    $this->withSession(mlSession($treasurer))->post(route('officer.fees.paid', [$fee, $paid]));
    ClubNotification::query()->delete();

    $was    = $fee->due_date->copy();
    $newDue = now()->addDays(20);

    $this->withSession(mlSession($treasurer))
        ->post(route('officer.fees.postpone', $fee), ['due_date' => $newDue->toDateString(), 'reason' => 'Supplier delay'])
        ->assertRedirect(route('officer.fees.show', $fee))
        ->assertSessionHas('success');

    $fee->refresh();
    expect($fee->status)->toBe(Fee::POSTPONED)
        ->and($fee->due_date->toDateString())->toBe($newDue->toDateString())
        ->and($fee->previous_due_date->toDateString())->toBe($was->toDateString());

    expect(mlNotes($owing)->sole())
        ->title->toBe("{$club->acronym} postponed a fee")
        ->body->toContain('now due ' . $newDue->format('M j, Y') . ' instead of ' . $was->format('M j, Y'))
        ->body->toContain('Reason: Supplier delay');
    expect(mlNotes($paid))->toBeEmpty();

    $this->withSession(mlSession($owing))->get(route('member.activity', ['tab' => 'fees']))
        ->assertSee('Postponed')->assertSee('Supplier delay');
});

it('refuses to postpone a fee to the same or an earlier date', function () {
    [, $treasurer] = mlClub();
    $fee = mlCreateFee($treasurer);

    $this->withSession(mlSession($treasurer))
        ->post(route('officer.fees.postpone', $fee), ['due_date' => $fee->due_date->toDateString()])
        ->assertSessionHasErrors('due_date');

    expect($fee->fresh()->status)->toBe(Fee::ACTIVE);
});

// ── Cancelling a fee ────────────────────────────────────────────────────────

it('cancels a fee and tells everyone billed, with a refund note for those who paid', function () {
    [$club, $treasurer, $members] = mlClub();
    [$owing, $paid] = $members->all();
    $fee = mlCreateFee($treasurer);
    $this->withSession(mlSession($treasurer))->post(route('officer.fees.paid', [$fee, $paid]));
    ClubNotification::query()->delete();

    $this->withSession(mlSession($treasurer))
        ->post(route('officer.fees.cancel', $fee), ['reason' => 'Event called off'])
        ->assertRedirect(route('officer.fees.show', $fee));

    expect($fee->fresh()->status)->toBe(Fee::CANCELLED);

    expect(mlNotes($owing)->sole())
        ->title->toBe("{$club->acronym} cancelled a fee")
        ->body->toContain('You no longer need to pay it. Reason: Event called off');
    expect(mlNotes($paid)->sole()->body)->toContain('You had already paid ₱250.00; ask your club treasurer about a refund');

    // Nothing is owed any more; the payment made keeps its receipt.
    $this->withSession(mlSession($owing))->get(route('member.activity', ['tab' => 'fees']))
        ->assertOk()->assertDontSee('Total due')->assertSee('No pending fees.')->assertSee('Event called off');

    $payment = FeePayment::where('fee_id', $fee->id)->where('user_id', $paid->id)->first();
    $this->withSession(mlSession($paid))->get(route('member.activity', ['tab' => 'fees']))
        ->assertSee('Ask your club treasurer about a refund')
        ->assertSee(route('member.activity.receipt', $payment), false);
});

it('closes a cancelled fee to edits, payments and further changes', function () {
    [, $treasurer, $members] = mlClub();
    $member = $members->first();
    $fee = mlCreateFee($treasurer);
    $this->withSession(mlSession($treasurer))->post(route('officer.fees.cancel', $fee));
    ClubNotification::query()->delete();

    $as = fn () => $this->withSession(mlSession($treasurer));

    $as()->get(route('officer.fees.edit', $fee))->assertRedirect(route('officer.fees.show', $fee));
    $as()->patch(route('officer.fees.update', $fee), [
        'title' => 'Changed', 'amount' => '1', 'due_date' => now()->addDays(30)->toDateString(), 'academic_period' => 'x',
    ])->assertSessionHas('error');
    $as()->post(route('officer.fees.paid', [$fee, $member]))->assertSessionHas('error');
    $as()->post(route('officer.fees.postpone', $fee), ['due_date' => now()->addDays(30)->toDateString()])->assertSessionHas('error');
    $as()->post(route('officer.fees.cancel', $fee))->assertSessionHas('error');

    expect($fee->fresh())->title->toBe('Org Shirt')->status->toBe(Fee::CANCELLED)
        ->and(FeePayment::where('fee_id', $fee->id)->where('status', 'paid')->exists())->toBeFalse()
        ->and(ClubNotification::count())->toBe(0);

    $as()->get(route('officer.fees.show', $fee))->assertOk()
        ->assertDontSee('Mark Paid')->assertDontSee('Postpone fee')->assertSee('Cancelled on');
});

it('takes a cancelled fee off calendars and out of the narrative report', function () {
    [$club, $treasurer] = mlClub();
    $semester = Semester::create([
        'label' => '1st Semester, A.Y. 2026–2027', 'term' => 'first', 'academic_year' => '2026-2027',
        'start_date' => now()->subMonth()->toDateString(), 'end_date' => now()->addMonths(3)->toDateString(), 'is_current' => true,
    ]);
    $kept      = mlCreateFee($treasurer, ['title' => 'Kept Fee']);
    $cancelled = mlCreateFee($treasurer, ['title' => 'Dropped Fee']);
    $this->withSession(mlSession($treasurer))->post(route('officer.fees.cancel', $cancelled));

    $sync = app(GoogleCalendarSync::class);
    expect($sync->entries(GoogleCalendarEvent::FEE, $kept->id))->not->toBeEmpty()
        ->and($sync->entries(GoogleCalendarEvent::FEE, $cancelled->id))->toBeEmpty();

    expect(array_column(NarrativeReportData::fees($club, $semester), 'title'))->toBe(['Kept Fee']);
});

it('lets only the club\'s own officers postpone or cancel its fees', function () {
    [, $treasurer] = mlClub();
    [, $otherTreasurer] = mlClub();
    $fee = mlCreateFee($treasurer);

    $this->withSession(mlSession($otherTreasurer))->post(route('officer.fees.cancel', $fee))->assertForbidden();
    $this->withSession(mlSession($otherTreasurer))
        ->post(route('officer.fees.postpone', $fee), ['due_date' => now()->addDays(30)->toDateString()])->assertForbidden();

    expect($fee->fresh()->status)->toBe(Fee::ACTIVE);
});

// ── Links follow the site the member is on ──────────────────────────────────
//
// The queue worker writes links with APP_URL; a member may be browsing on another
// address (php artisan serve on localhost:8000, a tunnel). A link carrying the worker's
// address took them to a site where they had no session — the sign-in page, as if they
// had been logged out. So a notification's link is a path, whoever made it.

it('stores and sends a notification link as a path, whatever address made it', function () {
    [$club, , $members] = mlClub();
    $member = $members->first();

    $note = ClubNotification::create([
        'recipient_id' => $member->id, 'sender_type' => 'system', 'club_id' => $club->id,
        'title' => 'LVC added a new fee', 'body' => 'x',
        'action_url' => 'http://clubsyncing.test/member/activity?tab=fees#owed',
    ]);

    expect(\Illuminate\Support\Facades\DB::table('notifications')->where('id', $note->id)->value('action_url'))
        ->toBe('/member/activity?tab=fees#owed');

    // The live push, and the poll a page falls back on without a socket.
    Event::assertDispatched(NotificationCreated::class, fn (NotificationCreated $e) => $e->broadcastWith()['action_url'] === '/member/activity?tab=fees#owed');
    $this->withSession(mlSession($member))->getJson(route('live.counts'))
        ->assertJsonPath('latest.action_url', '/member/activity?tab=fees#owed');
});

it('reads a link saved with another address back as a path', function () {
    [$club, , $members] = mlClub();
    $member = $members->first();

    // As the 28 rows made on http://localhost:8000 were saved, before links became paths.
    $id = \Illuminate\Support\Facades\DB::table('notifications')->insertGetId([
        'recipient_id' => $member->id, 'sender_type' => 'system', 'club_id' => $club->id,
        'title' => 'Old link', 'body' => 'x', 'is_read' => false,
        'action_url' => 'http://localhost:8000/member/clubs/' . $club->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(ClubNotification::find($id)->action_url)->toBe('/member/clubs/' . $club->id);

    // The notifications list links by path, and opening one stays on this site.
    $this->withSession(mlSession($member))->get(route('notifications.index'))
        ->assertSee('href="/member/clubs/' . $club->id . '"', false)
        ->assertDontSee('localhost:8000');

    $this->withSession(mlSession($member))->post(route('notifications.read', $id))
        ->assertRedirect(url('/member/clubs/' . $club->id));
});

it('gives the worker-made fee and activity notifications path links', function () {
    [$club, $treasurer, $members] = mlClub();

    config(['app.url' => 'http://some-other-host.test']);
    \Illuminate\Support\Facades\URL::forceRootUrl('http://some-other-host.test');

    mlCreateFee($treasurer);
    (new \App\Jobs\NotifyMembersOfActivity(mlActivity($club, $treasurer)->id))->handle();

    $links = ClubNotification::where('recipient_id', $members->first()->id)->get()->pluck('action_url');
    expect($links->all())->toBe(['/member/activity?tab=fees', '/member/clubs/' . $club->id]);
});
