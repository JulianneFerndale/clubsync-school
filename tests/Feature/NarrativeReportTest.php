<?php

use App\Jobs\DispatchSemesterNarrativeReportsJob;
use App\Jobs\GenerateClubNarrativeJob;
use App\Jobs\GenerateSemesterNarrativeReportJob;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubActivity;
use App\Models\ClubMember;
use App\Models\ClubNarrative;
use App\Models\ClubNotification;
use App\Models\ClubOfficer;
use App\Models\Semester;
use App\Models\SemesterNarrativeReport;
use App\Models\User;
use App\Services\GeminiService;
use App\Services\NarrativeReportData;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * A.4 AI Narrative Report Cluster — see docs/test.docx and TASK/tests-logs.md.
 *
 * The requirement: at the end of the semester, the AI drafts one narrative report per
 * club summarising every activity it conducted, with each activity's details and its
 * attendance, and the report is handed to the DSA as a document after the adviser has
 * reviewed it. Gemini is faked throughout; the live run is logged in tests-logs.md.
 */

// ── Fixtures ─────────────────────────────────────────────────────────────────

function nrUser(string $role): User
{
    static $n = 0;
    $n++;

    return User::create([
        'first_name'   => 'Nr',
        'last_name'    => "Tester{$n}",
        'edp_number'   => str_pad((string) (900 + $n), 10, '0', STR_PAD_LEFT),
        'email'        => "nr{$n}@sccpag.edu.ph",
        'password'     => Hash::make('Password1!'),
        'role'         => $role,
        'course_id'    => testCourse()->id,
        'is_admin'     => false,
        'is_suspended' => false,
    ]);
}

/** A club with an adviser and a president: [club, adviser, officer]. */
function nrClub(string $name = 'Building the Youth through Technology Education'): array
{
    static $n = 0;
    $n++;

    $adviser = nrUser('adviser');

    $club = Club::create([
        'slug'            => 'nr-club-' . $n,
        'acronym'         => 'NR' . $n,
        'club_type'       => 'Academic',
        'type'            => 'academic',
        'course_slug'     => 'all',
        'department_slug' => 'all',
        'name'            => $name,
        'is_active'       => true,
        'adviser_id'      => $adviser->id,
    ]);

    $officer = nrUser('president');
    ClubOfficer::create(['club_id' => $club->id, 'user_id' => $officer->id, 'position' => 'president']);

    return [$club, $adviser, $officer];
}

function nrSession(User $user): array
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

/** A semester that ended $endedDaysAgo days ago (negative = still running). */
function nrSemester(int $endedDaysAgo = 1, int $lengthDays = 120): Semester
{
    return Semester::create([
        'label'      => '1st Semester 2026-2027',
        'start_date' => today()->subDays($endedDaysAgo + $lengthDays)->toDateString(),
        'end_date'   => today()->subDays($endedDaysAgo)->toDateString(),
        'is_current' => false,
    ]);
}

function nrActivity(Club $club, User $creator, array $overrides = []): ClubActivity
{
    return ClubActivity::create(array_merge([
        'club_id'               => $club->id,
        'title'                 => 'BYTE Org 1st Semester General Assembly',
        'description'           => 'The opening general assembly where officers present the semester plan.',
        'date'                  => today()->subDays(30)->toDateString(),
        'time_start'            => '08:00',
        'time_end'              => '11:00',
        'venue'                 => 'SCC Gymnasium',
        'purpose'               => 'To orient members on the semester plan and ratify the committee heads.',
        'expected_participants' => 13,
        'activity_type'         => 'internal_meeting',
        'approval_status'       => 'no_approval_needed',
        'status'                => 'completed',
        'completed_at'          => now()->subDays(30),
        'created_by'            => $creator->id,
    ], $overrides));
}

/** Check $count fresh members in to an activity, returning them. */
function nrCheckIn(ClubActivity $activity, int $count): array
{
    $users = [];

    for ($i = 0; $i < $count; $i++) {
        $user = nrUser('member');
        ClubMember::create([
            'club_id' => $activity->club_id, 'user_id' => $user->id, 'role' => 'member',
            'status' => 'active', 'registration_status' => 'approved', 'joined_at' => now()->subMonths(3),
        ]);
        Attendance::create(['event_id' => $activity->id, 'user_id' => $user->id, 'time_in' => now()->subDays(30)]);
        $users[] = $user;
    }

    return $users;
}

/** The five activities from the live test, with their recorded attendance. */
function nrSemesterOfActivities(Club $club, User $officer): array
{
    $specs = [
        ['BYTE Org 1st Semester General Assembly', 'internal_meeting', 60, '08:00', '11:00', 'SCC Gymnasium', 13, 11],
        ['ACLE: Cybersecurity Awareness in Everyday Computing', 'acle', 50, '13:00', '16:00', 'Main AV Room', 12, 9],
        ['Tech Week 2026 Planning Meeting', 'internal_meeting', 40, '15:00', '16:30', 'Function Hall', 10, 6],
        ['Web Development Workshop: Building Your First Laravel App', 'campus_resource_use', 30, '09:00', '12:00', 'Main AV Room', 12, 10],
        ['UI/UX Design Workshop with Figma', 'campus_resource_use', 20, '13:00', '16:00', 'Function Hall', 10, 7],
    ];

    $activities = [];

    foreach ($specs as [$title, $type, $daysAgo, $start, $end, $venue, $expected, $attended]) {
        $activity = nrActivity($club, $officer, [
            'title' => $title, 'activity_type' => $type, 'date' => today()->subDays($daysAgo)->toDateString(),
            'time_start' => $start, 'time_end' => $end, 'venue' => $venue, 'expected_participants' => $expected,
            'description' => "Description of {$title}.", 'purpose' => "Purpose of {$title}.",
        ]);
        nrCheckIn($activity, $attended);
        $activities[] = $activity;
    }

    return $activities;
}

/** Fake a Gemini answer; $finish is the candidate's finishReason. */
function nrFakeGemini(string $text = "The club completed its activities this semester.", string $finish = 'STOP', ?array $parts = null): void
{
    config(['gemini.api_key' => 'test-key']);

    Http::fake(['*generativelanguage*' => Http::response([
        'candidates' => [[
            'content'      => ['parts' => $parts ?? [['text' => $text]]],
            'finishReason' => $finish,
        ]],
    ])]);
}

function nrSentPrompt(): string
{
    $prompt = '';

    Http::assertSent(function ($request) use (&$prompt) {
        $prompt = (string) data_get($request->data(), 'contents.0.parts.0.text', '');

        return true;
    });

    return $prompt;
}

function nrGenerate(Club $club, Semester $semester): ?SemesterNarrativeReport
{
    GenerateSemesterNarrativeReportJob::dispatchSync($club->id, $semester->id);

    return SemesterNarrativeReport::where('club_id', $club->id)->where('semester_id', $semester->id)->first();
}

/** A narrative that covers all five nrSemesterOfActivities() activities with their attendance. */
function nrFaithfulNarrative(): string
{
    return implode("\n\n", [
        'The club completed 5 activities this semester, with 43 check-ins against 57 expected participants.',
        '"BYTE Org 1st Semester General Assembly" was held with 11 attendees of 13 expected.',
        '"ACLE: Cybersecurity Awareness in Everyday Computing" had 9 attendees of 12 expected.',
        '"Tech Week 2026 Planning Meeting" had 6 attendees of 10 expected.',
        '"Web Development Workshop: Building Your First Laravel App" had 10 attendees of 12 expected.',
        '"UI/UX Design Workshop with Figma" had 7 attendees of 10 expected.',
    ]);
}

// ── Procedure 1 — one report at the end of the semester, covering every activity ──

it('P1: drafts the reports daily on a schedule', function () {
    $sweep = collect(app(Schedule::class)->events())
        ->first(fn ($e) => str_contains($e->getSummaryForDisplay(), 'DispatchSemesterNarrativeReportsJob'));

    expect($sweep)->not->toBeNull()
        ->and($sweep->expression)->toBe('0 6 * * *');
});

it('P1: drafts nothing while the semester is still running', function () {
    nrFakeGemini();
    [$club, , $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: -10);
    nrActivity($club, $officer);

    (new DispatchSemesterNarrativeReportsJob)->handle();

    expect(SemesterNarrativeReport::count())->toBe(0);
});

it('P1: the day after the semester ends, drafts ONE report covering every completed activity', function () {
    nrFakeGemini();
    [$club, , $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: 1);
    nrSemesterOfActivities($club, $officer);

    (new DispatchSemesterNarrativeReportsJob)->handle();

    $reports = SemesterNarrativeReport::where('club_id', $club->id)->get();

    expect($reports)->toHaveCount(1)
        ->and($reports->first()->semester_id)->toBe($semester->id)
        ->and($reports->first()->activity_data['totals']['activities'])->toBe(5)
        ->and(collect($reports->first()->activity_data['activities'])->pluck('title')->all())->toBe([
            'BYTE Org 1st Semester General Assembly',
            'ACLE: Cybersecurity Awareness in Everyday Computing',
            'Tech Week 2026 Planning Meeting',
            'Web Development Workshop: Building Your First Laravel App',
            'UI/UX Design Workshop with Figma',
        ]);
});

it('P1: includes activities on the last day of the semester', function () {
    [$club, , $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: 1);
    nrActivity($club, $officer, ['title' => 'Closing Meeting', 'date' => $semester->end_date->toDateString()]);

    $data = NarrativeReportData::semester($club, $semester);

    expect(collect($data['activities'])->pluck('title'))->toContain('Closing Meeting');
});

it('P1: leaves out activities outside the semester, not completed, or of another club', function () {
    [$club, , $officer] = nrClub();
    [$otherClub, , $otherOfficer] = nrClub('Chess Club');
    $semester = nrSemester(endedDaysAgo: 1);

    nrActivity($club, $officer, ['title' => 'Counted']);
    nrActivity($club, $officer, ['title' => 'Before the semester', 'date' => $semester->start_date->copy()->subDay()->toDateString()]);
    nrActivity($club, $officer, ['title' => 'Still scheduled', 'status' => 'scheduled']);
    nrActivity($club, $officer, ['title' => 'Cancelled', 'status' => 'cancelled']);
    nrActivity($otherClub, $otherOfficer, ['title' => 'Other club']);

    $data = NarrativeReportData::semester($club, $semester);

    expect(collect($data['activities'])->pluck('title')->all())->toBe(['Counted']);
});

it('P1: gives each club its own report and skips a club that completed nothing', function () {
    nrFakeGemini();
    [$club, , $officer] = nrClub();
    [$quietClub] = nrClub('Chess Club');
    [$otherClub, , $otherOfficer] = nrClub('Debate Varsity');
    nrSemester(endedDaysAgo: 1);
    nrActivity($club, $officer);
    nrActivity($otherClub, $otherOfficer);

    (new DispatchSemesterNarrativeReportsJob)->handle();

    expect(SemesterNarrativeReport::pluck('club_id')->sort()->values()->all())->toBe([$club->id, $otherClub->id])
        ->and(SemesterNarrativeReport::where('club_id', $quietClub->id)->exists())->toBeFalse();
});

it('P1: never drafts a second report when the sweep runs again', function () {
    nrFakeGemini();
    [$club, , $officer] = nrClub();
    nrSemester(endedDaysAgo: 1);
    nrActivity($club, $officer);

    (new DispatchSemesterNarrativeReportsJob)->handle();
    (new DispatchSemesterNarrativeReportsJob)->handle();

    expect(SemesterNarrativeReport::count())->toBe(1);
});

it('P1: catches up on a semester the scheduler missed, within the catch-up window only', function () {
    nrFakeGemini();
    [$recentClub, , $recentOfficer] = nrClub();
    [$oldClub, , $oldOfficer] = nrClub('Chess Club');

    $recent = nrSemester(endedDaysAgo: 10);
    nrActivity($recentClub, $recentOfficer, ['date' => $recent->end_date->copy()->subDays(5)->toDateString()]);

    $old = Semester::create([
        'label' => '2nd Semester 2025-2026', 'is_current' => false,
        'start_date' => today()->subDays(300)->toDateString(), 'end_date' => today()->subDays(180)->toDateString(),
    ]);
    nrActivity($oldClub, $oldOfficer, ['date' => today()->subDays(200)->toDateString()]);

    (new DispatchSemesterNarrativeReportsJob)->handle();

    expect(SemesterNarrativeReport::where('semester_id', $recent->id)->count())->toBe(1)
        ->and(SemesterNarrativeReport::where('semester_id', $old->id)->count())->toBe(0);
});

it('P1: runs on the real calendar — nothing on the end date, the report the next morning', function () {
    nrFakeGemini();
    [$club, , $officer] = nrClub();
    $semester = Semester::create(['label' => '1st Semester 2026-2027', 'start_date' => '2026-08-10', 'end_date' => '2026-12-18', 'is_current' => true]);
    nrActivity($club, $officer, ['date' => '2026-12-18']);

    $this->travelTo(Carbon::parse('2026-12-18 06:00'));
    (new DispatchSemesterNarrativeReportsJob)->handle();
    expect(SemesterNarrativeReport::count())->toBe(0);

    $this->travelTo(Carbon::parse('2026-12-19 06:00'));
    (new DispatchSemesterNarrativeReportsJob)->handle();
    expect(SemesterNarrativeReport::where('semester_id', $semester->id)->count())->toBe(1);
});

it('P1: the manual command refuses a semester that has not ended unless forced', function () {
    nrFakeGemini();
    [$club, , $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: -5);
    nrActivity($club, $officer);

    $this->artisan('narrative-reports:generate', ['semester' => $semester->id])->assertFailed();
    expect(SemesterNarrativeReport::count())->toBe(0);

    $this->artisan('narrative-reports:generate', ['semester' => $semester->id, '--force' => true])->assertSuccessful();
    expect(SemesterNarrativeReport::count())->toBe(1);
});

it('P1: lets the DSA define the semesters the reports run on', function () {
    $dsa = nrUser('dsa');

    $this->withSession(nrSession($dsa))->post(route('dsa.semesters.store'), [
        'label' => '1st Semester, A.Y. 2026-2027', 'start_date' => '2026-08-10', 'end_date' => '2026-12-18', 'is_current' => '1',
    ])->assertRedirect(route('dsa.semesters.index'));

    $semester = Semester::first();

    expect($semester->end_date->toDateString())->toBe('2026-12-18')
        ->and($semester->is_current)->toBeTrue()
        ->and(AuditLog::where('action', 'semester.created')->exists())->toBeTrue();
});

it('P1: refuses overlapping semesters and a semester that ends before it starts', function () {
    $dsa = nrUser('dsa');
    Semester::create(['label' => 'Existing', 'start_date' => '2026-08-10', 'end_date' => '2026-12-18', 'is_current' => false]);

    $this->withSession(nrSession($dsa))->post(route('dsa.semesters.store'), [
        'label' => 'Overlap', 'start_date' => '2026-12-01', 'end_date' => '2027-05-01',
    ])->assertSessionHasErrors('start_date');

    $this->withSession(nrSession($dsa))->post(route('dsa.semesters.store'), [
        'label' => 'Backwards', 'start_date' => '2027-05-01', 'end_date' => '2027-01-10',
    ])->assertSessionHasErrors('end_date');

    expect(Semester::count())->toBe(1);
});

it('P1: keeps a single current semester', function () {
    $dsa = nrUser('dsa');
    $old = Semester::create(['label' => 'Old', 'start_date' => '2026-01-05', 'end_date' => '2026-05-20', 'is_current' => true]);

    $this->withSession(nrSession($dsa))->post(route('dsa.semesters.store'), [
        'label' => 'New', 'start_date' => '2026-08-10', 'end_date' => '2026-12-18', 'is_current' => '1',
    ]);

    expect($old->fresh()->is_current)->toBeFalse()
        ->and(Semester::where('is_current', true)->pluck('label')->all())->toBe(['New']);
});

it('P1: the semester page is DSA-only', function (string $role) {
    $this->withSession(nrSession(nrUser($role)))->get(route('dsa.semesters.index'))->assertForbidden();
})->with(['member', 'adviser', 'president']);

// ── Procedure 2 — each activity's details ────────────────────────────────────

it("P2: sends every activity's title, type, date, time, venue, description and purpose to the AI", function () {
    nrFakeGemini();
    [$club, , $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: 1);
    nrActivity($club, $officer, [
        'title' => 'Web Development Workshop: Building Your First Laravel App', 'activity_type' => 'campus_resource_use',
        'date' => '2026-09-08', 'time_start' => '09:00', 'time_end' => '12:00', 'venue' => 'Main AV Room',
        'description' => 'Participants build a task-list app with Laravel.', 'purpose' => 'To give members practical web development experience.',
    ]);

    nrGenerate($club, $semester);
    $prompt = nrSentPrompt();

    expect($prompt)
        ->toContain('Title: "Web Development Workshop: Building Your First Laravel App"')
        ->toContain('Type: Campus Resource Use')
        ->toContain('Date: September 8, 2026')
        ->toContain('Time: 9:00 AM to 12:00 PM')
        ->toContain('Venue: Main AV Room')
        ->toContain('Participants build a task-list app with Laravel.')
        ->toContain('To give members practical web development experience.');
});

it('P2: names the ACLE type in full', function () {
    [$club, , $officer] = nrClub();
    $activity = nrActivity($club, $officer, ['activity_type' => 'acle']);

    expect(NarrativeReportData::activity($activity)['type'])->toBe('ACLE (Alternative Classroom Learning Experience)');
});

it('P2: reads PostgreSQL-style H:i:s times the same way', function () {
    [$club, , $officer] = nrClub();
    $activity = nrActivity($club, $officer, ['time_start' => '15:00:00', 'time_end' => '16:30:00']);

    expect(NarrativeReportData::activity($activity)['time'])->toBe('3:00 PM to 4:30 PM');
});

it('P2: does not store a draft that Gemini cut off, and flags it for a manual draft instead', function () {
    nrFakeGemini('Building the Youth through Technology Education gathered at the Function Hall on September 17, 202', 'MAX_TOKENS');
    [$club, , $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: 1);
    nrActivity($club, $officer);

    $report = nrGenerate($club, $semester);

    expect($report->draft_content)->toBeNull()
        ->and($report->ai_available)->toBeFalse()
        ->and($report->status)->toBe('pending_review');
});

it('P2: the per-activity bulletin recap also rejects a cut-off draft', function () {
    nrFakeGemini('*Draft P2:* Putting theory into practice, the workshop centered on hands', 'MAX_TOKENS');
    [$club, , $officer] = nrClub();
    $activity = nrActivity($club, $officer);

    GenerateClubNarrativeJob::dispatchSync($activity->id);

    $narrative = ClubNarrative::where('club_activity_id', $activity->id)->first();

    expect($narrative->draft_content)->toBeNull()
        ->and($narrative->ai_available)->toBeFalse();
});

it('P2: joins an answer split across parts and skips thought summaries', function () {
    nrFakeGemini(parts: [
        ['text' => 'Planning the answer...', 'thought' => true],
        ['text' => 'First paragraph. '],
        ['text' => 'Second paragraph.'],
    ]);
    [$club, , $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: 1);
    nrActivity($club, $officer);

    expect(nrGenerate($club, $semester)->draft_content)->toBe('First paragraph. Second paragraph.');
});

it('P2: leaves the model room to finish even if it thinks first', function () {
    nrFakeGemini();
    [$club, , $officer] = nrClub();
    $activity = nrActivity($club, $officer);

    app(GeminiService::class)->draftClubNarrative($activity, $club);

    Http::assertSent(fn ($request) => data_get($request->data(), 'generationConfig.maxOutputTokens') >= 2048);
});

// ── Procedure 3 — expected participants and actual attendees ─────────────────

it('P3: counts attendees from the attendance sheet, not the club roster', function () {
    [$club, , $officer] = nrClub();
    $activity = nrActivity($club, $officer, ['expected_participants' => 12]);
    nrCheckIn($activity, 9);

    // A registered no-show, and members who never touched the sheet.
    $noShow = nrUser('member');
    Attendance::create(['event_id' => $activity->id, 'user_id' => $noShow->id, 'time_in' => null]);
    ClubMember::create(['club_id' => $club->id, 'user_id' => nrUser('member')->id, 'role' => 'member', 'status' => 'active', 'registration_status' => 'approved']);

    $facts = NarrativeReportData::activity($activity);

    expect($facts['attended'])->toBe(9)
        ->and($facts['expected'])->toBe(12)
        ->and($facts['turnout'])->toBe(75.0)
        ->and($facts['attendance_recorded'])->toBeTrue();
});

it("P3: puts each activity's expected and actual attendance in the prompt", function () {
    nrFakeGemini();
    [$club, , $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: 1);
    nrSemesterOfActivities($club, $officer);

    nrGenerate($club, $semester);
    $prompt = nrSentPrompt();

    expect($prompt)
        ->toContain("Expected participants: 12\nActual attendees (from the attendance record): 9 (75% of expected)")
        ->toContain("Expected participants: 13\nActual attendees (from the attendance record): 11 (84.6% of expected)")
        ->toContain('Total attendance across all activities: 43 check-ins by 43 different members')
        ->toContain('Total expected participants across all activities: 57 (overall turnout 75.4% of expected)');
});

it('P3: totals count a member who attended several activities once', function () {
    [$club, , $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: 1);
    $first  = nrActivity($club, $officer, ['title' => 'First']);
    $second = nrActivity($club, $officer, ['title' => 'Second']);

    foreach (nrCheckIn($first, 3) as $user) {
        Attendance::create(['event_id' => $second->id, 'user_id' => $user->id, 'time_in' => now()->subDays(30)]);
    }

    $totals = NarrativeReportData::semester($club, $semester)['totals'];

    expect($totals['attended'])->toBe(6)
        ->and($totals['unique_attendees'])->toBe(3);
});

it('P3: says so plainly when no attendance was taken', function () {
    [$club, , $officer] = nrClub();
    $activity = nrActivity($club, $officer);

    $text = NarrativeReportData::activityText(NarrativeReportData::activity($activity));

    expect($text)->toContain('Actual attendees (from the attendance record): not recorded (no attendance was taken)');
});

it('P3: the bulletin recap prompt carries the attendance too', function () {
    nrFakeGemini();
    [$club, , $officer] = nrClub();
    $activity = nrActivity($club, $officer, ['expected_participants' => 10]);
    nrCheckIn($activity, 6);

    app(GeminiService::class)->draftClubNarrative($activity, $club);

    expect(nrSentPrompt())->toContain("Expected participants: 10\nActual attendees (from the attendance record): 6 (60% of expected)");
});

it("P3: warns the adviser when the narrative leaves out an activity's attendance", function () {
    nrFakeGemini();
    [$club, , $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: 1);
    nrSemesterOfActivities($club, $officer);
    $report = nrGenerate($club, $semester);

    $report->update(['adviser_edited_content' => str_replace(' with 7 attendees of 10 expected', '', str_replace('had 7 attendees of 10 expected', 'was held', nrFaithfulNarrative()))]);

    expect($report->reviewWarnings())->toBe([
        'The narrative does not state the attendance for "UI/UX Design Workshop with Figma" (7 attended of 10 expected).',
    ]);
});

// ── Procedure 4 — grounded in the records ────────────────────────────────────

it('P4: tells the model the plans were written before the activity and must not be reported as results', function () {
    nrFakeGemini();
    [$club, , $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: 1);
    nrActivity($club, $officer);

    nrGenerate($club, $semester);

    expect(nrSentPrompt())
        ->toContain('Description (written by the officer before the activity)')
        ->toContain('Purpose / objectives (written by the officer before the activity)')
        ->toContain('Never state that an objective was achieved')
        ->toContain('Do not calculate new figures')
        ->toContain('Treat them as the only source of truth');
});

it("P4: lets the officer's post-activity report be used as the source of outcomes", function () {
    nrFakeGemini();
    [$club, , $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: 1);
    nrActivity($club, $officer, ['post_report_content' => 'Members elected four committee heads.']);

    nrGenerate($club, $semester);

    expect(nrSentPrompt())->toContain("Officer's post-activity report: Members elected four committee heads.");
});

it('P4: the bulletin recap is written under the same accuracy rules', function () {
    nrFakeGemini();
    [$club, , $officer] = nrClub();
    $activity = nrActivity($club, $officer);

    app(GeminiService::class)->draftClubNarrative($activity, $club);

    expect(nrSentPrompt())->toContain('Never state that an objective was achieved');
});

it('P4: raises no warnings for a narrative that matches the records', function () {
    nrFakeGemini(nrFaithfulNarrative());
    [$club, , $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: 1);
    nrSemesterOfActivities($club, $officer);

    expect(nrGenerate($club, $semester)->reviewWarnings())->toBe([]);
});

it('P4: flags figures that appear nowhere in the records', function () {
    nrFakeGemini(nrFaithfulNarrative() . "\n\nOver 150 students benefited, and 98% rated the workshops highly.");
    [$club, , $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: 1);
    nrSemesterOfActivities($club, $officer);

    expect(nrGenerate($club, $semester)->reviewWarnings())
        ->toBe(['These figures do not appear in the activity records: 150, 98.']);
});

it('P4: flags an activity the narrative never mentions', function () {
    nrFakeGemini(str_replace("\n\n" . '"Tech Week 2026 Planning Meeting" had 6 attendees of 10 expected.', '', nrFaithfulNarrative()));
    [$club, , $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: 1);
    nrSemesterOfActivities($club, $officer);

    expect(nrGenerate($club, $semester)->reviewWarnings())
        ->toBe(['The narrative does not mention "Tech Week 2026 Planning Meeting".']);
});

it('P4: accepts one clear part of a two-part title as a mention', function () {
    nrFakeGemini(str_replace('"ACLE: Cybersecurity Awareness in Everyday Computing"', 'The ACLE on Cybersecurity Awareness in Everyday Computing', nrFaithfulNarrative()));
    [$club, , $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: 1);
    nrSemesterOfActivities($club, $officer);

    expect(nrGenerate($club, $semester)->reviewWarnings())->toBe([]);
});

// ── Procedure 5 — held for the adviser (regression for the new report) ──────

it('P5: holds the drafted report for the adviser and tells only the adviser', function () {
    nrFakeGemini();
    [$club, $adviser, $officer] = nrClub();
    $dsa = nrUser('dsa');
    $semester = nrSemester(endedDaysAgo: 1);
    nrActivity($club, $officer);

    $report = nrGenerate($club, $semester);

    expect($report->status)->toBe('pending_review')
        ->and($report->submitted_at)->toBeNull()
        ->and($report->pdf_path)->toBeNull()
        ->and(ClubNotification::where('recipient_id', $adviser->id)->where('title', 'Semester narrative report ready for review')->exists())->toBeTrue()
        ->and(ClubNotification::where('recipient_id', $dsa->id)->exists())->toBeFalse()
        ->and(AuditLog::where('action', 'ai.semester_report.generated')->where('resource_id', $report->id)->exists())->toBeTrue();
});

it('P5: keeps an unsubmitted report away from the DSA', function () {
    nrFakeGemini();
    [$club, , $officer] = nrClub();
    $dsa = nrUser('dsa');
    $semester = nrSemester(endedDaysAgo: 1);
    nrActivity($club, $officer);
    $report = nrGenerate($club, $semester);

    $this->withSession(nrSession($dsa))->get(route('dsa.semester-reports.index'))
        ->assertOk()->assertDontSee($club->name);

    $this->withSession(nrSession($dsa))->get(route('dsa.semester-reports.pdf', $report))->assertNotFound();
});

it('P5: refuses the adviser of another club', function () {
    nrFakeGemini();
    [$club, , $officer] = nrClub();
    [, $otherAdviser] = nrClub('Chess Club');
    $semester = nrSemester(endedDaysAgo: 1);
    nrActivity($club, $officer);
    $report = nrGenerate($club, $semester);

    $this->withSession(nrSession($otherAdviser))->get(route('adviser.semester-reports.show', $report))->assertForbidden();
    $this->withSession(nrSession($otherAdviser))->patch(route('adviser.semester-reports.update', $report), [
        'content' => nrFaithfulNarrative(), 'intent' => 'submit',
    ])->assertForbidden();

    expect($report->fresh()->status)->toBe('pending_review');
});

it('P5: gives officers and members no way to submit the report', function (string $role) {
    nrFakeGemini();
    [$club, , $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: 1);
    nrActivity($club, $officer);
    $report = nrGenerate($club, $semester);

    $this->withSession(nrSession($role === 'president' ? $officer : nrUser($role)))
        ->patch(route('adviser.semester-reports.update', $report), ['content' => nrFaithfulNarrative(), 'intent' => 'submit'])
        ->assertForbidden();

    expect($report->fresh()->status)->toBe('pending_review');
})->with(['president', 'member']);

it('P5: holds the report and alerts the DSA when the club has no adviser', function () {
    nrFakeGemini();
    [$club, , $officer] = nrClub();
    $club->update(['adviser_id' => null]);
    $dsa = nrUser('dsa');
    $semester = nrSemester(endedDaysAgo: 1);
    nrActivity($club, $officer);

    $report = nrGenerate($club, $semester);

    expect($report->status)->toBe('pending_review')
        ->and(ClubNotification::where('recipient_id', $dsa->id)->where('title', 'Semester narrative report held: no adviser')->exists())->toBeTrue();
});

// ── Procedure 6 — a document handed over to the DSA ──────────────────────────

it('P6: submitting the report produces a PDF, marks it submitted, and notifies the DSA', function () {
    Storage::fake('local');
    nrFakeGemini(nrFaithfulNarrative());
    [$club, $adviser, $officer] = nrClub();
    $dsa = nrUser('dsa');
    $semester = nrSemester(endedDaysAgo: 1);
    nrSemesterOfActivities($club, $officer);
    $report = nrGenerate($club, $semester);

    $this->withSession(nrSession($adviser))->patch(route('adviser.semester-reports.update', $report), [
        'content' => nrFaithfulNarrative(), 'intent' => 'submit',
    ])->assertRedirect(route('adviser.semester-reports.show', $report));

    $report->refresh();

    expect($report->status)->toBe('submitted')
        ->and($report->reviewed_by)->toBe($adviser->id)
        ->and($report->submitted_at)->not->toBeNull()
        ->and($report->adviser_edited_content)->toBeNull()
        ->and(Storage::disk('local')->exists($report->pdf_path))->toBeTrue()
        ->and(Storage::disk('local')->get($report->pdf_path))->toStartWith('%PDF')
        ->and(ClubNotification::where('recipient_id', $dsa->id)->where('title', 'Semester narrative report submitted')->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'semester_report.submitted')->where('resource_id', $report->id)->exists())->toBeTrue();
});

it('P6: the DSA sees the submitted report and downloads the PDF', function () {
    Storage::fake('local');
    nrFakeGemini(nrFaithfulNarrative());
    [$club, $adviser, $officer] = nrClub();
    $dsa = nrUser('dsa');
    $semester = nrSemester(endedDaysAgo: 1);
    nrSemesterOfActivities($club, $officer);
    $report = nrGenerate($club, $semester);

    $this->withSession(nrSession($adviser))->patch(route('adviser.semester-reports.update', $report), [
        'content' => nrFaithfulNarrative(), 'intent' => 'submit',
    ]);

    $this->withSession(nrSession($dsa))->get(route('dsa.semester-reports.index'))
        ->assertOk()->assertSee($club->name)->assertSee('1st Semester 2026-2027');

    $download = $this->withSession(nrSession($dsa))->get(route('dsa.semester-reports.pdf', $report));

    $download->assertOk()->assertDownload(strtolower($club->acronym) . '-1st-semester-2026-2027-narrative-report.pdf');
});

it('P6: the document carries the narrative, every activity, and its attendance', function () {
    nrFakeGemini(nrFaithfulNarrative());
    [$club, $adviser, $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: 1);
    nrSemesterOfActivities($club, $officer);
    $report = nrGenerate($club, $semester);
    $report->update(['status' => 'submitted', 'submitted_at' => now()]);

    $html = view('reports.semester-narrative-pdf', [
        'report'     => $report,
        'data'       => $report->activity_data,
        'paragraphs' => preg_split('/\n\s*\n/', $report->finalContent()),
        'reviewer'   => $adviser,
    ])->render();

    expect($html)
        ->toContain('Semester Narrative Report')
        ->toContain('Director of Student Affairs')
        ->toContain('The club completed 5 activities this semester')
        ->toContain('ACLE: Cybersecurity Awareness in Everyday Computing')
        ->toContain('ACLE (Alternative Classroom Learning Experience)')
        ->toContain('1:00 PM to 4:00 PM')
        ->toContain('Main AV Room')
        ->toContain('Purpose of UI/UX Design Workshop with Figma.')
        ->toContain('<td class="num">12</td>')
        ->toContain('<td class="num">9</td>')
        ->toContain('<td class="num">75%</td>')
        ->toContain('<td class="num">43</td>')
        ->toContain($adviser->name);
});

it("P6: saving keeps the report with the adviser — no document, no DSA notice", function () {
    Storage::fake('local');
    nrFakeGemini('A draft the adviser will rework.');
    [$club, $adviser, $officer] = nrClub();
    $dsa = nrUser('dsa');
    $semester = nrSemester(endedDaysAgo: 1);
    nrActivity($club, $officer);
    $report = nrGenerate($club, $semester);

    $this->withSession(nrSession($adviser))->patch(route('adviser.semester-reports.update', $report), [
        'content' => 'An edited narrative that the adviser is still working on.', 'intent' => 'save',
    ]);

    $report->refresh();

    expect($report->status)->toBe('pending_review')
        ->and($report->adviser_edited_content)->toBe('An edited narrative that the adviser is still working on.')
        ->and($report->pdf_path)->toBeNull()
        ->and(ClubNotification::where('recipient_id', $dsa->id)->exists())->toBeFalse();
});

it('P6: a submitted report cannot be changed', function () {
    Storage::fake('local');
    nrFakeGemini(nrFaithfulNarrative());
    [$club, $adviser, $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: 1);
    nrActivity($club, $officer);
    $report = nrGenerate($club, $semester);

    $this->withSession(nrSession($adviser))->patch(route('adviser.semester-reports.update', $report), ['content' => nrFaithfulNarrative(), 'intent' => 'submit']);
    $this->withSession(nrSession($adviser))->patch(route('adviser.semester-reports.update', $report), ['content' => 'Rewritten after submission, which must not stick.', 'intent' => 'save'])
        ->assertSessionHasErrors('status');

    expect($report->fresh()->finalContent())->toBe(nrFaithfulNarrative());
});

it('P6: will not submit an empty narrative', function () {
    Storage::fake('local');
    nrFakeGemini();
    [$club, $adviser, $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: 1);
    nrActivity($club, $officer);
    $report = nrGenerate($club, $semester);

    $this->withSession(nrSession($adviser))->patch(route('adviser.semester-reports.update', $report), ['content' => '', 'intent' => 'submit'])
        ->assertSessionHasErrors('content');

    expect($report->fresh()->status)->toBe('pending_review');
});

it('P6: with Gemini unavailable the report is still created and the adviser can write and submit it', function () {
    Storage::fake('local');
    config(['gemini.api_key' => '']);
    Http::fake();
    [$club, $adviser, $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: 1);
    nrActivity($club, $officer);

    $report = nrGenerate($club, $semester);

    expect($report->ai_available)->toBeFalse()
        ->and($report->draft_content)->toBeNull();

    $this->withSession(nrSession($adviser))->get(route('adviser.semester-reports.show', $report))
        ->assertOk()->assertSee('AI Unavailable — Manual Draft Required');

    $this->withSession(nrSession($adviser))->patch(route('adviser.semester-reports.update', $report), [
        'content' => 'The club held its general assembly this semester, with its attendance recorded below.', 'intent' => 'submit',
    ]);

    expect($report->fresh()->status)->toBe('submitted');
    Http::assertNothingSent();
});

it('P6: only the DSA can open the submitted reports list', function (string $role) {
    $this->withSession(nrSession(nrUser($role)))->get(route('dsa.semester-reports.index'))->assertForbidden();
})->with(['member', 'adviser', 'president']);
