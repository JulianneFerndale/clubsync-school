<?php

use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubNotification;
use App\Models\ClubOfficer;
use App\Models\ClubReportSubmission;
use App\Models\ResourceDocument;
use App\Models\Semester;
use App\Models\User;
use Database\Seeders\ReportTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * The five semester-end reports clubs file with the DSA, from the templates in
 * docs/resources-dsa. Every officer of the club sees them; only the club's
 * president submits; the DSA accepts or returns them with remarks.
 */

beforeEach(fn () => Storage::fake('local'));

function crUser(string $role): User
{
    static $n = 0;
    $n++;

    return User::create([
        'first_name'   => 'Report',
        'last_name'    => "User{$n}",
        'edp_number'   => str_pad((string) (9300 + $n), 10, '0', STR_PAD_LEFT),
        'email'        => "report{$n}@sccpag.edu.ph",
        'password'     => Hash::make('Password1!'),
        'role'         => $role,
        'course_id'    => testCourse()->id,
        'is_admin'     => false,
        'is_suspended' => false,
    ]);
}

function crSession(User $user): array
{
    $header  = rtrim(strtr(base64_encode(json_encode(['alg' => 'none'])), '+/', '-_'), '=');
    $payload = rtrim(strtr(base64_encode(json_encode(['exp' => time() + 3600])), '+/', '-_'), '=');

    return [
        'firebase_id_token'  => "{$header}.{$payload}.signature",
        'firebase_uid'       => 'report-uid-' . $user->id,
        'firebase_user_role' => $user->role,
        'firebase_user_id'   => $user->id,
        'firebase_is_admin'  => false,
    ];
}

function crClub(): Club
{
    static $n = 0;
    $n++;

    return Club::create([
        'slug'            => 'report-club-' . $n,
        'acronym'         => 'RC' . $n,
        'club_type'       => 'Academic',
        'type'            => 'academic',
        'course_slug'     => 'all',
        'department_slug' => 'all',
        'name'            => "Report Club {$n}",
        'is_active'       => true,
    ]);
}

function crOfficer(Club $club, string $position): User
{
    $user = crUser($position);
    ClubOfficer::create(['club_id' => $club->id, 'user_id' => $user->id, 'position' => $position]);

    return $user;
}

function crSemester(): Semester
{
    return Semester::create([
        'label'      => '1st Semester 2026-2027',
        'start_date' => now()->subDays(30)->toDateString(),
        'end_date'   => now()->addDays(90)->toDateString(),
        'is_current' => true,
    ]);
}

function crFile(string $name = 'accomplishment-report.docx'): UploadedFile
{
    return UploadedFile::fake()->create($name, 200, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
}

/** The president submits one report and gets the row back. */
function crSubmit(User $president, string $type = 'accomplishment_report'): ClubReportSubmission
{
    test()->withSession(crSession($president))
        ->post(route('officer.club-reports.submit', $type), ['file' => crFile()])
        ->assertSessionHasNoErrors();

    return ClubReportSubmission::where('report_type', $type)->latest('id')->firstOrFail();
}

// ── Templates ────────────────────────────────────────────────────────────────

it('publishes the five DSA templates from docs/resources-dsa', function () {
    $this->seed(ReportTemplateSeeder::class);

    $templates = ResourceDocument::whereNotNull('report_type')->get();

    expect($templates)->toHaveCount(5)
        ->and($templates->pluck('report_type')->sort()->values()->all())
            ->toBe(collect(array_keys(ClubReportSubmission::TYPES))->sort()->values()->all())
        ->and($templates->every(fn ($t) => $t->category === 'template'))->toBeTrue()
        ->and($templates->every(fn ($t) => ! str_starts_with($t->file_name, 'Copy of')))->toBeTrue();

    $templates->each(fn ($t) => Storage::disk('local')->assertExists($t->file_path));
});

it('is safe to run the template seeder again', function () {
    $this->seed(ReportTemplateSeeder::class);
    $this->seed(ReportTemplateSeeder::class);

    expect(ResourceDocument::whereNotNull('report_type')->count())->toBe(5);
});

it('puts back a template file that went missing from storage', function () {
    $this->seed(ReportTemplateSeeder::class);
    $template = ResourceDocument::firstWhere('report_type', 'financial_report');
    Storage::disk('local')->delete($template->file_path);

    $this->seed(ReportTemplateSeeder::class);

    Storage::disk('local')->assertExists($template->fresh()->file_path);
});

it('leaves a template the admin replaced alone', function () {
    $this->seed(ReportTemplateSeeder::class);
    $template = ResourceDocument::firstWhere('report_type', 'minutes_of_meetings');
    Storage::disk('local')->put('resources/admin-upload.docx', 'revised');
    $template->update(['file_path' => 'resources/admin-upload.docx', 'file_name' => 'Minutes v2.docx']);

    $this->seed(ReportTemplateSeeder::class);

    expect($template->fresh()->file_name)->toBe('Minutes v2.docx');
});

// ── Officer side ─────────────────────────────────────────────────────────────

it('shows every officer the reports due and the templates', function () {
    $this->seed(ReportTemplateSeeder::class);
    crSemester();
    $club = crClub();
    $treasurer = crOfficer($club, 'treasurer');

    $this->withSession(crSession($treasurer))->get(route('officer.club-reports.index'))
        ->assertOk()
        ->assertSee('Minutes of Meetings')
        ->assertSee('Financial Transactions &amp; Liquidation Report', false)
        ->assertSee('Download Template')
        ->assertSee('Only the club president submits')
        ->assertDontSee('Submit to DSA');
});

it('lets the club president submit a report and tells the DSA', function () {
    $semester  = crSemester();
    $club      = crClub();
    $president = crOfficer($club, 'president');
    $dsa       = crUser('dsa');

    $this->withSession(crSession($president))
        ->post(route('officer.club-reports.submit', 'accomplishment_report'), ['file' => crFile()])
        ->assertRedirect(route('officer.club-reports.index'));

    $submission = ClubReportSubmission::firstOrFail();

    expect($submission->club_id)->toBe($club->id)
        ->and($submission->semester_id)->toBe($semester->id)
        ->and($submission->status)->toBe('submitted')
        ->and($submission->submitted_by)->toBe($president->id);
    Storage::disk('local')->assertExists($submission->file_path);

    expect(AuditLog::where('action', 'club_report.submitted')->where('resource_id', $submission->id)->exists())->toBeTrue();

    $notice = ClubNotification::where('recipient_id', $dsa->id)->first();
    expect($notice)->not->toBeNull()
        ->and($notice->body)->toContain($club->name)
        ->and($notice->body)->toContain('Accomplishment & Documentation Report');
});

it('refuses a submission from any officer other than the president', function (string $position) {
    crSemester();
    $club    = crClub();
    $officer = crOfficer($club, $position);

    $this->withSession(crSession($officer))
        ->post(route('officer.club-reports.submit', 'financial_report'), ['file' => crFile()])
        ->assertForbidden();

    expect(ClubReportSubmission::count())->toBe(0);
})->with(['treasurer', 'mmo']);

it('refuses a submission from a member', function () {
    crSemester();
    $member = crUser('member');

    $this->withSession(crSession($member))
        ->post(route('officer.club-reports.submit', 'financial_report'), ['file' => crFile()])
        ->assertForbidden();
});

it('holds a president to their own club seat, not a post held elsewhere', function () {
    crSemester();
    $clubA = crClub();
    $clubB = crClub();
    $user  = crOfficer($clubA, 'treasurer');
    ClubOfficer::create(['club_id' => $clubB->id, 'user_id' => $user->id, 'position' => 'president']);

    crSubmit($user);

    expect(ClubReportSubmission::firstOrFail()->club_id)->toBe($clubB->id);
});

it('refuses an unknown report type', function () {
    crSemester();
    $president = crOfficer(crClub(), 'president');

    $this->withSession(crSession($president))
        ->post(route('officer.club-reports.submit', 'not_a_report'), ['file' => crFile()])
        ->assertNotFound();
});

it('refuses a submission when no semester is open', function () {
    $president = crOfficer(crClub(), 'president');

    $this->withSession(crSession($president))
        ->post(route('officer.club-reports.submit', 'accomplishment_report'), ['file' => crFile()])
        ->assertSessionHasErrors('report');

    expect(ClubReportSubmission::count())->toBe(0);
});

it('only accepts document files', function () {
    crSemester();
    $president = crOfficer(crClub(), 'president');

    $this->withSession(crSession($president))
        ->post(route('officer.club-reports.submit', 'accomplishment_report'), [
            'file' => UploadedFile::fake()->create('photo.png', 50, 'image/png'),
        ])
        ->assertSessionHasErrors('file');
});

it('replaces the file on resubmission, keeping one copy per report', function () {
    crSemester();
    $president = crOfficer(crClub(), 'president');
    $first     = crSubmit($president);
    $oldPath   = $first->file_path;

    $this->withSession(crSession($president))
        ->post(route('officer.club-reports.submit', 'accomplishment_report'), ['file' => crFile('revised.docx')])
        ->assertSessionHasNoErrors();

    expect(ClubReportSubmission::count())->toBe(1)
        ->and($first->fresh()->file_name)->toBe('revised.docx');
    Storage::disk('local')->assertMissing($oldPath);
});

it('lets officers download only their own club\'s reports', function () {
    crSemester();
    $club       = crClub();
    $president  = crOfficer($club, 'president');
    $treasurer  = crOfficer($club, 'treasurer');
    $outsider   = crOfficer(crClub(), 'president');
    $submission = crSubmit($president);

    $this->withSession(crSession($treasurer))
        ->get(route('officer.club-reports.download', $submission))
        ->assertOk()
        ->assertDownload("{$club->acronym} - Accomplishment and Documentation Report - 1st Semester 2026-2027.docx");

    $this->withSession(crSession($outsider))
        ->get(route('officer.club-reports.download', $submission))
        ->assertNotFound();
});

// ── DSA review ───────────────────────────────────────────────────────────────

it('lists submissions for the DSA with the clubs still missing reports', function () {
    crSemester();
    $club      = crClub();
    $president = crOfficer($club, 'president');
    crSubmit($president);
    crClub(); // filed nothing
    $dsa = crUser('dsa');

    $this->withSession(crSession($dsa))->get(route('dsa.club-reports.index'))
        ->assertOk()
        ->assertSee($club->name)
        ->assertSee('Awaiting DSA review')
        ->assertSee('Clubs that have not filed all 5 reports');
});

it('keeps the DSA review page away from officers', function () {
    $president = crOfficer(crClub(), 'president');

    $this->withSession(crSession($president))->get(route('dsa.club-reports.index'))->assertForbidden();
});

it('lets the DSA accept a report, which locks it', function () {
    crSemester();
    $club       = crClub();
    $president  = crOfficer($club, 'president');
    $treasurer  = crOfficer($club, 'treasurer');
    $submission = crSubmit($president);
    $dsa        = crUser('dsa');

    $this->withSession(crSession($dsa))
        ->post(route('dsa.club-reports.accept', $submission))
        ->assertSessionHasNoErrors();

    $submission->refresh();
    expect($submission->status)->toBe('accepted')
        ->and($submission->reviewed_by)->toBe($dsa->id)
        ->and(AuditLog::where('action', 'club_report.accepted')->exists())->toBeTrue()
        // Every officer hears the outcome, not only the one who submitted.
        ->and(ClubNotification::where('recipient_id', $treasurer->id)->where('title', 'Report accepted by the DSA')->exists())->toBeTrue();

    $this->withSession(crSession($president))
        ->post(route('officer.club-reports.submit', 'accomplishment_report'), ['file' => crFile()])
        ->assertSessionHasErrors('report');
});

it('requires remarks to return a report, and shows them to the club', function () {
    crSemester();
    $club       = crClub();
    $president  = crOfficer($club, 'president');
    $submission = crSubmit($president);
    $dsa        = crUser('dsa');

    $this->withSession(crSession($dsa))
        ->post(route('dsa.club-reports.return', $submission), ['remarks' => ''])
        ->assertSessionHasErrors('remarks');

    $this->withSession(crSession($dsa))
        ->post(route('dsa.club-reports.return', $submission), ['remarks' => 'Annex B attendance sheet is missing.'])
        ->assertSessionHasNoErrors();

    expect($submission->fresh()->status)->toBe('returned');

    $this->withSession(crSession($president))->get(route('officer.club-reports.index'))
        ->assertSee('Annex B attendance sheet is missing.')
        ->assertSee('Resubmit');

    // Resubmitting clears the review and puts it back in the DSA's queue.
    crSubmit($president);
    $submission->refresh();
    expect($submission->status)->toBe('submitted')
        ->and($submission->review_remarks)->toBeNull()
        ->and(AuditLog::where('action', 'club_report.resubmitted')->exists())->toBeTrue();
});

it('will not review a report twice', function () {
    crSemester();
    $submission = crSubmit(crOfficer(crClub(), 'president'));
    $dsa        = crUser('dsa');

    $this->withSession(crSession($dsa))->post(route('dsa.club-reports.accept', $submission));

    $this->withSession(crSession($dsa))
        ->post(route('dsa.club-reports.return', $submission), ['remarks' => 'Too late'])
        ->assertSessionHasErrors('review');

    expect($submission->fresh()->status)->toBe('accepted');
});
