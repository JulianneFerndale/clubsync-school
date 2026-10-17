<?php

use App\Models\ActivityPhoto;
use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubActivity;
use App\Models\ClubOfficer;
use App\Models\Fee;
use App\Models\FeePayment;
use App\Models\Semester;
use App\Models\SemesterNarrativeReport;
use App\Models\User;
use App\Services\ActivityPhotoStorage;
use App\Services\ClubDeletion;
use App\Services\FirebaseService;
use App\Services\NarrativeDocuments\Document;
use App\Services\NarrativeDocuments\DocumentBuilder;
use App\Services\NarrativeReportData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * Report photos (client revision, 2026-10-01): officers attach photos to an activity,
 * especially once a meeting is over. They are filed in the reporting folder, recorded
 * in activity_photos, and pasted into the semester-end documents — publication
 * materials into the Communication Letters & Publication Materials report, receipts
 * into the liquidation, properties into the inventory, and so on.
 */

beforeEach(fn () => Storage::fake('local'));

function apUser(string $role): User
{
    static $n = 0;
    $n++;

    return User::create([
        'first_name' => 'Ap', 'last_name' => "Tester{$n}", 'edp_number' => str_pad((string) (700 + $n), 10, '0', STR_PAD_LEFT),
        'email' => "ap{$n}@sccpag.edu.ph", 'password' => Hash::make('Password1!'), 'role' => $role,
        'course_id' => testCourse()->id, 'is_admin' => false, 'is_suspended' => false,
    ]);
}

/** [club, adviser, president, mmo] */
function apClub(): array
{
    static $n = 0;
    $n++;

    $adviser = apUser('adviser');
    $club    = Club::create([
        'slug' => "ap-club-{$n}", 'acronym' => "AP{$n}", 'club_type' => 'Non-Academic', 'type' => 'non_academic',
        'course_slug' => 'all', 'department_slug' => 'all', 'name' => "Chess Club {$n}", 'is_active' => true, 'adviser_id' => $adviser->id,
    ]);

    $president = apUser('president');
    $mmo       = apUser('mmo');
    ClubOfficer::create(['club_id' => $club->id, 'user_id' => $president->id, 'position' => 'president']);
    ClubOfficer::create(['club_id' => $club->id, 'user_id' => $mmo->id, 'position' => 'mmo']);

    return [$club, $adviser, $president, $mmo];
}

function apSession(User $user): array
{
    $header  = rtrim(strtr(base64_encode(json_encode(['alg' => 'none'])), '+/', '-_'), '=');
    $payload = rtrim(strtr(base64_encode(json_encode(['exp' => time() + 3600])), '+/', '-_'), '=');

    return [
        'firebase_id_token' => "{$header}.{$payload}.signature", 'firebase_uid' => 'uid-' . $user->id,
        'firebase_user_role' => $user->role, 'firebase_user_id' => $user->id, 'firebase_is_admin' => $user->is_admin,
    ];
}

function apSemester(): Semester
{
    return Semester::create([
        'label' => '1st Semester, A.Y. 2026-2027', 'term' => 'first', 'academic_year' => '2026-2027',
        'start_date' => today()->subDays(100)->toDateString(), 'end_date' => today()->addDays(20)->toDateString(), 'is_current' => true,
    ]);
}

function apActivity(Club $club, User $creator, array $overrides = []): ClubActivity
{
    return ClubActivity::create(array_merge([
        'club_id' => $club->id, 'title' => 'ACLE: Chess Strategy and Critical Thinking', 'description' => 'A lecture on opening principles.',
        'date' => today()->subDays(10)->toDateString(), 'time_start' => '13:00', 'time_end' => '15:00', 'venue' => 'AVR',
        'purpose' => 'To develop critical thinking.', 'expected_participants' => 3, 'activity_type' => 'acle',
        'approval_status' => 'approved', 'status' => 'completed', 'completed_at' => now()->subDays(10), 'created_by' => $creator->id,
    ], $overrides));
}

function apAttach(User $officer, ClubActivity $activity, array $fields, ?array $files = null)
{
    return test()->withSession(apSession($officer))->post(route('officer.activities.photos.store', $activity), array_merge([
        'photos' => $files ?? [UploadedFile::fake()->image('photo.jpg', 800, 600)],
    ], $fields));
}

/** A report for the semester, as the drafting job would leave it. */
function apReport(Club $club, Semester $semester, string $status = 'pending_review'): SemesterNarrativeReport
{
    $data     = NarrativeReportData::semester($club, $semester);
    $sections = collect($data['activities'])->mapWithKeys(fn ($a) => [$a['id'] => [
        'introduction' => "\"{$a['title']}\" was held.", 'rationale' => 'Planned for the members.', 'minutes' => '', 'recommendations' => '- Continue.',
    ]])->all();

    return SemesterNarrativeReport::create([
        'club_id' => $club->id, 'semester_id' => $semester->id, 'activity_data' => $data, 'ai_sections' => $sections, 'sections' => $sections,
        'ai_available' => true, 'status' => $status, 'submitted_at' => $status === 'submitted' ? now() : null,
    ]);
}

function apText(Document $document): string
{
    $lines = [];

    foreach ($document->blocks as $block) {
        $lines[] = match ($block['type']) {
            'paragraph' => implode('', array_column($block['runs'], 'text')),
            'table'     => implode("\n", array_map(fn ($row) => implode(' | ', $row), [...$block['rows'], ...($block['total'] ? [$block['total']] : [])])) ?: $block['empty'],
            'photos'    => $block['photos'] === [] ? $block['empty'] : 'PHOTOS: ' . implode(' | ', array_map(fn ($p) => $p['caption'] ?: '(photo)', $block['photos'])),
            'cover'     => $block['title'] . "\n" . implode("\n", array_map(fn ($runs) => implode('', array_column($runs, 'text')), $block['lines'])),
            default     => '',
        };
    }

    return implode("\n", $lines);
}

/** A JPEG carrying an EXIF block with a GPS marker, as a phone would save it. */
function apJpegWithExif(): UploadedFile
{
    $image = imagecreatetruecolor(2400, 1800);
    imagefill($image, 0, 0, imagecolorallocate($image, 30, 120, 60));
    ob_start();
    imagejpeg($image, null, 90);
    $jpeg = (string) ob_get_clean();

    $exif = "Exif\0\0" . "MM\0\x2A\0\0\0\x08" . 'GPSLatitude 7.8257N GPSLongitude 123.4370E';
    $app1 = "\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif;

    $path = tempnam(sys_get_temp_dir(), 'exif') . '.jpg';
    file_put_contents($path, substr($jpeg, 0, 2) . $app1 . substr($jpeg, 2));

    return new UploadedFile($path, 'IMG_20261010.jpg', 'image/jpeg', null, true);
}

// ── Attaching ────────────────────────────────────────────────────────────────

it('files an officer\'s photos in the reporting folder and records them', function () {
    [$club, , $president] = apClub();
    $activity = apActivity($club, $president);

    apAttach($president, $activity, ['category' => 'documentation', 'caption' => 'Opening lecture'], [
        UploadedFile::fake()->image('a.jpg', 800, 600), UploadedFile::fake()->image('b.png', 600, 800),
    ])->assertRedirect(route('officer.activities.show', $activity) . '#report-photos');

    $photos = ActivityPhoto::where('club_activity_id', $activity->id)->get();

    expect($photos)->toHaveCount(2)
        ->and($photos->pluck('category')->unique()->all())->toBe(['documentation'])
        ->and($photos->first()->caption)->toBe('Opening lecture')
        ->and($photos->first()->uploaded_by)->toBe($president->id)
        ->and($photos->first()->path)->toStartWith("report-photos/{$club->id}/{$activity->id}/")->toEndWith('.jpg')
        ->and(Storage::disk('local')->exists($photos->first()->path))->toBeTrue()
        ->and(AuditLog::where('action', 'activity.photo.attached')->count())->toBe(2);
});

it('lets the mass media officer attach publication materials', function () {
    [$club, , $president, $mmo] = apClub();
    $activity = apActivity($club, $president);

    apAttach($mmo, $activity, ['category' => 'publication', 'caption' => 'ACLE poster'])->assertRedirect();

    expect(ActivityPhoto::where('category', 'publication')->first()->uploaded_by)->toBe($mmo->id);
});

it('shrinks every photo and drops its camera metadata, GPS included', function () {
    [$club, , $president] = apClub();
    $activity = apActivity($club, $president);

    apAttach($president, $activity, ['category' => 'documentation'], [apJpegWithExif()])->assertRedirect();

    $photo = ActivityPhoto::first();
    $bytes = Storage::disk('local')->get($photo->path);

    expect($photo->width)->toBe(1600)
        ->and($photo->height)->toBe(1200)
        ->and($bytes)->not->toContain('Exif')
        ->and($bytes)->not->toContain('GPSLatitude');
});

it('requires the item and amount of a receipt, and the name and quantity of a property', function () {
    [$club, , $president] = apClub();
    $activity = apActivity($club, $president);

    apAttach($president, $activity, ['category' => 'receipt'])->assertSessionHasErrors(['caption', 'amount']);
    apAttach($president, $activity, ['category' => 'property'])->assertSessionHasErrors(['caption', 'quantity']);

    apAttach($president, $activity, ['category' => 'receipt', 'caption' => 'Tarpaulin', 'amount' => '450.50', 'reference_number' => '00123'])->assertSessionHasNoErrors();
    apAttach($president, $activity, ['category' => 'property', 'caption' => 'Chess sets', 'quantity' => 8, 'remarks' => 'Complete'])->assertSessionHasNoErrors();

    expect(ActivityPhoto::where('category', 'receipt')->first())
        ->amount->toBe('450.50')->reference_number->toBe('00123')
        ->and(ActivityPhoto::where('category', 'property')->first())
        ->quantity->toBe(8)->remarks->toBe('Complete');
});

it('takes one receipt per upload, and keeps figures off categories that do not print them', function () {
    [$club, , $president] = apClub();
    $activity = apActivity($club, $president);

    apAttach($president, $activity, ['category' => 'receipt', 'caption' => 'Two receipts', 'amount' => 10], [
        UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg'),
    ])->assertSessionHasErrors('photos');

    apAttach($president, $activity, ['category' => 'documentation', 'amount' => 999, 'quantity' => 5])->assertSessionHasNoErrors();

    expect(ActivityPhoto::first())->amount->toBeNull()->quantity->toBeNull();
});

it('accepts images only', function () {
    [$club, , $president] = apClub();
    $activity = apActivity($club, $president);

    apAttach($president, $activity, ['category' => 'letter'], [UploadedFile::fake()->create('letter.pdf', 50, 'application/pdf')])
        ->assertSessionHasErrors('photos.0');

    expect(ActivityPhoto::count())->toBe(0);
});

it('refuses officers of another club and members', function () {
    [$club, , $president] = apClub();
    [, , $otherPresident] = apClub();
    $activity = apActivity($club, $president);

    apAttach($otherPresident, $activity, ['category' => 'documentation'])->assertForbidden();
    apAttach(apUser('member'), $activity, ['category' => 'documentation'])->assertForbidden();

    expect(ActivityPhoto::count())->toBe(0);
});

it('serves a photo only to the club\'s officers, and only under its own activity', function () {
    [$club, , $president] = apClub();
    [, , $otherPresident] = apClub();
    $activity = apActivity($club, $president);
    $other    = apActivity($club, $president, ['title' => 'Another activity']);
    apAttach($president, $activity, ['category' => 'documentation'])->assertRedirect();
    $photo = ActivityPhoto::first();

    $this->withSession(apSession($president))->get(route('officer.activities.photos.show', [$activity, $photo]))
        ->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    $this->withSession(apSession($president))->get(route('officer.activities.photos.show', [$other, $photo]))->assertNotFound();
    $this->withSession(apSession($otherPresident))->get(route('officer.activities.photos.show', [$activity, $photo]))->assertForbidden();
});

it('removes a photo and its file', function () {
    [$club, , $president] = apClub();
    $activity = apActivity($club, $president);
    apAttach($president, $activity, ['category' => 'documentation'])->assertRedirect();
    $photo = ActivityPhoto::first();

    $this->withSession(apSession($president))->delete(route('officer.activities.photos.destroy', [$activity, $photo]))->assertRedirect();

    expect(ActivityPhoto::count())->toBe(0)
        ->and(Storage::disk('local')->exists($photo->path))->toBeFalse()
        ->and(AuditLog::where('action', 'activity.photo.removed')->exists())->toBeTrue();
});

it('records the budget released for an activity', function () {
    [$club, , $president] = apClub();
    $activity = apActivity($club, $president);

    $this->withSession(apSession($president))->patch(route('officer.activities.budget', $activity), ['budget_amount' => '1500'])->assertRedirect();

    expect($activity->fresh()->budget_amount)->toBe('1500.00')
        ->and(AuditLog::where('action', 'activity.budget.updated')->exists())->toBeTrue();
});

it('shows the photo panel on the activity page', function () {
    [$club, , $president] = apClub();
    $activity = apActivity($club, $president);
    apAttach($president, $activity, ['category' => 'receipt', 'caption' => 'Tarpaulin', 'amount' => 450])->assertRedirect();

    $this->withSession(apSession($president))->get(route('officer.activities.show', $activity))
        ->assertOk()
        ->assertSee('Semester Report Photos')
        ->assertSee('Attach to Semester Report')
        ->assertSee('Tarpaulin')
        ->assertSee('PHP 450.00');
});

// ── Fixed once the report has gone to the DSA ────────────────────────────────

it('keeps an activity\'s photos and budget as they were once its report is submitted', function () {
    [$club, , $president] = apClub();
    $semester = apSemester();
    $activity = apActivity($club, $president);
    apAttach($president, $activity, ['category' => 'documentation'])->assertRedirect();
    $photo = ActivityPhoto::first();
    apReport($club, $semester, 'submitted');

    apAttach($president, $activity, ['category' => 'documentation'])->assertSessionHasErrors('photos');
    $this->withSession(apSession($president))->delete(route('officer.activities.photos.destroy', [$activity, $photo]))->assertSessionHasErrors('photos');
    $this->withSession(apSession($president))->patch(route('officer.activities.budget', $activity), ['budget_amount' => 5])->assertSessionHasErrors('budget_amount');

    expect(ActivityPhoto::count())->toBe(1)
        ->and($activity->fresh()->budget_amount)->toBeNull();

    $this->withSession(apSession($president))->get(route('officer.activities.show', $activity))
        ->assertOk()->assertSee('these records are now fixed')->assertDontSee('Attach to Semester Report');
});

it('leaves out of a submitted report any photo added after it was submitted', function () {
    [$club, , $president] = apClub();
    $semester = apSemester();
    $activity = apActivity($club, $president);
    $report   = apReport($club, $semester, 'submitted');
    $report->update(['submitted_at' => now()->subHour()]);
    app(ActivityPhotoStorage::class)->store(UploadedFile::fake()->image('late.jpg'), $activity, ['category' => 'documentation', 'caption' => 'Late photo'], $president->id);

    expect(apText((new DocumentBuilder($report->fresh()))->build('accomplishment')))->not->toContain('Late photo');
});

// ── Pasted into the documents ────────────────────────────────────────────────

it('pastes each kind of photo into the document and section it belongs to', function () {
    [$club, , $president, $mmo] = apClub();
    $semester = apSemester();
    $acle     = apActivity($club, $president, ['budget_amount' => 1000]);
    $meeting  = apActivity($club, $president, ['title' => 'Tournament Planning Meeting', 'activity_type' => 'internal_meeting', 'approval_status' => 'no_approval_needed']);

    apAttach($president, $acle, ['category' => 'documentation', 'caption' => 'Members at the lecture'])->assertRedirect();
    apAttach($president, $acle, ['category' => 'program', 'caption' => 'Program flow'])->assertRedirect();
    apAttach($president, $acle, ['category' => 'letter', 'caption' => 'Letter for approval of the ACLE'])->assertRedirect();
    apAttach($mmo, $acle, ['category' => 'publication', 'caption' => 'ACLE poster on Facebook'])->assertRedirect();
    apAttach($president, $acle, ['category' => 'receipt', 'caption' => 'Tarpaulin', 'amount' => 700, 'reference_number' => 'OR-11'])->assertRedirect();
    apAttach($president, $acle, ['category' => 'receipt', 'caption' => 'Snacks', 'amount' => 450])->assertRedirect();
    apAttach($president, $acle, ['category' => 'property', 'caption' => 'Chess sets', 'quantity' => 8, 'remarks' => 'Complete'])->assertRedirect();
    apAttach($president, $meeting, ['category' => 'minutes', 'caption' => 'Minutes, page 1'])->assertRedirect();

    $builder = new DocumentBuilder(apReport($club, $semester));

    expect(apText($builder->build('accomplishment')))
        ->toContain('PHOTOS: Members at the lecture')
        ->toContain('PHOTOS: Program flow')
        ->toContain('Letter for approval of the ACLE')
        ->and(apText($builder->build('communications')))
        ->toContain('1. Letter for approval of the ACLE')
        ->toContain('1. ACLE poster on Facebook')
        ->toContain('Attached Photo of Information posted in Social Media')
        ->and(apText($builder->build('financial')))
        ->toContain('Activity 1: ACLE: Chess Strategy and Critical Thinking | Php 1,000.00 | Php 1,150.00 | Deficit Php 150.00')
        ->toContain('OR-11 | Tarpaulin | Php 700.00')
        ->toContain(' | TOTAL | Php 1,150.00')
        ->toContain('Tarpaulin — Php 700.00 (Receipt No. OR-11)')
        ->and(apText($builder->build('inventory')))
        ->toContain('Chess sets | 8 | Complete | ACLE: Chess Strategy and Critical Thinking')
        ->and(apText($builder->build('minutes')))
        ->toContain('1. Tournament Planning Meeting')
        ->toContain('PHOTOS: Minutes, page 1');
});

it('lists the semester\'s fee collections in the financial report', function () {
    [$club, , $president] = apClub();
    $semester = apSemester();
    apActivity($club, $president);
    $fee = Fee::create(['club_id' => $club->id, 'title' => 'Membership Fee', 'amount' => 50, 'due_date' => today()->subDays(5), 'academic_period' => '1st Semester 2026-2027']);
    foreach (['paid', 'paid', 'pending'] as $status) {
        FeePayment::create(['fee_id' => $fee->id, 'user_id' => apUser('member')->id, 'status' => $status]);
    }

    expect(apText((new DocumentBuilder(apReport($club, $semester)))->build('financial')))
        ->toContain('Collection of Membership Fee | Php 100.00 | — | Collected from 2 of 3 members at Php 50.00 each');
});

it('prints the signature lines with the officers on record and the MMO on the communications cover', function () {
    [$club, , $president, $mmo] = apClub();
    $semester = apSemester();
    apActivity($club, $president);
    $builder = new DocumentBuilder(apReport($club, $semester));

    expect(apText($builder->build('accomplishment')))->toContain("{$president->name}\n(Club President)")
        ->and(apText($builder->build('communications')))->toContain($mmo->name)->toContain('Mass Media Officer');
});

it('says plainly where a section has nothing attached, so it can be filled in the Word file', function () {
    [$club, , $president] = apClub();
    $semester = apSemester();
    apActivity($club, $president);
    $builder = new DocumentBuilder(apReport($club, $semester));

    expect(apText($builder->build('accomplishment')))->toContain('No photos were attached for this activity.')
        ->and(apText($builder->build('financial')))->toContain('No fee collections, budgets or receipts were recorded this semester.')
        ->and(apText($builder->build('inventory')))->toContain('No properties were recorded for this semester\'s activities.');
});

it('tells the AI what the officers attached, with the receipt figures', function () {
    [$club, , $president] = apClub();
    $activity = apActivity($club, $president, ['budget_amount' => 1000]);
    apAttach($president, $activity, ['category' => 'documentation'])->assertRedirect();
    apAttach($president, $activity, ['category' => 'receipt', 'caption' => 'Tarpaulin', 'amount' => 700])->assertRedirect();

    expect(NarrativeReportData::recordsText($activity->id))
        ->toContain('Photos attached by the officers: 1 activity photos')
        ->toContain('Budget released: PHP 1,000.00')
        ->toContain('Receipts (1), total PHP 700.00: Tarpaulin PHP 700.00');
});

it('deletes the club\'s report photos with the club', function () {
    [$club, , $president] = apClub();
    $activity = apActivity($club, $president);
    apAttach($president, $activity, ['category' => 'documentation'])->assertRedirect();
    $path = ActivityPhoto::first()->path;
    $this->mock(FirebaseService::class)->shouldIgnoreMissing();

    app(ClubDeletion::class)->delete($club);

    expect(ActivityPhoto::count())->toBe(0)
        ->and(Storage::disk('local')->exists($path))->toBeFalse();
});
