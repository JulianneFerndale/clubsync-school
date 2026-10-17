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
use App\Services\ActivityPhotoStorage;
use App\Services\GeminiService;
use App\Services\NarrativeDocuments\Document;
use App\Services\NarrativeDocuments\DocumentBuilder;
use App\Services\NarrativeDocuments\ReportDocuments;
use App\Services\NarrativeReportData;
use Carbon\Carbon;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;

uses(RefreshDatabase::class);

/*
 * A.4 AI Narrative Report Cluster — see docs/test.docx and TASK/tests-logs.md.
 *
 * The requirement: at the end of the semester, the AI drafts one narrative report per
 * club summarising every activity it conducted, with each activity's details and its
 * attendance, and the report is handed to the DSA as a document after the adviser has
 * reviewed it. Gemini is faked throughout; the live run is logged in tests-logs.md.
 *
 * Since 2026-10-01 the report is the five DSA documents (accomplishment, minutes,
 * financial, property inventory, communications), drafted section by section for each
 * activity and handed over as Word files that also download as PDFs. Reports drafted
 * before that ("legacy") keep their single narrative and PDF, and are tested as such.
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
        'venue'                 => 'AVR',
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
        ['BYTE Org 1st Semester General Assembly', 'internal_meeting', 60, '08:00', '11:00', 'AVR', 13, 11],
        ['ACLE: Cybersecurity Awareness in Everyday Computing', 'acle', 50, '13:00', '16:00', 'Moot Court', 12, 9],
        ['Tech Week 2026 Planning Meeting', 'internal_meeting', 40, '15:00', '16:30', 'Library', 10, 6],
        ['Web Development Workshop: Building Your First Laravel App', 'campus_resource_use', 30, '09:00', '12:00', 'Moot Court', 12, 10],
        ['UI/UX Design Workshop with Figma', 'campus_resource_use', 20, '13:00', '16:00', 'Library', 10, 7],
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

/** A complete Gemini answer for one activity's sections, as the JSON it is asked for. */
function nrSections(array $overrides = []): string
{
    return json_encode(array_merge([
        'introduction'    => 'The activity was held as recorded.',
        'rationale'       => 'It was organised to serve the members.',
        'minutes'         => 'Agenda: As planned.',
        'recommendations' => '- Keep the attendance sheet.',
    ], $overrides));
}

/** @return list<string> Every prompt sent to Gemini, in order. */
function nrSentPrompts(): array
{
    return Http::recorded()
        ->map(fn ($pair) => (string) data_get($pair[0]->data(), 'contents.0.parts.0.text', ''))
        ->values()
        ->all();
}

/** A five-document report with blank sections, made without Gemini. */
function nrSectionReport(Club $club, Semester $semester): SemesterNarrativeReport
{
    $data  = NarrativeReportData::semester($club, $semester);
    $blank = collect($data['activities'])
        ->mapWithKeys(fn ($a) => [$a['id'] => array_fill_keys(array_keys(SemesterNarrativeReport::SECTIONS), '')])
        ->all();

    return SemesterNarrativeReport::create([
        'club_id' => $club->id, 'semester_id' => $semester->id, 'activity_data' => $data,
        'ai_sections' => $blank, 'sections' => $blank, 'ai_available' => false, 'status' => 'pending_review',
    ]);
}

/** Sections for every activity of a report that say only what its records say. */
function nrFaithfulSections(SemesterNarrativeReport $report): array
{
    return collect($report->activity_data['activities'])->mapWithKeys(fn ($a) => [$a['id'] => [
        'introduction'    => $a['attendance_recorded']
            ? "\"{$a['title']}\" had {$a['attended']} attendees of {$a['expected']} expected."
            : "\"{$a['title']}\" was held; attendance was not recorded.",
        'rationale'       => 'It was organised to serve the members.',
        'minutes'         => $a['is_meeting'] ? 'Agenda: As planned.' : '',
        'recommendations' => '- Keep the attendance sheet.',
    ]])->all();
}

/** A report drafted before the five documents existed: one narrative, no sections. */
function nrLegacyReport(Club $club, Semester $semester, string $content): SemesterNarrativeReport
{
    return SemesterNarrativeReport::create([
        'club_id' => $club->id, 'semester_id' => $semester->id,
        'activity_data' => NarrativeReportData::semester($club, $semester),
        'draft_content' => $content, 'ai_available' => true, 'status' => 'pending_review',
    ]);
}

/** A photo attached through the real storage path (re-encoded, filed, recorded). */
function nrPhoto(ClubActivity $activity, User $officer, string $category, array $details = []): \App\Models\ActivityPhoto
{
    return app(ActivityPhotoStorage::class)->store(
        UploadedFile::fake()->image('photo.jpg', 64, 48), $activity, ['category' => $category] + $details, $officer->id,
    );
}

/** Every piece of text a document prints, one per line. */
function nrDocumentText(Document $document): string
{
    $lines = [];

    foreach ($document->blocks as $block) {
        if ($block['type'] === 'paragraph') {
            $lines[] = implode('', array_column($block['runs'], 'text'));
        } elseif ($block['type'] === 'table') {
            $lines[] = implode(' | ', array_column($block['columns'], 'label'));
            foreach ([...$block['rows'], ...($block['total'] ? [$block['total']] : [])] as $row) {
                $lines[] = implode(' | ', $row);
            }
            if ($block['rows'] === []) {
                $lines[] = $block['empty'];
            }
        } elseif ($block['type'] === 'photos') {
            $lines[] = $block['photos'] === [] ? $block['empty'] : implode(' | ', array_column($block['photos'], 'caption'));
        } elseif ($block['type'] === 'cover') {
            array_push($lines, $block['title'], implode('', array_column($block['term'], 'text')), (string) $block['year'], $block['club'], $block['date']);
            foreach ($block['lines'] as $runs) {
                $lines[] = implode('', array_column($runs, 'text'));
            }
        }
    }

    return implode("\n", $lines);
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

it('P1: concluding a running semester drafts the reports now, for adviser review, without moving its dates', function () {
    nrFakeGemini();
    [$club, $adviser, $officer] = nrClub();
    [$idle] = nrClub('Club With Nothing Completed');
    $semester = nrSemester(endedDaysAgo: -30);
    nrActivity($club, $officer);
    $dsa = nrUser('dsa');

    $this->withSession(nrSession($dsa))->post(route('dsa.semesters.conclude', $semester))
        ->assertRedirect(route('dsa.semesters.index'))
        ->assertSessionHas('success');

    $report = SemesterNarrativeReport::sole();

    expect($report->club_id)->toBe($club->id)
        ->and($report->status)->toBe('pending_review')
        ->and(SemesterNarrativeReport::where('club_id', $idle->id)->exists())->toBeFalse()
        ->and(ClubNotification::where('recipient_id', $adviser->id)->where('title', 'Semester narrative report ready for review')->exists())->toBeTrue()
        ->and($semester->fresh()->end_date->toDateString())->toBe($semester->end_date->toDateString())
        ->and(AuditLog::where('action', 'semester.concluded')->exists())->toBeTrue();
});

it('P1: concluding twice drafts no second report', function () {
    nrFakeGemini();
    [$club, , $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: -30);
    nrActivity($club, $officer);
    $dsa = nrUser('dsa');

    $this->withSession(nrSession($dsa))->post(route('dsa.semesters.conclude', $semester));
    $this->withSession(nrSession($dsa))->post(route('dsa.semesters.conclude', $semester))
        ->assertSessionHas('success', fn (string $message) => str_starts_with($message, 'Nothing to draft'));

    expect(SemesterNarrativeReport::count())->toBe(1);
});

it('P1: a semester that has not started cannot be concluded', function () {
    nrFakeGemini();
    [$club, , $officer] = nrClub();
    $semester = Semester::create([
        'label' => 'Next Semester', 'start_date' => today()->addDays(10)->toDateString(),
        'end_date' => today()->addDays(130)->toDateString(), 'is_current' => false,
    ]);
    nrActivity($club, $officer, ['date' => today()->addDays(20)->toDateString()]);

    $this->withSession(nrSession(nrUser('dsa')))->post(route('dsa.semesters.conclude', $semester))
        ->assertSessionHasErrors('semester');

    expect(SemesterNarrativeReport::count())->toBe(0);
});

it('P1: only the DSA can conclude a semester', function (string $role) {
    $semester = nrSemester(endedDaysAgo: -30);

    $this->withSession(nrSession(nrUser($role)))->post(route('dsa.semesters.conclude', $semester))->assertForbidden();
})->with(['member', 'adviser', 'president']);

// ── Procedure 2 — each activity's details ────────────────────────────────────

it("P2: sends every activity's title, type, date, time, venue, description and purpose to the AI", function () {
    nrFakeGemini();
    [$club, , $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: 1);
    nrActivity($club, $officer, [
        'title' => 'Web Development Workshop: Building Your First Laravel App', 'activity_type' => 'campus_resource_use',
        'date' => '2026-09-08', 'time_start' => '09:00', 'time_end' => '12:00', 'venue' => 'Moot Court',
        'description' => 'Participants build a task-list app with Laravel.', 'purpose' => 'To give members practical web development experience.',
    ]);

    nrGenerate($club, $semester);
    $prompt = nrSentPrompt();

    expect($prompt)
        ->toContain('Title: "Web Development Workshop: Building Your First Laravel App"')
        ->toContain('Type: Campus Resource Use')
        ->toContain('Date: September 8, 2026')
        ->toContain('Time: 9:00 AM to 12:00 PM')
        ->toContain('Venue: Moot Court')
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
    nrFakeGemini(substr(nrSections(), 0, 60), 'MAX_TOKENS');
    [$club, , $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: 1);
    $activity = nrActivity($club, $officer);

    $report = nrGenerate($club, $semester);

    // Not the partial answer: the activity starts from the template of its records instead.
    expect($report->draftSource($activity->id))->toBe('template')
        ->and($report->section($activity->id, 'introduction'))->toStartWith('"' . $activity->title . '" (')
        ->and($report->ai_available)->toBeFalse()
        ->and($report->status)->toBe('pending_review');
});

it('P2: discards an answer that is not the complete set of sections', function () {
    nrFakeGemini(json_encode(['introduction' => 'Only an introduction.']));
    [$club, , $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: 1);
    $activity = nrActivity($club, $officer);

    $report = nrGenerate($club, $semester);

    expect($report->draftSource($activity->id))->toBe('template')
        ->and($report->section($activity->id, 'introduction'))->not->toBe('Only an introduction.')
        ->and($report->ai_available)->toBeFalse();
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
    $json = nrSections(['introduction' => 'First paragraph. Second paragraph.']);
    $cut  = strpos($json, 'Second');
    nrFakeGemini(parts: [
        ['text' => 'Planning the answer...', 'thought' => true],
        ['text' => substr($json, 0, $cut)],
        ['text' => substr($json, $cut)],
    ]);
    [$club, , $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: 1);
    $activity = nrActivity($club, $officer);

    expect(nrGenerate($club, $semester)->section($activity->id, 'introduction'))->toBe('First paragraph. Second paragraph.');
});

it('P2: leaves the model room to finish even if it thinks first', function () {
    nrFakeGemini();
    [$club, , $officer] = nrClub();
    $activity = nrActivity($club, $officer);

    app(GeminiService::class)->draftClubNarrative($activity, $club);

    Http::assertSent(fn ($request) => data_get($request->data(), 'generationConfig.maxOutputTokens') >= 2048);
});

it('P2: asks for the sections as JSON, one request per activity', function () {
    nrFakeGemini(nrSections());
    [$club, , $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: 1);
    nrSemesterOfActivities($club, $officer);

    nrGenerate($club, $semester);

    Http::assertSentCount(5);
    Http::assertSent(fn ($request) => data_get($request->data(), 'generationConfig.responseMimeType') === 'application/json'
        && data_get($request->data(), 'generationConfig.responseSchema.required') === ['introduction', 'rationale', 'minutes', 'recommendations']);
});

it('P2: asks for minutes only for meetings, and keeps none for other activities', function () {
    nrFakeGemini(nrSections(['minutes' => 'Agenda: Plans.']));
    [$club, , $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: 1);
    $meeting = nrActivity($club, $officer, ['title' => 'Planning Meeting', 'activity_type' => 'internal_meeting']);
    $acle    = nrActivity($club, $officer, ['title' => 'ACLE: Chess Strategy', 'activity_type' => 'acle']);

    $report  = nrGenerate($club, $semester);
    $prompts = nrSentPrompts();

    expect(collect($prompts)->first(fn ($p) => str_contains($p, 'Planning Meeting')))->toContain('"Proceedings:"')
        ->and(collect($prompts)->first(fn ($p) => str_contains($p, 'ACLE: Chess Strategy')))->toContain('an empty string, because this activity was not a meeting')
        ->and($report->section($meeting->id, 'minutes'))->toBe('Agenda: Plans.')
        ->and($report->section($acle->id, 'minutes'))->toBe('');
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
        ->and($facts['attendance_recorded'])->toBeTrue()
        ->and($facts['attendees'])->toHaveCount(9);
});

it("P3: puts each activity's expected and actual attendance in its prompt", function () {
    nrFakeGemini(nrSections());
    [$club, , $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: 1);
    nrSemesterOfActivities($club, $officer);

    nrGenerate($club, $semester);
    $prompts = implode("\n----\n", nrSentPrompts());

    expect($prompts)
        ->toContain("Expected participants: 12\nActual attendees (from the attendance record): 9 (75% of expected)")
        ->toContain("Expected participants: 13\nActual attendees (from the attendance record): 11 (84.6% of expected)")
        ->toContain('Activity 2 of 5 completed this semester');
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

it("P3: warns the adviser when an introduction leaves out the activity's attendance", function () {
    [$club, , $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: 1);
    nrSemesterOfActivities($club, $officer);
    $report   = nrSectionReport($club, $semester);
    $sections = nrFaithfulSections($report);
    $figma    = collect($report->activity_data['activities'])->firstWhere('title', 'UI/UX Design Workshop with Figma');

    $sections[$figma['id']]['introduction'] = '"UI/UX Design Workshop with Figma" was held in the Library.';
    $report->update(['sections' => $sections]);

    expect($report->reviewWarnings())->toBe([
        'The introduction for "UI/UX Design Workshop with Figma" does not state its attendance (7 attended of 10 expected).',
    ]);
});

it("P3: a legacy narrative is still checked for each activity's attendance", function () {
    [$club, , $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: 1);
    nrSemesterOfActivities($club, $officer);
    $report = nrLegacyReport($club, $semester, str_replace('had 7 attendees of 10 expected', 'was held', nrFaithfulNarrative()));

    expect($report->reviewWarnings())->toBe([
        'The narrative does not state the attendance for "UI/UX Design Workshop with Figma" (7 attended of 10 expected).',
    ]);
});

// ── Procedure 4 — grounded in the records ────────────────────────────────────

it('P4: tells the model the plans were written before the activity and must not be reported as results', function () {
    nrFakeGemini(nrSections());
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
    nrFakeGemini(nrSections());
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

it('P4: raises no warnings for sections that match the records', function () {
    [$club, , $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: 1);
    nrSemesterOfActivities($club, $officer);
    $report = nrSectionReport($club, $semester);
    $report->update(['sections' => nrFaithfulSections($report)]);

    expect($report->reviewWarnings())->toBe([]);
});

it("P4: flags figures that appear nowhere in an activity's records", function () {
    [$club, , $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: 1);
    nrSemesterOfActivities($club, $officer);
    $report   = nrSectionReport($club, $semester);
    $sections = nrFaithfulSections($report);
    $acle     = collect($report->activity_data['activities'])->firstWhere('title', 'ACLE: Cybersecurity Awareness in Everyday Computing');

    $sections[$acle['id']]['recommendations'] = '- Over 150 students benefited, and 98% rated it highly.';
    $report->update(['sections' => $sections]);

    expect($report->reviewWarnings())
        ->toBe(['These figures for "ACLE: Cybersecurity Awareness in Everyday Computing" do not appear in its records: 150, 98.']);
});

it('P4: accepts figures from the receipts and budget the officers attached', function () {
    Storage::fake('local');
    [$club, , $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: 1);
    $activity = nrActivity($club, $officer, ['budget_amount' => 1500]);
    nrPhoto($activity, $officer, 'receipt', ['caption' => 'Tarpaulin', 'amount' => 450]);
    $report   = nrSectionReport($club, $semester);
    $sections = nrFaithfulSections($report);

    $sections[$activity->id]['rationale'] = 'Of the PHP 1,500.00 budget, receipts for PHP 450.00 were attached.';
    $report->update(['sections' => $sections]);

    expect($report->reviewWarnings())->toBe([]);
});

it('P4: flags an empty section', function () {
    [$club, , $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: 1);
    $meeting  = nrActivity($club, $officer, ['title' => 'Officers Planning Meeting']);
    $report   = nrSectionReport($club, $semester);
    $sections = nrFaithfulSections($report);

    $sections[$meeting->id]['minutes'] = '';
    $report->update(['sections' => $sections]);

    expect($report->reviewWarnings())->toBe(['Minutes of the meeting for "Officers Planning Meeting" is empty.']);
});

it('P4: a legacy narrative is still checked for figures and missing activities', function () {
    [$club, , $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: 1);
    nrSemesterOfActivities($club, $officer);

    $faithful   = nrLegacyReport($club, $semester, nrFaithfulNarrative());
    $inflated   = nrLegacyReport(nrClub()[0], $semester, nrFaithfulNarrative() . "\n\nOver 150 students benefited, and 98% rated the workshops highly.");
    $inflated->update(['activity_data' => $faithful->activity_data]);
    $incomplete = nrLegacyReport(nrClub()[0], $semester, str_replace("\n\n" . '"Tech Week 2026 Planning Meeting" had 6 attendees of 10 expected.', '', nrFaithfulNarrative()));
    $incomplete->update(['activity_data' => $faithful->activity_data]);
    $twoPart    = nrLegacyReport(nrClub()[0], $semester, str_replace('"ACLE: Cybersecurity Awareness in Everyday Computing"', 'The ACLE on Cybersecurity Awareness in Everyday Computing', nrFaithfulNarrative()));
    $twoPart->update(['activity_data' => $faithful->activity_data]);

    expect($faithful->reviewWarnings())->toBe([])
        ->and($inflated->fresh()->reviewWarnings())->toBe(['These figures do not appear in the activity records: 150, 98.'])
        ->and($incomplete->fresh()->reviewWarnings())->toBe(['The narrative does not mention "Tech Week 2026 Planning Meeting".'])
        ->and($twoPart->fresh()->reviewWarnings())->toBe([]);
});

// ── Procedure 5 — held for the adviser (regression for the new report) ──────

it('P5: holds the drafted report for the adviser and tells only the adviser', function () {
    nrFakeGemini(nrSections());
    [$club, $adviser, $officer] = nrClub();
    $dsa = nrUser('dsa');
    $semester = nrSemester(endedDaysAgo: 1);
    nrActivity($club, $officer);

    $report = nrGenerate($club, $semester);

    expect($report->status)->toBe('pending_review')
        ->and($report->submitted_at)->toBeNull()
        ->and($report->isLegacy())->toBeFalse()
        ->and(ClubNotification::where('recipient_id', $adviser->id)->where('title', 'Semester narrative report ready for review')->exists())->toBeTrue()
        ->and(ClubNotification::where('recipient_id', $dsa->id)->exists())->toBeFalse()
        ->and(AuditLog::where('action', 'ai.semester_report.generated')->where('resource_id', $report->id)->exists())->toBeTrue();
});

it('P5: keeps an unsubmitted report away from the DSA', function () {
    nrFakeGemini(nrSections());
    [$club, , $officer] = nrClub();
    $dsa = nrUser('dsa');
    $semester = nrSemester(endedDaysAgo: 1);
    nrActivity($club, $officer);
    $report = nrGenerate($club, $semester);

    $this->withSession(nrSession($dsa))->get(route('dsa.semester-reports.index'))
        ->assertOk()->assertDontSee($club->name);

    $this->withSession(nrSession($dsa))->get(route('dsa.semester-reports.pdf', $report))->assertNotFound();
    $this->withSession(nrSession($dsa))->get(route('dsa.semester-reports.document', [$report, 'accomplishment', 'docx']))->assertNotFound();
    $this->withSession(nrSession($dsa))->get(route('dsa.semester-reports.bundle', $report))->assertNotFound();
});

it('P5: refuses the adviser of another club', function () {
    nrFakeGemini(nrSections());
    [$club, , $officer] = nrClub();
    [, $otherAdviser] = nrClub('Chess Club');
    $semester = nrSemester(endedDaysAgo: 1);
    $activity = nrActivity($club, $officer);
    $report = nrGenerate($club, $semester);

    $this->withSession(nrSession($otherAdviser))->get(route('adviser.semester-reports.show', $report))->assertForbidden();
    $this->withSession(nrSession($otherAdviser))->get(route('adviser.semester-reports.document', [$report, 'minutes', 'pdf']))->assertForbidden();
    $this->withSession(nrSession($otherAdviser))->patch(route('adviser.semester-reports.update', $report), [
        'sections' => [$activity->id => ['introduction' => 'Hijacked.']], 'intent' => 'submit',
    ])->assertForbidden();

    expect($report->fresh()->status)->toBe('pending_review');
});

it('P5: gives officers and members no way to submit the report', function (string $role) {
    nrFakeGemini(nrSections());
    [$club, , $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: 1);
    $activity = nrActivity($club, $officer);
    $report = nrGenerate($club, $semester);

    $this->withSession(nrSession($role === 'president' ? $officer : nrUser($role)))
        ->patch(route('adviser.semester-reports.update', $report), ['sections' => [$activity->id => ['introduction' => 'x']], 'intent' => 'submit'])
        ->assertForbidden();

    expect($report->fresh()->status)->toBe('pending_review');
})->with(['president', 'member']);

it('P5: holds the report and alerts the DSA when the club has no adviser', function () {
    nrFakeGemini(nrSections());
    [$club, , $officer] = nrClub();
    $club->update(['adviser_id' => null]);
    $dsa = nrUser('dsa');
    $semester = nrSemester(endedDaysAgo: 1);
    nrActivity($club, $officer);

    $report = nrGenerate($club, $semester);

    expect($report->status)->toBe('pending_review')
        ->and(ClubNotification::where('recipient_id', $dsa->id)->where('title', 'Semester narrative report held: no adviser')->exists())->toBeTrue();
});

// ── Procedure 6 — the documents handed over to the DSA ───────────────────────

it('P6: submitting writes the five Word files, marks the report submitted, and notifies the DSA', function () {
    Storage::fake('local');
    nrFakeGemini(nrSections());
    [$club, $adviser, $officer] = nrClub();
    $dsa = nrUser('dsa');
    $semester = nrSemester(endedDaysAgo: 1);
    nrSemesterOfActivities($club, $officer);
    $report = nrGenerate($club, $semester);

    $this->withSession(nrSession($adviser))->patch(route('adviser.semester-reports.update', $report), [
        'sections' => nrFaithfulSections($report), 'intent' => 'submit',
    ])->assertRedirect(route('adviser.semester-reports.show', $report));

    $report->refresh();

    expect($report->status)->toBe('submitted')
        ->and($report->reviewed_by)->toBe($adviser->id)
        ->and($report->submitted_at)->not->toBeNull()
        ->and($report->sectionsEdited())->toBeTrue()
        ->and(ClubNotification::where('recipient_id', $dsa->id)->where('title', 'Semester narrative report submitted')->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'semester_report.submitted')->where('resource_id', $report->id)->exists())->toBeTrue();

    foreach (['accomplishment', 'minutes', 'financial', 'inventory', 'communications'] as $document) {
        $path = $report->documentDirectory() . "/{$document}.docx";
        expect(Storage::disk('local')->exists($path))->toBeTrue()
            ->and(Storage::disk('local')->get($path))->toStartWith('PK');
    }
});

it('P6: the DSA downloads the complete report as a PDF, and each document as Word or PDF', function () {
    Storage::fake('local');
    nrFakeGemini(nrSections());
    [$club, $adviser, $officer] = nrClub();
    $dsa = nrUser('dsa');
    $semester = nrSemester(endedDaysAgo: 1);
    nrSemesterOfActivities($club, $officer);
    $report = nrGenerate($club, $semester);

    $this->withSession(nrSession($adviser))->patch(route('adviser.semester-reports.update', $report), [
        'sections' => nrFaithfulSections($report), 'intent' => 'submit',
    ]);

    $this->withSession(nrSession($dsa))->get(route('dsa.semester-reports.index'))
        ->assertOk()->assertSee($club->name)->assertSee('1st Semester 2026-2027')->assertSee('Complete report (PDF)');

    $pdf = $this->withSession(nrSession($dsa))->get(route('dsa.semester-reports.pdf', $report));
    $pdf->assertOk()->assertDownload("{$club->acronym} - Semester Narrative Report - 1st Semester 2026-2027.pdf");
    expect($pdf->getContent())->toStartWith('%PDF');

    $docx = $this->withSession(nrSession($dsa))->get(route('dsa.semester-reports.document', [$report, 'minutes', 'docx']));
    $docx->assertOk()->assertDownload("{$club->acronym} - Minutes of Meetings - 1st Semester 2026-2027.docx");
    expect($docx->getContent())->toBe(Storage::disk('local')->get($report->fresh()->documentDirectory() . '/minutes.docx'));

    $zip = $this->withSession(nrSession($dsa))->get(route('dsa.semester-reports.bundle', $report));
    $zip->assertOk();
    expect($zip->getContent())->toStartWith('PK');
});

it('P6: the documents carry every activity, its attendance list and the reviewed sections', function () {
    nrFakeGemini(nrSections());
    [$club, , $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: 1);
    $semester->update(['term' => 'first', 'academic_year' => '2026-2027']);
    nrSemesterOfActivities($club, $officer);
    $report = nrGenerate($club, $semester);
    $report->update(['sections' => nrFaithfulSections($report)]);

    $builder        = new DocumentBuilder($report);
    $accomplishment = nrDocumentText($builder->build('accomplishment'));
    $minutes        = nrDocumentText($builder->build('minutes'));

    expect($accomplishment)
        ->toContain('ACCOMPLISHMENT & DOCUMENTATION REPORT')
        ->toContain('1st SEMESTER')
        ->toContain('S.Y 2026-2027')
        ->toContain($club->name)
        ->toContain('1. BYTE Org 1st Semester General Assembly')
        ->toContain('ACLE: Cybersecurity Awareness in Everyday Computing')
        ->toContain('"ACLE: Cybersecurity Awareness in Everyday Computing" had 9 attendees of 12 expected.')
        ->toContain('9 of 12 expected participants attended')
        ->toContain('ANNEX A')->toContain('ANNEX F')
        ->toContain('SUMMARY RECOMMENDATIONS:')
        ->and($minutes)
        ->toContain('Tech Week 2026 Planning Meeting')
        ->not->toContain('Web Development Workshop');
});

it('P6: every document carries the SCC banner, the GSC and SCC logos and the club logo', function () {
    Storage::fake('public');
    Storage::disk('public')->put('clubs/logos/nr.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
    nrFakeGemini(nrSections());
    [$club, , $officer] = nrClub();
    $club->update(['profile_photo_url' => '/storage/clubs/logos/nr.png']);
    $semester = nrSemester(endedDaysAgo: 1);
    nrActivity($club, $officer);
    $report = nrGenerate($club, $semester)->load('club');

    $docx = app(ReportDocuments::class)->document($report, 'accomplishment', 'docx')['bytes'];
    $zip  = new ZipArchive();
    $file = tempnam(sys_get_temp_dir(), 'nr');
    file_put_contents($file, $docx);
    $zip->open($file);

    $media = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        if (str_starts_with($zip->getNameIndex($i), 'word/media/')) {
            $media[] = $zip->getNameIndex($i);
        }
    }
    $header = $zip->getFromName('word/header1.xml');
    $zip->close();
    @unlink($file);

    // Banner (header), GSC logo, SCC seal and the club's logo.
    expect($media)->toHaveCount(4)
        ->and($header)->toContain('behindDoc="1"')->toContain('Saint Columban College banner');
});

it("P6: saving keeps the report with the adviser — no documents, no DSA notice", function () {
    Storage::fake('local');
    nrFakeGemini(nrSections());
    [$club, $adviser, $officer] = nrClub();
    $dsa = nrUser('dsa');
    $semester = nrSemester(endedDaysAgo: 1);
    $activity = nrActivity($club, $officer);
    $report = nrGenerate($club, $semester);

    $this->withSession(nrSession($adviser))->patch(route('adviser.semester-reports.update', $report), [
        'sections' => [$activity->id => ['introduction' => 'An introduction the adviser is still working on.']], 'intent' => 'save',
    ]);

    $report->refresh();

    expect($report->status)->toBe('pending_review')
        ->and($report->section($activity->id, 'introduction'))->toBe('An introduction the adviser is still working on.')
        ->and($report->section($activity->id, 'rationale'))->toBe('It was organised to serve the members.')
        ->and($report->ai_sections[$activity->id]['introduction'])->toBe('The activity was held as recorded.')
        ->and(Storage::disk('local')->exists($report->documentDirectory()))->toBeFalse()
        ->and(ClubNotification::where('recipient_id', $dsa->id)->exists())->toBeFalse();
});

it('P6: the adviser previews each document before submitting', function () {
    nrFakeGemini(nrSections());
    [$club, $adviser, $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: 1);
    nrActivity($club, $officer);
    $report = nrGenerate($club, $semester);

    $this->withSession(nrSession($adviser))->get(route('adviser.semester-reports.show', $report))
        ->assertOk()->assertSee('The five DSA documents')->assertSee('Property Inventory Report');

    $preview = $this->withSession(nrSession($adviser))->get(route('adviser.semester-reports.document', [$report, 'financial', 'docx']));

    $preview->assertOk();
    expect($preview->headers->get('Content-Type'))->toBe('application/vnd.openxmlformats-officedocument.wordprocessingml.document')
        ->and($preview->getContent())->toStartWith('PK');
});

it('P6: a submitted report cannot be changed', function () {
    Storage::fake('local');
    nrFakeGemini(nrSections());
    [$club, $adviser, $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: 1);
    $activity = nrActivity($club, $officer);
    $report = nrGenerate($club, $semester);

    $this->withSession(nrSession($adviser))->patch(route('adviser.semester-reports.update', $report), ['sections' => nrFaithfulSections($report), 'intent' => 'submit']);
    $this->withSession(nrSession($adviser))->patch(route('adviser.semester-reports.update', $report), [
        'sections' => [$activity->id => ['introduction' => 'Rewritten after submission, which must not stick.']], 'intent' => 'save',
    ])->assertSessionHasErrors('status');

    expect($report->fresh()->section($activity->id, 'introduction'))->not->toContain('Rewritten after submission');
});

it('P6: will not submit an activity without its introduction', function () {
    Storage::fake('local');
    nrFakeGemini(nrSections());
    [$club, $adviser, $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: 1);
    $activity = nrActivity($club, $officer);
    $report = nrGenerate($club, $semester);

    $this->withSession(nrSession($adviser))->patch(route('adviser.semester-reports.update', $report), [
        'sections' => [$activity->id => ['introduction' => '']], 'intent' => 'submit',
    ])->assertSessionHasErrors('status');

    expect($report->fresh()->status)->toBe('pending_review');
});

it('P6: with Gemini unavailable the report is still created and the adviser can write and submit it', function () {
    Storage::fake('local');
    config(['gemini.api_key' => '']);
    Http::fake();
    [$club, $adviser, $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: 1);
    $activity = nrActivity($club, $officer);

    $report = nrGenerate($club, $semester);

    expect($report->ai_available)->toBeFalse()
        ->and($report->draftSource($activity->id))->toBe('template');

    $this->withSession(nrSession($adviser))->get(route('adviser.semester-reports.show', $report))
        ->assertOk()->assertSee('AI Unavailable — Manual Draft Required')->assertSee('Template draft')
        ->assertDontSee('Draft with AI');

    $this->withSession(nrSession($adviser))->patch(route('adviser.semester-reports.update', $report), [
        'sections' => [$activity->id => [
            'introduction' => 'The club held its general assembly this semester.',
            'rationale'    => 'It was planned to orient the members.',
        ]],
        'intent' => 'submit',
    ]);

    expect($report->fresh()->status)->toBe('submitted');
    Http::assertNothingSent();
});

it('P6: a legacy report is still submitted as its single narrative PDF', function () {
    Storage::fake('local');
    [$club, $adviser, $officer] = nrClub();
    $dsa = nrUser('dsa');
    $semester = nrSemester(endedDaysAgo: 1);
    nrSemesterOfActivities($club, $officer);
    $report = nrLegacyReport($club, $semester, nrFaithfulNarrative());

    $this->withSession(nrSession($adviser))->get(route('adviser.semester-reports.show', $report))
        ->assertOk()->assertSee('Narrative')->assertDontSee('The five DSA documents');

    $this->withSession(nrSession($adviser))->patch(route('adviser.semester-reports.update', $report), [
        'content' => nrFaithfulNarrative(), 'intent' => 'submit',
    ])->assertRedirect(route('adviser.semester-reports.show', $report));

    $report->refresh();

    expect($report->status)->toBe('submitted')
        ->and(Storage::disk('local')->get($report->pdf_path))->toStartWith('%PDF');

    $this->withSession(nrSession($dsa))->get(route('dsa.semester-reports.pdf', $report))
        ->assertOk()->assertDownload(strtolower($club->acronym) . '-1st-semester-2026-2027-narrative-report.pdf');
    $this->withSession(nrSession($dsa))->get(route('dsa.semester-reports.document', [$report, 'minutes', 'docx']))->assertNotFound();
});

it('P6: only the DSA can open the submitted reports list', function (string $role) {
    $this->withSession(nrSession(nrUser($role)))->get(route('dsa.semester-reports.index'))->assertForbidden();
})->with(['member', 'adviser', 'president']);

// ── Procedure 7 — Gemini retries, the DSA returns a report, the AI redrafts one activity ──

/** A Gemini answer carrying one activity's sections, as the HTTP body Gemini sends. */
function nrSectionsAnswer(array $overrides = []): array
{
    return ['candidates' => [[
        'content'      => ['parts' => [['text' => nrSections($overrides)]]],
        'finishReason' => 'STOP',
    ]]];
}

/** Gemini answers in this order, one per request: each entry is [body, status]. */
function nrGeminiSequence(array $answers): void
{
    config(['gemini.api_key' => 'test-key']);

    $sequence = Http::fakeSequence('*generativelanguage*');
    foreach ($answers as [$body, $status]) {
        $sequence->push($body, $status);
    }
}

/** A five-document report its adviser has submitted: [report, club, adviser, officer, dsa, activity]. */
function nrSubmittedReport(): array
{
    Storage::fake('local');
    nrFakeGemini(nrSections());
    [$club, $adviser, $officer] = nrClub();
    $dsa      = nrUser('dsa');
    $semester = nrSemester(endedDaysAgo: 1);
    $activity = nrActivity($club, $officer);
    $report   = nrGenerate($club, $semester);

    test()->withSession(nrSession($adviser))->patch(route('adviser.semester-reports.update', $report), [
        'sections' => nrFaithfulSections($report), 'intent' => 'submit',
    ]);

    return [$report->fresh(), $club, $adviser, $officer, $dsa, $activity];
}

function nrReturn(SemesterNarrativeReport $report, User $dsa, string $remarks = 'Attach the documentation photos and complete the minutes.')
{
    return test()->withSession(nrSession($dsa))->post(route('dsa.semester-reports.return', $report), ['remarks' => $remarks]);
}

it('P7: asks Gemini again after a temporary 500, and keeps the draft', function () {
    Sleep::fake();
    nrGeminiSequence([[['error' => ['code' => 500, 'status' => 'INTERNAL']], 500], [nrSectionsAnswer(), 200]]);
    [$club, , $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: 1);
    $activity = nrActivity($club, $officer);

    $report = nrGenerate($club, $semester);

    expect($report->ai_available)->toBeTrue()
        ->and($report->section($activity->id, 'introduction'))->toBe('The activity was held as recorded.')
        ->and(Http::recorded())->toHaveCount(2);
    Sleep::assertSleptTimes(1);
});

it('P7: retries rate limiting and an unavailable server, up to three attempts', function () {
    Sleep::fake();
    nrGeminiSequence([[[], 429], [[], 503], [nrSectionsAnswer(), 200]]);
    [$club, , $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: 1);
    nrActivity($club, $officer);

    expect(nrGenerate($club, $semester)->ai_available)->toBeTrue()
        ->and(Http::recorded())->toHaveCount(3);
});

it('P7: gives up after three temporary errors and leaves the activity for a manual draft', function () {
    Sleep::fake();
    nrGeminiSequence([[[], 500], [[], 500], [[], 500], [nrSectionsAnswer(), 200]]);
    [$club, , $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: 1);
    $activity = nrActivity($club, $officer);

    $report = nrGenerate($club, $semester);

    expect($report->ai_available)->toBeFalse()
        ->and($report->draftSource($activity->id))->toBe('template')
        ->and(Http::recorded())->toHaveCount(3);
});

it('P7: does not retry an error that asking again cannot fix', function () {
    Sleep::fake();
    nrGeminiSequence([[['error' => ['code' => 400, 'status' => 'INVALID_ARGUMENT']], 400], [nrSectionsAnswer(), 200]]);
    [$club, , $officer] = nrClub();
    $semester = nrSemester(endedDaysAgo: 1);
    nrActivity($club, $officer);

    expect(nrGenerate($club, $semester)->ai_available)->toBeFalse()
        ->and(Http::recorded())->toHaveCount(1);
    Sleep::assertNeverSlept();
});

it('P7: does not retry a lost connection, so offline use falls back at once', function () {
    Sleep::fake();
    config(['gemini.api_key' => 'test-key']);
    Http::fake(['*generativelanguage*' => Http::failedConnection('cURL error 7: Failed to connect')]);

    expect(app(GeminiService::class)->draftAnnouncement('General Assembly', 'announcement', 'BYTE Org'))->toBeNull();
    Sleep::assertNeverSlept();
});

it('P7: the other AI features get the same retry', function () {
    Sleep::fake();
    nrGeminiSequence([[[], 502], [['candidates' => [['content' => ['parts' => [['text' => 'Members are invited to the assembly.']]], 'finishReason' => 'STOP']]], 200]]);

    expect(app(GeminiService::class)->draftAnnouncement('General Assembly', 'announcement', 'BYTE Org'))
        ->toBe('Members are invited to the assembly.');
});

it('P7: the DSA returns a submitted report to the adviser with remarks', function () {
    [$report, , $adviser, $officer, $dsa] = nrSubmittedReport();

    nrReturn($report, $dsa)->assertRedirect(route('dsa.semester-reports.index'));
    $report->refresh();

    expect($report->status)->toBe('pending_review')
        ->and($report->isReturned())->toBeTrue()
        ->and($report->submitted_at)->toBeNull()
        ->and($report->returned_by)->toBe($dsa->id)
        ->and($report->return_remarks)->toBe('Attach the documentation photos and complete the minutes.')
        ->and(ClubNotification::where('recipient_id', $adviser->id)->where('title', 'Semester narrative report returned for revision')
            ->where('body', 'like', '%Attach the documentation photos%')->exists())->toBeTrue()
        ->and(ClubNotification::where('recipient_id', $officer->id)->where('title', 'Semester narrative report returned for revision')->exists())->toBeTrue()
        ->and(AuditLog::where('action', 'semester_report.returned')->where('resource_id', $report->id)->exists())->toBeTrue();
});

it('P7: the DSA must say what to revise', function () {
    [$report, , , , $dsa] = nrSubmittedReport();

    nrReturn($report, $dsa, '')->assertSessionHasErrors('remarks');

    expect($report->fresh()->status)->toBe('submitted');
});

it('P7: only a submitted report can be returned', function () {
    nrFakeGemini(nrSections());
    [$club, , $officer] = nrClub();
    $dsa = nrUser('dsa');
    nrActivity($club, $officer);
    $report = nrGenerate($club, nrSemester(endedDaysAgo: 1));

    nrReturn($report, $dsa)->assertSessionHasErrors('revision');

    expect($report->fresh()->returned_at)->toBeNull();
});

it('P7: a report of a club with no adviser is not returned to nobody', function () {
    [$report, $club, , , $dsa] = nrSubmittedReport();
    $club->update(['adviser_id' => null]);

    nrReturn($report, $dsa)->assertSessionHasErrors('revision');

    expect($report->fresh()->status)->toBe('submitted');
});

it('P7: only the DSA can return a report', function (string $role) {
    [$report] = nrSubmittedReport();

    nrReturn($report, nrUser($role))->assertForbidden();

    expect($report->fresh()->status)->toBe('submitted');
})->with(['adviser', 'president', 'member']);

it('P7: the DSA sees which reports are waiting on a revision', function () {
    [$report, $club, , , $dsa] = nrSubmittedReport();
    nrReturn($report, $dsa);

    $this->withSession(nrSession($dsa))->get(route('dsa.semester-reports.index'))
        ->assertOk()->assertSee('Returned for Revision')->assertSee($club->name)
        ->assertSee('Attach the documentation photos and complete the minutes.');
});

it("P7: the adviser sees the DSA's remarks, revises the report and resubmits it", function () {
    [$report, , $adviser, , $dsa, $activity] = nrSubmittedReport();
    nrReturn($report, $dsa);

    $this->withSession(nrSession($adviser))->get(route('adviser.semester-reports.index'))
        ->assertOk()->assertSee('Returned by the DSA');
    $this->withSession(nrSession($adviser))->get(route('adviser.semester-reports.show', $report))
        ->assertOk()->assertSee('Returned by the DSA for revision')
        ->assertSee('Attach the documentation photos and complete the minutes.')->assertSee('Resubmit to DSA');

    $sections = nrFaithfulSections($report->fresh());
    $sections[$activity->id]['minutes'] = 'Agenda: As planned. Proceedings: The officers presented the semester plan.';

    $this->withSession(nrSession($adviser))->patch(route('adviser.semester-reports.update', $report), [
        'sections' => $sections, 'intent' => 'submit',
    ])->assertSessionHas('success', 'Revised report submitted to the DSA.');

    $report->refresh();
    $minutes  = nrDocumentText((new DocumentBuilder($report))->build('minutes'));
    $audit    = AuditLog::where('action', 'semester_report.submitted')->where('resource_id', $report->id)->orderByDesc('id')->first();

    expect($report->status)->toBe('submitted')
        ->and($report->submitted_at)->not->toBeNull()
        ->and($report->returned_at)->not->toBeNull()
        ->and($minutes)->toContain('The officers presented the semester plan.')
        ->and(Storage::disk('local')->exists($report->documentDirectory() . '/minutes.docx'))->toBeTrue()
        ->and(ClubNotification::where('recipient_id', $dsa->id)->where('title', 'Revised semester narrative report submitted')->exists())->toBeTrue()
        ->and($audit->metadata['resubmission'] ?? null)->toBeTrue();

    $this->withSession(nrSession($dsa))->get(route('dsa.semester-reports.index'))
        ->assertOk()->assertSee('revised after you returned it')->assertDontSee('Returned for Revision');
});

it('P7: a returned report lets the officers attach report photos again until it is resubmitted', function () {
    [$report, , $adviser, , $dsa, $activity] = nrSubmittedReport();

    expect($activity->submittedReport()?->id)->toBe($report->id);

    nrReturn($report, $dsa);
    expect($activity->fresh()->submittedReport())->toBeNull();

    $this->withSession(nrSession($adviser))->patch(route('adviser.semester-reports.update', $report), [
        'sections' => nrFaithfulSections($report->fresh()), 'intent' => 'submit',
    ]);
    expect($activity->fresh()->submittedReport()?->id)->toBe($report->id);
});

it('P7: the adviser has the AI draft an activity it could not draft the first time', function () {
    Storage::fake('local');
    Sleep::fake();
    nrGeminiSequence([[[], 400], [nrSectionsAnswer(['introduction' => 'Drafted on the second request.']), 200]]);
    [$club, $adviser, $officer] = nrClub();
    $activity = nrActivity($club, $officer);
    $report   = nrGenerate($club, nrSemester(endedDaysAgo: 1));

    expect($report->ai_available)->toBeFalse();
    $this->withSession(nrSession($adviser))->get(route('adviser.semester-reports.show', $report))
        ->assertSee('AI Unavailable — Manual Draft Required')->assertSee('Draft with AI');

    $this->withSession(nrSession($adviser))
        ->patch(route('adviser.semester-reports.redraft', [$report, $activity->id]), ['sections' => []])
        ->assertRedirect(route('adviser.semester-reports.show', $report) . "#activity-{$activity->id}")
        ->assertSessionHas('success');

    $report->refresh();

    expect($report->section($activity->id, 'introduction'))->toBe('Drafted on the second request.')
        ->and($report->ai_sections[$activity->id]['introduction'])->toBe('Drafted on the second request.')
        ->and($report->ai_available)->toBeTrue()
        ->and($report->status)->toBe('pending_review')
        ->and(AuditLog::where('action', 'ai.semester_report.section_redrafted')->where('resource_id', $report->id)->exists())->toBeTrue();

    $this->withSession(nrSession($adviser))->get(route('adviser.semester-reports.show', $report))
        ->assertDontSee('AI Unavailable — Manual Draft Required');
});

it('P7: redrafting one activity keeps the edits made to the others', function () {
    Storage::fake('local');
    nrFakeGemini(nrSections(['introduction' => 'A fresh draft of the workshop.']));
    [$club, $adviser, $officer] = nrClub();
    $meeting  = nrActivity($club, $officer);
    $workshop = nrActivity($club, $officer, [
        'title' => 'Web Development Workshop', 'activity_type' => 'campus_resource_use',
        'approval_status' => 'approved', 'date' => today()->subDays(20)->toDateString(),
    ]);
    $report = nrGenerate($club, nrSemester(endedDaysAgo: 1));

    $this->withSession(nrSession($adviser))->patch(route('adviser.semester-reports.redraft', [$report, $workshop->id]), [
        'sections' => [
            $meeting->id  => ['introduction' => 'The adviser rewrote the meeting.'],
            $workshop->id => ['introduction' => 'Typed but about to be replaced.'],
        ],
    ]);

    $report->refresh();

    expect($report->section($meeting->id, 'introduction'))->toBe('The adviser rewrote the meeting.')
        ->and($report->section($workshop->id, 'introduction'))->toBe('A fresh draft of the workshop.');
});

it('P7: a redraft the AI cannot answer changes nothing in the activity', function () {
    Storage::fake('local');
    Sleep::fake();
    nrGeminiSequence([[nrSectionsAnswer(), 200], [[], 400]]);
    [$club, $adviser, $officer] = nrClub();
    $activity = nrActivity($club, $officer);
    $report   = nrGenerate($club, nrSemester(endedDaysAgo: 1));

    $this->withSession(nrSession($adviser))
        ->patch(route('adviser.semester-reports.redraft', [$report, $activity->id]), ['sections' => []])
        ->assertSessionHasErrors('status');

    expect($report->fresh()->section($activity->id, 'introduction'))->toBe('The activity was held as recorded.');
});

it('P7: redraft is refused for a submitted report, another club, an activity outside the report and a legacy report', function () {
    [$report, $club, $adviser, $officer, , $activity] = nrSubmittedReport();
    [, $otherAdviser] = nrClub('Another Club');

    $this->withSession(nrSession($adviser))->patch(route('adviser.semester-reports.redraft', [$report, $activity->id]))
        ->assertSessionHasErrors('status');
    $this->withSession(nrSession($otherAdviser))->patch(route('adviser.semester-reports.redraft', [$report, $activity->id]))
        ->assertForbidden();

    $report->update(['status' => 'pending_review']);
    $outside = nrActivity($club, $officer, ['title' => 'Not in the report', 'date' => today()->addDays(5)->toDateString()]);
    $this->withSession(nrSession($adviser))->patch(route('adviser.semester-reports.redraft', [$report, $outside->id]))
        ->assertNotFound();

    [$legacyClub, $legacyAdviser] = nrClub('Legacy Club');
    $legacy = nrLegacyReport($legacyClub, nrSemester(endedDaysAgo: 400), 'A single narrative.');
    $this->withSession(nrSession($legacyAdviser))->patch(route('adviser.semester-reports.redraft', [$legacy, $activity->id]))
        ->assertNotFound();
});

// ── Procedure 8 — when Gemini is down: outage memory, template drafts, catching up ──

/** Gemini answers 503 until it has answered three times: one request's retries, all failed. */
function nrGeminiDown(): void
{
    Sleep::fake();
    nrGeminiSequence([[[], 503], [[], 503], [[], 503]]);
}

it('P8: the API key travels in a header, never in the URL', function () {
    nrFakeGemini('Members are invited.');

    app(GeminiService::class)->draftAnnouncement('General Assembly', 'announcement', 'BYTE Org');

    Http::assertSent(fn ($request) => ! str_contains($request->url(), 'key=')
        && $request->hasHeader('x-goog-api-key', 'test-key'));
});

it('P8: an unavailable Gemini is remembered, so the next AI request falls back at once', function () {
    nrGeminiDown();
    $gemini = app(GeminiService::class);

    expect($gemini->draftAnnouncement('General Assembly', 'announcement', 'BYTE Org'))->toBeNull()
        ->and($gemini->isReady())->toBeFalse()
        ->and($gemini->draftAnnouncement('General Assembly', 'announcement', 'BYTE Org'))->toBeNull()
        ->and(Http::recorded())->toHaveCount(3);

    $this->travel(GeminiService::OUTAGE_SECONDS + 1)->seconds();

    expect($gemini->isReady())->toBeTrue();
});

it('P8: a lost connection is remembered as an outage too', function () {
    Sleep::fake();
    config(['gemini.api_key' => 'test-key']);
    Http::fake(['*generativelanguage*' => Http::failedConnection('cURL error 28: Operation timed out')]);

    expect(app(GeminiService::class)->draftAnnouncement('General Assembly', 'announcement', 'BYTE Org'))->toBeNull()
        ->and(app(GeminiService::class)->isReady())->toBeFalse();
});

it('P8: one request Gemini cannot handle does not switch the AI off for everyone', function (int $status) {
    Sleep::fake();
    nrGeminiSequence([[[], $status], [[], $status], [[], $status]]);

    expect(app(GeminiService::class)->draftAnnouncement('General Assembly', 'announcement', 'BYTE Org'))->toBeNull()
        ->and(app(GeminiService::class)->isReady())->toBeTrue();
})->with([500, 400]);

it('P8: an activity the AI cannot draft starts from a template of its records', function () {
    config(['gemini.api_key' => '']);
    Http::fake();
    [$club, , $officer] = nrClub();
    $activity = nrActivity($club, $officer);
    nrCheckIn($activity, 11);

    $report = nrGenerate($club, nrSemester(endedDaysAgo: 1));
    $id     = $activity->id;

    expect($report->draftSource($id))->toBe('template')
        ->and($report->ai_sections[$id]['source'])->toBe('template')
        ->and($report->ai_available)->toBeFalse()
        ->and($report->section($id, 'introduction'))
            ->toStartWith('"BYTE Org 1st Semester General Assembly" (Internal Meeting) was held on ')
            ->toContain('at AVR.')
            ->toContain("The officers' proposal described it as follows: The opening general assembly where officers present the semester plan.")
            ->toContain('Of the 13 expected participants, 11 attended, a turnout of 84.6%')
        ->and($report->section($id, 'rationale'))
            ->toBe('According to its proposal, the activity was organised for the following purpose: To orient members on the semester plan and ratify the committee heads.')
        ->and($report->section($id, 'minutes'))
            ->toContain('Attendance: 11 of 13 expected participants')
            ->toContain('Proceedings: Not recorded in ClubSync. See the attached minutes.')
        ->and($report->section($id, 'recommendations'))
            ->toStartWith('- ')
            ->toContain('Attach documentation photos of the activity to the report.');
    Http::assertNothingSent();
});

it('P8: a template draft says only what the records say, so the review checks pass', function () {
    config(['gemini.api_key' => '']);
    Http::fake();
    [$club, , $officer] = nrClub();
    $meeting = nrActivity($club, $officer, ['post_report_content' => 'Members elected four committee heads.']);
    nrCheckIn($meeting, 5);
    $workshop = nrActivity($club, $officer, [
        'title' => 'Web Development Workshop', 'activity_type' => 'campus_resource_use', 'budget_amount' => 1500,
        'approval_status' => 'approved', 'date' => today()->subDays(20)->toDateString(),
    ]);

    $report = nrGenerate($club, nrSemester(endedDaysAgo: 1));

    expect($report->reviewWarnings())->toBe([])
        ->and($report->section($meeting->id, 'minutes'))->toContain('Proceedings: Members elected four committee heads.')
        ->and($report->section($meeting->id, 'introduction'))->toContain("The officers' post-activity report states: Members elected four committee heads.")
        ->and($report->section($workshop->id, 'introduction'))->toContain('Attendance was not recorded in ClubSync for this activity.')
        ->and($report->section($workshop->id, 'minutes'))->toBe('')
        ->and($report->section($workshop->id, 'recommendations'))
            ->toContain('Attach the receipts for the PHP 1,500.00 budget released for this activity.');
});

it('P8: the adviser is told the activities start from a template, and the page flags them', function () {
    config(['gemini.api_key' => '']);
    Http::fake();
    [$club, $adviser, $officer] = nrClub();
    nrActivity($club, $officer);

    $report = nrGenerate($club, nrSemester(endedDaysAgo: 1));

    expect(ClubNotification::where('recipient_id', $adviser->id)->where('title', 'Semester narrative report ready for review')
        ->where('body', 'like', '%AI Unavailable — Manual Draft Required%')
        ->where('body', 'like', '%starting template%')->exists())->toBeTrue()
        ->and($report->sectionsEdited())->toBeFalse();

    $this->withSession(nrSession($adviser))->get(route('adviser.semester-reports.show', $report))
        ->assertOk()->assertSee('Template draft')->assertSee('They start')->assertSee('Fill from records');
});

it('P8: a Gemini outage partway through a report stops asking and templates the rest at once', function () {
    nrGeminiDown();
    [$club, , $officer] = nrClub();
    $first  = nrActivity($club, $officer);
    $second = nrActivity($club, $officer, ['title' => 'Planning Meeting', 'date' => today()->subDays(20)->toDateString()]);

    $report = nrGenerate($club, nrSemester(endedDaysAgo: 1));

    expect(Http::recorded())->toHaveCount(3)
        ->and($report->draftSource($first->id))->toBe('template')
        ->and($report->draftSource($second->id))->toBe('template');
});

it('P8: once Gemini is back, ai:catch-up has the AI draft the template activities left unchanged', function () {
    Sleep::fake();
    nrGeminiSequence([[[], 503], [[], 503], [[], 503], [nrSectionsAnswer(['introduction' => 'Drafted once the AI was back.']), 200]]);
    [$club, $adviser, $officer] = nrClub();
    $activity = nrActivity($club, $officer);
    $report   = nrGenerate($club, nrSemester(endedDaysAgo: 1));

    // Still inside the remembered outage: nothing is sent.
    $this->artisan('ai:catch-up')->assertSuccessful();
    expect(Http::recorded())->toHaveCount(3);

    $this->travel(GeminiService::OUTAGE_SECONDS + 1)->seconds();
    $this->artisan('ai:catch-up')->assertSuccessful();
    $report->refresh();

    $audit = AuditLog::where('action', 'ai.semester_report.section_redrafted')->where('resource_id', $report->id)->first();

    expect(Http::recorded())->toHaveCount(4)
        ->and($report->draftSource($activity->id))->toBe('ai')
        ->and($report->section($activity->id, 'introduction'))->toBe('Drafted once the AI was back.')
        ->and($report->ai_available)->toBeTrue()
        ->and($report->status)->toBe('pending_review')
        ->and($audit?->metadata['automatic'] ?? null)->toBeTrue()
        ->and(ClubNotification::where('recipient_id', $adviser->id)->where('title', 'AI drafts added to your semester report')
            ->where('body', 'like', '%BYTE Org 1st Semester General Assembly%')->exists())->toBeTrue();
});

it('P8: ai:catch-up leaves alone a template activity the adviser has edited', function () {
    config(['gemini.api_key' => '']);
    // No Http::fake() yet: its catch-all would answer before nrFakeGemini() below.
    [$club, $adviser, $officer] = nrClub();
    $edited    = nrActivity($club, $officer);
    $untouched = nrActivity($club, $officer, ['title' => 'Planning Meeting', 'date' => today()->subDays(20)->toDateString()]);
    $report    = nrGenerate($club, nrSemester(endedDaysAgo: 1));

    $this->withSession(nrSession($adviser))->patch(route('adviser.semester-reports.update', $report), [
        'sections' => [$edited->id => ['introduction' => 'The adviser wrote this one.']], 'intent' => 'save',
    ]);

    nrFakeGemini(nrSections(['introduction' => 'An AI draft.']));
    $this->artisan('ai:catch-up')->assertSuccessful();
    $report->refresh();

    expect($report->section($edited->id, 'introduction'))->toBe('The adviser wrote this one.')
        ->and($report->draftSource($edited->id))->toBe('template')
        ->and($report->section($untouched->id, 'introduction'))->toBe('An AI draft.')
        ->and(Http::recorded())->toHaveCount(1);
});

it('P8: ai:catch-up never changes a submitted report', function () {
    Storage::fake('local');
    config(['gemini.api_key' => '']);
    // No Http::fake() yet: its catch-all would answer before nrFakeGemini() below.
    [$club, $adviser, $officer] = nrClub();
    $activity = nrActivity($club, $officer);
    $report   = nrGenerate($club, nrSemester(endedDaysAgo: 1));

    // The template's introduction states the attendance, so it can be submitted as it is.
    $this->withSession(nrSession($adviser))->patch(route('adviser.semester-reports.update', $report), [
        'sections' => $report->sections, 'intent' => 'submit',
    ]);
    $before = $report->fresh()->sections;

    nrFakeGemini(nrSections());
    $this->artisan('ai:catch-up')->assertSuccessful();

    expect($report->fresh()->status)->toBe('submitted')
        ->and($report->fresh()->sections)->toBe($before);
    Http::assertNothingSent();
});

it('P8: the adviser fills an activity in from its records, with no AI at all', function () {
    config(['gemini.api_key' => '']);
    Http::fake();
    [$club, $adviser, $officer] = nrClub();
    $activity = nrActivity($club, $officer);
    $report   = nrSectionReport($club, nrSemester(endedDaysAgo: 1));

    $this->withSession(nrSession($adviser))->get(route('adviser.semester-reports.show', $report))
        ->assertSee('Manual draft required')->assertSee('Fill from records')->assertDontSee('Draft with AI');

    $this->withSession(nrSession($adviser))
        ->patch(route('adviser.semester-reports.template', [$report, $activity->id]), ['sections' => []])
        ->assertRedirect(route('adviser.semester-reports.show', $report) . "#activity-{$activity->id}")
        ->assertSessionHas('success');

    $report->refresh();

    expect($report->draftSource($activity->id))->toBe('template')
        ->and($report->section($activity->id, 'introduction'))->toStartWith('"BYTE Org 1st Semester General Assembly"')
        ->and(AuditLog::where('action', 'semester_report.section_templated')->where('resource_id', $report->id)->exists())->toBeTrue();
    Http::assertNothingSent();
});

it('P8: filling from records keeps an AI draft as the first draft, so it is not redrafted behind the adviser', function () {
    nrFakeGemini(nrSections(['introduction' => 'The AI draft.']));
    [$club, $adviser, $officer] = nrClub();
    $activity = nrActivity($club, $officer);
    $report   = nrGenerate($club, nrSemester(endedDaysAgo: 1));

    $this->withSession(nrSession($adviser))
        ->patch(route('adviser.semester-reports.template', [$report, $activity->id]), ['sections' => []]);
    $report->refresh();

    expect($report->draftSource($activity->id))->toBe('ai')
        ->and($report->ai_sections[$activity->id]['introduction'])->toBe('The AI draft.')
        ->and($report->section($activity->id, 'introduction'))->toStartWith('"BYTE Org 1st Semester General Assembly"')
        ->and($report->sectionsEdited())->toBeTrue();
});

it('P8: only the club adviser fills a report in from its records, and only while it is a draft', function () {
    Storage::fake('local');
    config(['gemini.api_key' => '']);
    Http::fake();
    [$club, $adviser, $officer] = nrClub();
    [, $otherAdviser] = nrClub('Another Club');
    $activity = nrActivity($club, $officer);
    $report   = nrGenerate($club, nrSemester(endedDaysAgo: 1));

    $this->withSession(nrSession($otherAdviser))->patch(route('adviser.semester-reports.template', [$report, $activity->id]))
        ->assertForbidden();

    $report->update(['status' => 'submitted']);
    $this->withSession(nrSession($adviser))->patch(route('adviser.semester-reports.template', [$report, $activity->id]))
        ->assertSessionHasErrors('status');
});

it('P8: during an outage the review page offers no AI button', function () {
    nrGeminiDown();
    [$club, $adviser, $officer] = nrClub();
    nrActivity($club, $officer);
    $report = nrGenerate($club, nrSemester(endedDaysAgo: 1));

    $this->withSession(nrSession($adviser))->get(route('adviser.semester-reports.show', $report))
        ->assertOk()->assertDontSee('Draft with AI')->assertSee('the AI is unavailable right now');
});

it('P8: ai:catch-up is scheduled every 15 minutes', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains((string) $event->command, 'ai:catch-up'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('*/15 * * * *');
});
