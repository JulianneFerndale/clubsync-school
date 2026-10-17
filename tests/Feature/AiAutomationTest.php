<?php

use App\Jobs\DetectClubViolationsJob;
use App\Jobs\NotifyMembersOfAnnouncement;
use App\Models\AiNotificationQueue;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubActivity;
use App\Models\ClubMember;
use App\Models\ClubNotification;
use App\Models\ClubOfficer;
use App\Models\ClubOfficerRecord;
use App\Models\MemberSemesterStatus;
use App\Models\Semester;
use App\Models\User;
use App\Services\AnnouncementTemplates;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Maatwebsite\Excel\Facades\Excel;

uses(RefreshDatabase::class);

/*
 * A.3 AI & Automation Cluster — see docs/test.docx and TASK/tests-logs.md.
 *
 * Procedure 1 — "Tests the system if the Gemini API successfully generates an
 * announcement draft based on the Officer's input prompt."
 *
 * The officer's prompt is the title and/or the free-text description typed into the
 * "Generate with AI" panel on the Draft Announcement page. These tests fake the Gemini
 * transport so the suite stays offline and deterministic; the live end-to-end run
 * against the real API (Playwright, clubsyncing.test) is logged in TASK/tests-logs.md.
 */

// ── Fixtures ─────────────────────────────────────────────────────────────────

function aiUser(string $role): User
{
    static $n = 0;
    $n++;

    return User::create([
        'first_name'   => 'Ai',
        'last_name'    => "Tester{$n}",
        'edp_number'   => str_pad((string) (700 + $n), 10, '0', STR_PAD_LEFT),
        'email'        => "ai{$n}@sccpag.edu.ph",
        'password'     => Hash::make('Password1!'),
        'role'         => $role,
        'course_id'    => testCourse()->id,
        'is_admin'     => false,
        'is_suspended' => false,
    ]);
}

function aiClub(string $name = 'Building the Youth through Technology Education'): Club
{
    static $n = 0;
    $n++;

    return Club::create([
        'slug'            => 'ai-club-' . $n,
        'acronym'         => 'AIC' . $n,
        'club_type'       => 'Academic',
        'type'            => 'academic',
        'course_slug'     => 'all',
        'department_slug' => 'all',
        'name'            => $name,
        'is_active'       => true,
    ]);
}

/** An officer (president) of a fresh club, plus the club itself. */
function aiOfficer(?Club $club = null): array
{
    $club ??= aiClub();
    $user = aiUser('president');

    ClubOfficer::create([
        'club_id'  => $club->id,
        'user_id'  => $user->id,
        'position' => 'president',
    ]);

    return [$user, $club];
}

function aiSession(User $user): array
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

/** Fake a successful Gemini generateContent response. */
function aiFakeGemini(string $text = "  Members, our general assembly is set.\n\nPlease attend.  "): void
{
    config(['gemini.api_key' => 'test-key']);

    Http::fake(['*generativelanguage*' => Http::response([
        'candidates' => [[
            'content' => ['parts' => [['text' => $text]]],
        ]],
    ])]);
}

/** What the officer types into the AI panel. */
function aiInput(array $overrides = []): array
{
    return array_merge([
        'title'   => 'BYTE Org 1st Semester General Assembly',
        'type'    => 'announcement',
        'context' => 'Remind all BSIT members about the general assembly on September 20, 1:00 PM at the CCS AVR. '
            . 'Attendance is required and we will elect committee heads.',
    ], $overrides);
}

/** The prompt text actually sent to Gemini. */
function aiSentPrompt(): string
{
    $prompt = '';

    Http::assertSent(function ($request) use (&$prompt) {
        $prompt = (string) data_get($request->data(), 'contents.0.parts.0.text', '');

        return true;
    });

    return $prompt;
}

// ── Procedure 1 — Gemini drafts an announcement from the officer's prompt ────

it("P1: generates an announcement draft from the officer's title and description", function () {
    aiFakeGemini();
    [$officer] = aiOfficer();

    $response = $this->withSession(aiSession($officer))
        ->postJson(route('officer.announcements.ai-draft'), aiInput());

    $response->assertOk();

    // The draft comes back to the officer, whitespace-trimmed and ready to edit.
    expect($response->json('content'))->toBe("Members, our general assembly is set.\n\nPlease attend.")
        ->and($response->json('error'))->toBeNull();
});

it("P1: builds the prompt from the officer's own input and the club it belongs to", function () {
    aiFakeGemini();
    [$officer, $club] = aiOfficer();

    $this->withSession(aiSession($officer))
        ->postJson(route('officer.announcements.ai-draft'), aiInput())
        ->assertOk();

    $prompt = aiSentPrompt();

    expect($prompt)->toContain($club->name)
        ->and($prompt)->toContain('BYTE Org 1st Semester General Assembly')
        ->and($prompt)->toContain('elect committee heads')
        ->and($prompt)->toContain('CCS AVR');
});

it('P1: generates from a description alone when the officer gives no title', function () {
    // The panel invites a title OR a description ("Describe what this is about"),
    // so a description-only prompt must be accepted, not rejected.
    aiFakeGemini();
    [$officer] = aiOfficer();

    $response = $this->withSession(aiSession($officer))
        ->postJson(route('officer.announcements.ai-draft'), aiInput(['title' => '']));

    $response->assertOk();

    expect($response->json('content'))->not->toBeEmpty();
    expect(aiSentPrompt())->toContain('CCS AVR');
});

it('P1: generates from a title alone when the officer gives no description', function () {
    aiFakeGemini();
    [$officer] = aiOfficer();

    $response = $this->withSession(aiSession($officer))
        ->postJson(route('officer.announcements.ai-draft'), aiInput(['context' => '']));

    $response->assertOk();

    expect(aiSentPrompt())->toContain('BYTE Org 1st Semester General Assembly');
});

it('P1: answers an empty prompt with a readable JSON error, not a redirect', function () {
    // The page calls this endpoint with fetch() and no Accept header — a thrown
    // ValidationException would 302 to HTML and surface as a bogus "Network error".
    aiFakeGemini();
    [$officer] = aiOfficer();

    $response = $this->withSession(aiSession($officer))
        ->postJson(route('officer.announcements.ai-draft'), aiInput(['title' => '', 'context' => '']));

    $response->assertStatus(422);

    expect($response->json('error'))->toBe('Enter a title or describe the topic before generating.');

    Http::assertNothingSent();
});

it('P1: drafts a formal letter when the officer picks the letter type', function () {
    aiFakeGemini();
    [$officer] = aiOfficer();

    $this->withSession(aiSession($officer))
        ->postJson(route('officer.announcements.ai-draft'), aiInput([
            'type'  => 'letter',
            'title' => 'Request for Venue Use — CCS AVR',
        ]))
        ->assertOk();

    expect(aiSentPrompt())->toContain('formal letter');
});

it('P1: tells the model not to invent details the officer did not give', function () {
    aiFakeGemini();
    [$officer] = aiOfficer();

    $this->withSession(aiSession($officer))
        ->postJson(route('officer.announcements.ai-draft'), aiInput())
        ->assertOk();

    expect(aiSentPrompt())->toContain('Do NOT invent');
});

it('P1: rejects an over-long description instead of sending it', function () {
    aiFakeGemini();
    [$officer] = aiOfficer();

    $this->withSession(aiSession($officer))
        ->postJson(route('officer.announcements.ai-draft'), aiInput(['context' => str_repeat('a', 501)]))
        ->assertStatus(422);

    Http::assertNothingSent();
});

// ── POLICY.md §AI & Automation — generation is a draft, never a dispatch ─────

it('P1: returns the draft to the officer without persisting or publishing anything', function () {
    aiFakeGemini();
    [$officer] = aiOfficer();

    $this->withSession(aiSession($officer))
        ->postJson(route('officer.announcements.ai-draft'), aiInput())
        ->assertOk();

    expect(Announcement::count())->toBe(0);
});

it('P1: publishes an accepted draft flagged AI-assisted, and only when the officer posts it', function () {
    aiFakeGemini();
    [$officer, $club] = aiOfficer();

    $content = $this->withSession(aiSession($officer))
        ->postJson(route('officer.announcements.ai-draft'), aiInput())
        ->json('content');

    // Nothing exists yet: generating a draft publishes nothing on its own.
    expect(Announcement::count())->toBe(0);

    // The officer reads it, then presses Publish — that press is the human gate
    // POLICY.md §AI & Automation requires, now that no adviser stands after it.
    $this->withSession(aiSession($officer))
        ->post(route('officer.announcements.store'), [
            'title'       => 'BYTE Org 1st Semester General Assembly',
            'content'     => $content,
            'type'        => 'announcement',
            'ai_assisted' => 1,
        ])->assertRedirect();

    $announcement = Announcement::first();

    expect($announcement->club_id)->toBe($club->id)
        ->and($announcement->author_id)->toBe($officer->id)
        ->and($announcement->content)->toBe($content)
        ->and($announcement->ai_assisted)->toBeTrue()
        ->and($announcement->status)->toBe('published')
        ->and($announcement->published_at)->not->toBeNull();
});

// ── POLICY.md §Data Privacy — AI generation events are audited ───────────────

it('P1: records the generation in the tamper-evident audit log', function () {
    aiFakeGemini();
    [$officer, $club] = aiOfficer();

    $this->withSession(aiSession($officer))
        ->postJson(route('officer.announcements.ai-draft'), aiInput())
        ->assertOk();

    $entry = AuditLog::where('action', 'ai.announcement.drafted')->first();

    expect($entry)->not->toBeNull()
        ->and($entry->actor_id)->toBe($officer->id)
        ->and($entry->resource_type)->toBe(Club::class)
        ->and($entry->resource_id)->toBe($club->id)
        ->and($entry->created_at)->not->toBeNull()
        ->and($entry->hash)->not->toBeEmpty()
        ->and($entry->metadata['type'])->toBe('announcement')
        ->and($entry->metadata['used_context'])->toBeTrue();
});

it('P1: audits a failed generation too', function () {
    config(['gemini.api_key' => 'test-key']);
    Http::fake(['*generativelanguage*' => Http::response('upstream error', 500)]);

    [$officer] = aiOfficer();

    $this->withSession(aiSession($officer))
        ->postJson(route('officer.announcements.ai-draft'), aiInput())
        ->assertStatus(500);

    expect(AuditLog::where('action', 'ai.announcement.draft_failed')->count())->toBe(1)
        ->and(AuditLog::where('action', 'ai.announcement.drafted')->count())->toBe(0);
});

// ── POLICY.md §AI & Automation — graceful degradation ────────────────────────

it('P1: reports a clean error when the Gemini call fails, and saves nothing', function () {
    config(['gemini.api_key' => 'test-key']);
    Http::fake(['*generativelanguage*' => Http::response('upstream error', 500)]);

    [$officer] = aiOfficer();

    $response = $this->withSession(aiSession($officer))
        ->postJson(route('officer.announcements.ai-draft'), aiInput());

    $response->assertStatus(500);

    expect($response->json('error'))->toBe('The AI could not draft this. Try again, or start from a template below.')
        ->and(Announcement::count())->toBe(0);
});

it('P1: refuses to call Gemini at all when no API key is configured', function () {
    config(['gemini.api_key' => '']);
    Http::fake();

    [$officer] = aiOfficer();

    $response = $this->withSession(aiSession($officer))
        ->postJson(route('officer.announcements.ai-draft'), aiInput());

    $response->assertStatus(503);

    expect($response->json('error'))->toBe('AI is not configured. Start from a template below, or write it yourself.');

    Http::assertNothingSent();
});

it('P1: keeps the manual draft path fully working when AI is unavailable', function () {
    config(['gemini.api_key' => '']);
    Http::fake();

    [$officer, $club] = aiOfficer();

    // The AI panel is not offered…
    $this->withSession(aiSession($officer))
        ->get(route('officer.announcements.create'))
        ->assertOk()
        ->assertDontSee('Generate with AI');

    // …and the officer can still write and save the announcement by hand.
    $this->withSession(aiSession($officer))
        ->post(route('officer.announcements.store'), [
            'title'   => 'Manually Written Assembly Notice',
            'content' => 'Written by the officer without AI assistance.',
            'type'    => 'announcement',
        ])->assertRedirect();

    $announcement = Announcement::first();

    expect($announcement->club_id)->toBe($club->id)
        ->and($announcement->ai_assisted)->toBeFalse()
        ->and($announcement->status)->toBe('published');
});

it('P1: offers the AI panel when the key is configured', function () {
    aiFakeGemini();
    [$officer] = aiOfficer();

    $this->withSession(aiSession($officer))
        ->get(route('officer.announcements.create'))
        ->assertOk()
        ->assertSee('Generate with AI');
});

// ── Access control ──────────────────────────────────────────────────────────

it('P1: is not reachable by a member or an adviser', function (string $role) {
    aiFakeGemini();
    $user = aiUser($role);

    $this->withSession(aiSession($user))
        ->postJson(route('officer.announcements.ai-draft'), aiInput())
        ->assertForbidden();

    Http::assertNothingSent();
})->with(['member', 'adviser']);

it('P1: refuses an officer who is not assigned to any club', function () {
    aiFakeGemini();
    $officer = aiUser('president'); // no club_officers row

    $this->withSession(aiSession($officer))
        ->postJson(route('officer.announcements.ai-draft'), aiInput())
        ->assertForbidden();

    Http::assertNothingSent();
});

// ─────────────────────────────────────────────────────────────────────────────
// Procedure 2 — an officer publishes their own club's announcement.
//
// Rewritten when adviser review moved off announcements and onto activities
// (POLICY.md §Announcements & Content Workflow). The spec procedure this replaces
// tested routing into the adviser's queue; there is no queue in front of an
// announcement any more, so what has to hold instead is that an officer reaches
// only their own club, and that members hear about it once it is posted.
// ─────────────────────────────────────────────────────────────────────────────

/** A club with an adviser and a president, plus both users. */
function aiClubWithAdviser(): array
{
    $club    = aiClub();
    $adviser = aiUser('adviser');
    $club->update(['adviser_id' => $adviser->id]);

    [$officer] = aiOfficer($club);

    return [$adviser, $club, $officer];
}

/** An AI-assisted draft sitting in the officer's hands. */
function aiAnnouncement(Club $club, User $officer, array $overrides = []): Announcement
{
    return Announcement::create(array_merge([
        'club_id'     => $club->id,
        'author_id'   => $officer->id,
        'title'       => 'General Assembly Reminder',
        'content'     => 'Members, please attend the general assembly on September 20 at the CCS AVR.',
        'type'        => 'announcement',
        'status'      => 'draft',
        'ai_assisted' => true,
    ], $overrides));
}

/** An active member of a club, returned as [user, clubMember]. */
function aiMember(Club $club, string $status = 'active'): array
{
    $user = aiUser('member');

    $member = ClubMember::create([
        'club_id'             => $club->id,
        'user_id'             => $user->id,
        'role'                => 'member',
        'status'              => $status,
        'registration_status' => 'approved',
        'date_joined'         => now()->subMonths(2),
        'joined_at'           => now()->subMonths(2),
    ]);

    return [$user, $member];
}

it('P2: publishes an officer announcement straight to their own club', function () {
    [, $club, $officer] = aiClubWithAdviser();

    $this->withSession(aiSession($officer))
        ->post(route('officer.announcements.store'), [
            'title'   => 'General Assembly Reminder',
            'content' => 'Members, please attend the general assembly on September 20 at the CCS AVR.',
            'type'    => 'announcement',
        ])->assertRedirect();

    $announcement = Announcement::first();

    expect($announcement->club_id)->toBe($club->id)
        ->and($announcement->status)->toBe('published')
        ->and($announcement->published_at)->not->toBeNull();
});

it('P2: tells the club members it was published', function () {
    Queue::fake();

    [, , $officer] = aiClubWithAdviser();

    $this->withSession(aiSession($officer))
        ->post(route('officer.announcements.store'), [
            'title'   => 'General Assembly Reminder',
            'content' => 'Members, please attend the general assembly on September 20.',
            'type'    => 'announcement',
        ])->assertRedirect();

    Queue::assertPushed(NotifyMembersOfAnnouncement::class);
});

it('P2: shows it on the bulletin, attributed to the club that posted it', function () {
    // The bulletin is campus-wide by design — every club's published announcements
    // and narratives appear on it — so what matters is that it carries the name of
    // the club it came from. Who may post INTO a club is the scoped part, covered
    // by the publish tests below.
    [, $club, $officer] = aiClubWithAdviser();
    $announcement = aiAnnouncement($club, $officer, ['status' => 'published', 'published_at' => now()]);

    [$memberUser] = aiMember($club);

    $this->withSession(aiSession($memberUser))
        ->get(route('member.bulletin.index'))
        ->assertOk()
        ->assertSee('General Assembly Reminder')
        ->assertSee($club->name, false);
});

it('P2: does not route the announcement to the adviser any more', function () {
    // The adviser reviews activities now. An announcement must not raise a review
    // notification for them, and the queue it used to land in is gone.
    [$adviser, , $officer] = aiClubWithAdviser();

    $this->withSession(aiSession($officer))
        ->post(route('officer.announcements.store'), [
            'title'   => 'General Assembly Reminder',
            'content' => 'Members, please attend the general assembly on September 20.',
            'type'    => 'announcement',
        ])->assertRedirect();

    expect(ClubNotification::where('recipient_id', $adviser->id)->count())->toBe(0);

    $adviserAnnouncementRoutes = collect(app('router')->getRoutes()->getRoutes())
        ->map(fn ($r) => (string) $r->getName())
        ->filter(fn ($n) => str_starts_with($n, 'adviser.') && str_contains($n, 'announcement'));

    expect($adviserAnnouncementRoutes)->toBeEmpty();
});

it('P2: refuses to publish into a club the officer does not belong to', function () {
    [, $club, $officer] = aiClubWithAdviser();
    $announcement = aiAnnouncement($club, $officer);

    [$intruder] = aiOfficer();

    $this->withSession(aiSession($intruder))
        ->post(route('officer.announcements.publish', $announcement))
        ->assertForbidden();

    expect($announcement->fresh()->status)->toBe('draft');
});

it('P2: lets the author correct an announcement after posting it', function () {
    [, $club, $officer] = aiClubWithAdviser();
    $announcement = aiAnnouncement($club, $officer, ['status' => 'published', 'published_at' => now()]);

    $this->withSession(aiSession($officer))
        ->patch(route('officer.announcements.update', $announcement), [
            'title'   => 'General Assembly Reminder',
            'content' => 'Members, the general assembly is on September 20 at CCS AVR, Room 301.',
            'type'    => 'announcement',
        ])->assertRedirect();

    expect($announcement->fresh()->content)->toContain('Room 301');
});

it('P2: a correction does not re-notify the members', function () {
    Queue::fake();

    [, $club, $officer] = aiClubWithAdviser();
    $announcement = aiAnnouncement($club, $officer, ['status' => 'published', 'published_at' => now()]);

    $this->withSession(aiSession($officer))
        ->patch(route('officer.announcements.update', $announcement), [
            'title'   => 'General Assembly Reminder',
            'content' => 'Corrected wording.',
            'type'    => 'announcement',
        ])->assertRedirect();

    Queue::assertNotPushed(NotifyMembersOfAnnouncement::class);
});

// ─────────────────────────────────────────────────────────────────────────────
// Procedure 3 — announcements left behind by the retired review queue.
//
// The spec procedure this replaces tested the adviser's approve/reject decision.
// With the gate removed, the thing that still has to work is the rows that were
// mid-review when it went: they are the officer's own, and the officer can post
// them — but nothing posts them on its own.
// ─────────────────────────────────────────────────────────────────────────────

it('P3: leaves an announcement stranded by the retired queue unpublished', function () {
    [, $club, $officer] = aiClubWithAdviser();
    $announcement = aiAnnouncement($club, $officer, ['status' => 'pending_review']);

    [$memberUser] = aiMember($club);

    // Nothing publishes it retroactively — members must not suddenly receive
    // something an officer never finished.
    $this->withSession(aiSession($memberUser))
        ->get(route('member.bulletin.index'))
        ->assertOk()
        ->assertDontSee('General Assembly Reminder');

    $this->withSession(aiSession($memberUser))
        ->get(route('member.bulletin.show', $announcement))
        ->assertNotFound();
});

it('P3: lets the author publish one that was stuck in the old queue', function (string $status) {
    Queue::fake();

    [, $club, $officer] = aiClubWithAdviser();
    $announcement = aiAnnouncement($club, $officer, ['status' => $status]);

    $this->withSession(aiSession($officer))
        ->post(route('officer.announcements.publish', $announcement))
        ->assertRedirect();

    $fresh = $announcement->fresh();

    expect($fresh->status)->toBe('published')
        ->and($fresh->published_at)->not->toBeNull();

    Queue::assertPushed(NotifyMembersOfAnnouncement::class);
})->with(['draft', 'pending_review', 'revision_required']);

it('P3: will not publish the same announcement twice', function () {
    Queue::fake();

    [, $club, $officer] = aiClubWithAdviser();
    $announcement = aiAnnouncement($club, $officer, ['status' => 'published', 'published_at' => now()]);

    $this->withSession(aiSession($officer))
        ->post(route('officer.announcements.publish', $announcement))
        ->assertSessionHasErrors('status');

    Queue::assertNotPushed(NotifyMembersOfAnnouncement::class);
});

it('P3: shows a published announcement on the member bulletin', function () {
    [, $club, $officer] = aiClubWithAdviser();
    $announcement = aiAnnouncement($club, $officer, ['status' => 'published', 'published_at' => now()]);

    [$memberUser] = aiMember($club);

    $this->withSession(aiSession($memberUser))
        ->get(route('member.bulletin.show', $announcement))
        ->assertOk()
        ->assertSee('General Assembly Reminder');
});

// ─────────────────────────────────────────────────────────────────────────────
// Procedure 4 — "Tests the system if the nightly compliance scan correctly flags
// clubs that fail to meet activity or documentation requirements."
// ─────────────────────────────────────────────────────────────────────────────

/** Gemini switched off: the scan must work without it, and no test may call the real API. */
function aiScanOffline(): void
{
    config(['gemini.api_key' => '']);
    Http::fake();
}

function aiSemester(int $startedDaysAgo = 20): Semester
{
    return Semester::create([
        'label'      => '1st Semester 2026-2027',
        'start_date' => now()->subDays($startedDaysAgo)->toDateString(),
        'end_date'   => now()->addDays(120)->toDateString(),
        'is_current' => true,
    ]);
}

function aiActivity(Club $club, User $creator, array $overrides = []): ClubActivity
{
    return ClubActivity::create(array_merge([
        'club_id'               => $club->id,
        'title'                 => 'Community Outreach Day',
        'description'           => 'Outreach activity in the barangay.',
        'date'                  => now()->subDays(10)->toDateString(),
        'time_start'            => '08:00',
        'time_end'              => '12:00',
        'venue'                 => 'YDC',
        'purpose'               => 'Community service.',
        'expected_participants' => 40,
        'activity_type'         => 'community_involvement',
        'approval_status'       => 'no_approval_needed',
        'status'                => 'scheduled',
        'created_by'            => $creator->id,
    ], $overrides));
}

/** Run the nightly scan. */
function aiRunScan(): void
{
    (new DetectClubViolationsJob)->handle();
}

/** Open violations raised against a club. */
function aiViolations(Club $club)
{
    return AiNotificationQueue::where('club_id', $club->id)->get();
}

it('P4: runs the compliance scan nightly on a schedule', function () {
    $events = collect(app(Schedule::class)->events());

    $scan = $events->first(fn ($e) => str_contains($e->getSummaryForDisplay(), 'DetectClubViolationsJob'));

    expect($scan)->not->toBeNull()
        ->and($scan->expression)->toBe('0 8 * * *');
});

it('P4: flags a club that has filed no semestral presence update', function () {
    aiScanOffline();
    aiSemester(startedDaysAgo: 20); // past the 14-day grace period

    [, $club] = aiOfficer();
    aiMember($club);

    aiRunScan();

    $violation = aiViolations($club)->firstWhere('gap_type', 'overdue_presence_update');

    expect($violation)->not->toBeNull()
        ->and($violation->description)->toContain($club->name)
        ->and($violation->description)->toContain('1st Semester 2026-2027')
        ->and($violation->status)->toBe('pending_review')
        ->and($violation->is_resolved)->toBeFalse();
});

it('P4: leaves the presence update alone inside the 14-day grace period', function () {
    aiScanOffline();
    aiSemester(startedDaysAgo: 5);

    [, $club] = aiOfficer();
    aiMember($club);

    aiRunScan();

    expect(aiViolations($club))->toBeEmpty();
});

it('P4: does not flag a club that has already filed its presence update', function () {
    aiScanOffline();
    $semester = aiSemester(startedDaysAgo: 20);

    [$officer, $club] = aiOfficer();
    [, $member] = aiMember($club);

    MemberSemesterStatus::create([
        'club_member_id'  => $member->id,
        'semester_id'     => $semester->id,
        'semester_status' => 'active',
        'updated_by'      => $officer->id,
    ]);

    aiRunScan();

    expect(aiViolations($club)->firstWhere('gap_type', 'overdue_presence_update'))->toBeNull();
});

it('P4: flags an activity approval request left pending for more than 5 days', function () {
    aiScanOffline();

    [$officer, $club] = aiOfficer();
    $activity = aiActivity($club, $officer, [
        'title'           => 'Leadership Seminar',
        'approval_status' => 'pending_approval',
        'date'            => now()->addDays(20)->toDateString(),
    ]);
    $activity->created_at = now()->subDays(6);
    $activity->save();

    aiRunScan();

    $violation = aiViolations($club)->firstWhere('gap_type', 'overdue_activity_request');

    expect($violation)->not->toBeNull()
        ->and($violation->description)->toContain('Leadership Seminar')
        ->and($violation->description)->toContain('5 days');
});

it('P4: leaves a freshly submitted activity request alone', function () {
    aiScanOffline();

    [$officer, $club] = aiOfficer();
    aiActivity($club, $officer, [
        'approval_status' => 'pending_approval',
        'date'            => now()->addDays(20)->toDateString(),
    ]);

    aiRunScan();

    expect(aiViolations($club)->firstWhere('gap_type', 'overdue_activity_request'))->toBeNull();
});

it('P4: flags officer records that have not been updated since October 1', function () {
    aiScanOffline();

    [, $club] = aiOfficer();

    // Last touched in June, well before the October 1 cut-off.
    $this->travelTo(Carbon::create(2026, 6, 1));

    ClubOfficerRecord::create([
        'club_id'           => $club->id,
        'full_name'         => 'Paulo Delgado',
        'position'          => 'president',
        'student_id_number' => '400016',
        'contact_email'     => 'delgado.president@sccpag.edu.ph',
        'academic_year'     => '2025-2026',
        'semester'          => '2nd',
        'is_active'         => true,
    ]);

    $this->travelTo(Carbon::create(2026, 11, 15));

    aiRunScan();

    $violation = aiViolations($club)->firstWhere('gap_type', 'unupdated_officer_info');

    expect($violation)->not->toBeNull()
        ->and($violation->description)->toContain('October 1, 2026');

    $this->travelBack();
});

it('P4: does not flag officer records updated in the current cycle', function () {
    aiScanOffline();
    $this->travelTo(Carbon::create(2026, 11, 15));

    [, $club] = aiOfficer();

    ClubOfficerRecord::create([
        'club_id'           => $club->id,
        'full_name'         => 'Paulo Delgado',
        'position'          => 'president',
        'student_id_number' => '400016',
        'contact_email'     => 'delgado.president@sccpag.edu.ph',
        'academic_year'     => '2026-2027',
        'semester'          => '1st',
        'is_active'         => true,
    ]);

    aiRunScan();

    expect(aiViolations($club)->firstWhere('gap_type', 'unupdated_officer_info'))->toBeNull();

    $this->travelBack();
});

it('P4: leaves a fully compliant club unflagged', function () {
    aiScanOffline();
    $semester = aiSemester(startedDaysAgo: 20);

    [$officer, $club] = aiOfficer();
    [, $member] = aiMember($club);

    MemberSemesterStatus::create([
        'club_member_id'  => $member->id,
        'semester_id'     => $semester->id,
        'semester_status' => 'active',
        'updated_by'      => $officer->id,
    ]);

    aiActivity($club, $officer, [
        'status'       => 'completed',
        'completed_at' => now()->subDays(9),
    ]);

    aiRunScan();

    expect(aiViolations($club))->toBeEmpty();
});

it('P4: does not raise the same violation twice while one is still open', function () {
    aiScanOffline();
    aiSemester(startedDaysAgo: 20);

    [, $club] = aiOfficer();
    aiMember($club);

    aiRunScan();
    aiRunScan();
    aiRunScan();

    expect(aiViolations($club)->where('gap_type', 'overdue_presence_update'))->toHaveCount(1);
});

it('P4: raises it again on a later night once the earlier one is resolved', function () {
    aiScanOffline();
    aiSemester(startedDaysAgo: 20);

    [, $club] = aiOfficer();
    aiMember($club);

    aiRunScan();

    AiNotificationQueue::where('club_id', $club->id)->update([
        'is_resolved' => true,
        'resolved_at' => now(),
    ]);

    aiRunScan();

    expect(aiViolations($club)->where('gap_type', 'overdue_presence_update'))->toHaveCount(2);
});

it('P4: drafts the DSA statement with AI but never sends it automatically', function () {
    // POLICY.md §AI & Automation — AI output waits in a human review queue.
    aiFakeGemini('Please submit the semestral presence update within 5 working days.');
    aiSemester(startedDaysAgo: 20);

    [, $club] = aiOfficer();
    aiMember($club);

    aiRunScan();

    $violation = aiViolations($club)->first();

    expect($violation->draft_content)->toContain('5 working days')
        ->and($violation->ai_available)->toBeTrue()
        ->and($violation->status)->toBe('pending_review')
        ->and($violation->sent_at)->toBeNull();

    expect(AuditLog::where('action', 'ai.violation_statement.generated')->count())->toBe(1);
});

it('P4: still flags the club when Gemini is unavailable', function () {
    // POLICY.md §AI & Automation — manual alternatives stay fully functional.
    aiScanOffline();
    aiSemester(startedDaysAgo: 20);

    [, $club] = aiOfficer();
    aiMember($club);

    aiRunScan();

    $violation = aiViolations($club)->first();

    expect($violation)->not->toBeNull()
        ->and($violation->draft_content)->toBeNull()
        ->and($violation->ai_available)->toBeFalse();

    Http::assertNothingSent();
});

it('P4: names the exact violation in the DSA notification, not a raw slug', function () {
    aiScanOffline();

    $dsa = aiUser('dsa');
    [$officer, $club] = aiOfficer();

    $activity = aiActivity($club, $officer, [
        'title'           => 'Brigada Eskwela Outreach',
        'approval_status' => 'pending_approval',
        'date'            => now()->addDays(20)->toDateString(),
    ]);
    $activity->created_at = now()->subDays(6);
    $activity->save();

    aiRunScan();

    $notification = ClubNotification::where('recipient_id', $dsa->id)->first();

    expect($notification)->not->toBeNull()
        ->and($notification->title)->toContain('Overdue activity approval request')
        ->and($notification->body)->toContain($club->name)
        ->and($notification->body)->toContain('Brigada Eskwela Outreach')
        // The raw database slug must never reach a human-facing message.
        ->and($notification->title . $notification->body)->not->toContain('overdue_activity_request');
});

// ── Announcements when the AI is down: templates always work ─────────────────

it('Fallback: offers announcement templates with no AI configured at all', function () {
    config(['gemini.api_key' => '']);
    [$officer, $club] = aiOfficer();

    $this->withSession(aiSession($officer))->get(route('officer.announcements.create'))
        ->assertOk()
        ->assertDontSee('Generate with AI')
        ->assertSee('Start from a template')
        ->assertSee('Meeting or general assembly')
        ->assertSee('Request to use a venue or equipment')
        ->assertSee($club->name);
});

it('Fallback: the edit page offers the same templates', function () {
    config(['gemini.api_key' => '']);
    [$officer, $club] = aiOfficer();
    $announcement = aiAnnouncement($club, $officer);

    $this->withSession(aiSession($officer))->get(route('officer.announcements.edit', $announcement))
        ->assertOk()->assertSee('Start from a template')->assertSee('Fee reminder');
});

it('Fallback: during a Gemini outage the AI panel says so, and the templates stay', function () {
    Sleep::fake();
    config(['gemini.api_key' => 'test-key']);
    Http::fake(['*generativelanguage*' => Http::response('unavailable', 503)]);
    [$officer] = aiOfficer();

    $first = $this->withSession(aiSession($officer))->postJson(route('officer.announcements.ai-draft'), aiInput());
    $first->assertStatus(503);
    expect($first->json('error'))->toBe('The AI is unavailable right now. Start from a template below, or write it yourself.');

    // Remembered: the next request does not wait on Gemini again.
    $this->withSession(aiSession($officer))->postJson(route('officer.announcements.ai-draft'), aiInput())->assertStatus(503);
    expect(Http::recorded())->toHaveCount(3);

    $this->withSession(aiSession($officer))->get(route('officer.announcements.create'))
        ->assertOk()
        ->assertSee('The AI is unavailable right now')
        ->assertDontSee('id="ai-btn"', false)
        ->assertSee('Start from a template');
});

it('Fallback: the AI draft endpoint is rate limited and answers JSON when it is', function () {
    aiFakeGemini();
    [$officer] = aiOfficer();

    for ($i = 0; $i < 20; $i++) {
        $this->withSession(aiSession($officer))->postJson(route('officer.announcements.ai-draft'), aiInput())->assertOk();
    }

    $this->withSession(aiSession($officer))->postJson(route('officer.announcements.ai-draft'), aiInput())
        ->assertStatus(429)->assertHeader('Content-Type', 'application/json');
});

it('Fallback: templates invent nothing — every detail the club must supply is a bracket', function () {
    foreach (AnnouncementTemplates::all('BYTE Org') as $key => $template) {
        expect($template['type'])->toBeIn(['announcement', 'letter'])
            ->and($template['content'])->toMatch('/\[[^\]]+\]/')
            // No date, time, amount or count is ever filled in for the officer: outside the
            // brackets there is not a single digit.
            ->and(preg_match('/\d/', preg_replace('/\[[^\]]*\]/', '', $template['content'])))->toBe(0, "{$key} contains a figure");
    }

    expect(AnnouncementTemplates::all('')['invitation']['content'])->toStartWith('[club name] invites you');
});

