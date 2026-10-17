<?php

use App\Jobs\SyncToGoogleCalendars;
use App\Models\Announcement;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubActivity;
use App\Models\ClubMember;
use App\Models\ClubNarrative;
use App\Models\ClubNotification;
use App\Models\ClubOfficer;
use App\Models\ClubReportSubmission;
use App\Models\Course;
use App\Models\Fee;
use App\Models\FeePayment;
use App\Models\GoogleCalendarEvent;
use App\Models\Semester;
use App\Models\SemesterNarrativeReport;
use App\Models\User;
use App\Services\FirebaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * The DSA can permanently delete a club, and everything that belongs to it goes with
 * it — including the records the database would otherwise orphan (announcements,
 * notifications) or refuse to let go of (the retired ched_reports table).
 */

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');

    // No Firebase calls in tests; individual tests assert on the cleanup call.
    $this->mock(FirebaseService::class)->shouldIgnoreMissing();
});

function cdUser(string $role, array $overrides = []): User
{
    static $n = 0;
    $n++;

    return User::create(array_merge([
        'first_name'   => 'Cd',
        'last_name'    => "Tester{$n}",
        'edp_number'   => str_pad((string) (700 + $n), 10, '0', STR_PAD_LEFT),
        'email'        => "cd{$n}@sccpag.edu.ph",
        'password'     => Hash::make('Password1!'),
        'role'         => $role,
        'course_id'    => testCourse()->id,
        'is_admin'     => false,
        'is_suspended' => false,
    ], $overrides));
}

function cdSession(User $user): array
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

function cdClub(string $acronym = 'PSYMS', string $type = 'academic', ?Course $course = null): Club
{
    $club = Club::create([
        'slug'            => 'cd-' . strtolower($acronym),
        'acronym'         => $acronym,
        'name'            => "{$acronym} Society",
        'type'            => $type,
        'club_type'       => $type === 'academic' ? 'Academic' : 'Non-Academic',
        'course_slug'     => 'all',
        'department_slug' => 'all',
        'is_active'       => true,
        'adviser_id'      => cdUser('adviser')->id,
    ]);

    if ($type === 'academic') {
        $club->courses()->attach(($course ?? testCourse())->id);
    }

    return $club;
}

/** Give a club one of everything it can own. Returns the officer who runs it. */
function cdPopulate(Club $club): User
{
    $officer = cdUser('president');
    ClubOfficer::create(['club_id' => $club->id, 'user_id' => $officer->id, 'position' => 'president']);

    $member     = cdUser('member');
    $membership = ClubMember::create(['club_id' => $club->id, 'user_id' => $member->id, 'status' => 'active']);

    $semester = Semester::firstOrCreate(['label' => 'CD Semester'], [
        'start_date' => today()->subMonth()->toDateString(), 'end_date' => today()->addMonths(3)->toDateString(), 'is_current' => true,
    ]);

    DB::table('member_semester_statuses')->insert([
        'club_member_id' => $membership->id, 'semester_id' => $semester->id, 'semester_status' => 'active',
        'updated_by' => $officer->id, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $letter   = "approval-letters/{$club->acronym}-letter.pdf";
    Storage::disk('local')->put($letter, 'letter');

    $activity = ClubActivity::create([
        'club_id' => $club->id, 'title' => "{$club->acronym} General Assembly", 'description' => 'Assembly.',
        'date' => today()->toDateString(), 'time_start' => '08:00', 'time_end' => '10:00', 'venue' => 'Library',
        'purpose' => 'Orientation.', 'expected_participants' => 10, 'activity_type' => 'acle',
        'approval_status' => 'approved', 'status' => 'completed', 'created_by' => $officer->id,
        'approval_letter_path' => $letter,
    ]);

    Attendance::create(['event_id' => $activity->id, 'user_id' => $member->id, 'time_in' => now(), 'recorded_by' => $officer->id]);
    DB::table('activity_change_logs')->insert([
        'club_activity_id' => $activity->id, 'changed_by' => $officer->id, 'changes' => json_encode([]),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('ched_reports')->insert([
        'club_activity_id' => $activity->id, 'club_id' => $club->id, 'report_type' => 'accomplishment',
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $announcement = Announcement::create([
        'club_id' => $club->id, 'author_id' => $officer->id, 'title' => 'Welcome', 'content' => 'Hello.',
        'type' => 'announcement', 'status' => 'published', 'published_at' => now(),
    ]);
    DB::table('announcement_comments')->insert(['announcement_id' => $announcement->id, 'user_id' => $member->id, 'content' => 'Hi', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('announcement_likes')->insert(['announcement_id' => $announcement->id, 'user_id' => $member->id, 'created_at' => now(), 'updated_at' => now()]);

    $fee = Fee::create([
        'club_id' => $club->id, 'title' => 'Membership Fee', 'amount' => 100,
        'due_date' => today()->addWeek()->toDateString(), 'academic_period' => '1st Semester, A.Y. 2026-2027',
    ]);
    FeePayment::create(['fee_id' => $fee->id, 'user_id' => $member->id, 'status' => 'paid', 'receipt_number' => "OR-2026-{$club->id}0001"]);

    ClubNarrative::create(['club_id' => $club->id, 'club_activity_id' => $activity->id, 'title' => 'Recap', 'status' => 'pending_review']);

    $pdf = "semester-reports/{$club->acronym}.pdf";
    Storage::disk('local')->put($pdf, 'pdf');
    SemesterNarrativeReport::create([
        'club_id' => $club->id, 'semester_id' => $semester->id, 'activity_data' => ['totals' => ['activities' => 1]],
        'status' => 'submitted', 'pdf_path' => $pdf,
    ]);

    $upload = "club-reports/{$club->id}/{$semester->id}/report.pdf";
    Storage::disk('local')->put($upload, 'report');
    ClubReportSubmission::create([
        'club_id' => $club->id, 'semester_id' => $semester->id, 'report_type' => array_key_first(ClubReportSubmission::TYPES),
        'file_path' => $upload, 'file_name' => 'report.pdf', 'file_size' => 6, 'status' => 'submitted',
        'submitted_by' => $officer->id, 'submitted_at' => now(),
    ]);

    ClubNotification::create(['recipient_id' => $member->id, 'sender_type' => 'system', 'club_id' => $club->id, 'title' => 'New activity', 'body' => 'x']);

    DB::table('ai_notification_queue')->insert(['club_id' => $club->id, 'gap_type' => 'officer_vacancy', 'draft_content' => 'x', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('compliance_items')->insert(['club_id' => $club->id, 'item_type' => 'document', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('club_officer_records')->insert([
        'club_id' => $club->id, 'full_name' => 'Cd Officer', 'position' => 'President', 'student_id_number' => '1',
        'academic_year' => '2026-2027', 'semester' => '1st', 'created_at' => now(), 'updated_at' => now(),
    ]);

    return $officer;
}

/** Row counts of everything a club can own, keyed by table. */
function cdFootprint(Club $club): array
{
    $activityIds = DB::table('club_activities')->where('club_id', $club->id)->pluck('id');
    $announcementIds = DB::table('announcements')->where('club_id', $club->id)->pluck('id');
    $memberIds = DB::table('club_members')->where('club_id', $club->id)->pluck('id');
    $feeIds = DB::table('fees')->where('club_id', $club->id)->pluck('id');

    return [
        'clubs'                      => DB::table('clubs')->where('id', $club->id)->count(),
        'club_members'               => $memberIds->count(),
        'member_semester_statuses'   => DB::table('member_semester_statuses')->whereIn('club_member_id', $memberIds)->count(),
        'club_officers'              => DB::table('club_officers')->where('club_id', $club->id)->count(),
        'club_officer_records'       => DB::table('club_officer_records')->where('club_id', $club->id)->count(),
        'club_activities'            => $activityIds->count(),
        'attendance'                 => DB::table('attendance')->whereIn('event_id', $activityIds)->count(),
        'activity_change_logs'       => DB::table('activity_change_logs')->whereIn('club_activity_id', $activityIds)->count(),
        'ched_reports'               => DB::table('ched_reports')->where('club_id', $club->id)->count(),
        'announcements'              => $announcementIds->count(),
        'announcement_comments'      => DB::table('announcement_comments')->whereIn('announcement_id', $announcementIds)->count(),
        'announcement_likes'         => DB::table('announcement_likes')->whereIn('announcement_id', $announcementIds)->count(),
        'fees'                       => $feeIds->count(),
        'fee_payments'               => DB::table('fee_payments')->whereIn('fee_id', $feeIds)->count(),
        'club_narratives'            => DB::table('club_narratives')->where('club_id', $club->id)->count(),
        'semester_narrative_reports' => DB::table('semester_narrative_reports')->where('club_id', $club->id)->count(),
        'club_report_submissions'    => DB::table('club_report_submissions')->where('club_id', $club->id)->count(),
        'notifications'              => DB::table('notifications')->where('club_id', $club->id)->count(),
        'ai_notification_queue'      => DB::table('ai_notification_queue')->where('club_id', $club->id)->count(),
        'compliance_items'           => DB::table('compliance_items')->where('club_id', $club->id)->count(),
        'club_course'                => DB::table('club_course')->where('club_id', $club->id)->count(),
    ];
}

function cdDelete(Club $club, ?User $dsa = null, ?string $confirm = null)
{
    return test()->withSession(cdSession($dsa ?? cdUser('dsa')))
        ->delete(route('dsa.clubs.destroy', $club), ['confirm_acronym' => $confirm ?? $club->acronym]);
}

it('deletes the club and every record that belongs to it', function () {
    $club = cdClub();
    cdPopulate($club);

    expect(array_filter(cdFootprint($club)))->toHaveCount(count(cdFootprint($club))); // every table really had a row

    // Announcements and notifications are SET NULL, so an orphan would no longer carry
    // the club_id; look them up by their own ids instead.
    $announcementIds = Announcement::where('club_id', $club->id)->pluck('id');
    $notificationIds = ClubNotification::where('club_id', $club->id)->pluck('id');

    cdDelete($club)
        ->assertRedirect(route('dsa.clubs.index'))
        ->assertSessionHas('success', fn (string $m) => str_contains($m, 'PSYMS Society and all of its data were permanently deleted.'));

    expect(array_filter(cdFootprint($club)))->toBe([])
        ->and(Announcement::whereKey($announcementIds)->exists())->toBeFalse()
        ->and(ClubNotification::whereKey($notificationIds)->exists())->toBeFalse();
});

it("leaves every other club's records alone", function () {
    $doomed = cdClub('PSYMS');
    $kept   = cdClub('BYTE', 'non_academic');
    cdPopulate($doomed);
    cdPopulate($kept);
    $before = cdFootprint($kept);

    cdDelete($doomed);

    expect(cdFootprint($kept))->toBe($before);
});

it("removes the club's stored files", function () {
    Storage::disk('public')->put('clubs/logos/psyms.png', 'logo');
    Storage::disk('public')->put('clubs/banners/psyms.png', 'banner');
    $club = cdClub();
    $club->update([
        'profile_photo_url' => Storage::disk('public')->url('clubs/logos/psyms.png'),
        'banner_image'      => '/storage/clubs/banners/psyms.png',
    ]);
    cdPopulate($club);

    cdDelete($club);

    Storage::disk('public')->assertMissing(['clubs/logos/psyms.png', 'clubs/banners/psyms.png']);
    Storage::disk('local')->assertMissing(['approval-letters/PSYMS-letter.pdf', 'semester-reports/PSYMS.pdf']);
    expect(Storage::disk('local')->allFiles("club-reports/{$club->id}"))->toBe([]);
});

it("clears the club's Firebase mirror", function () {
    $club = cdClub();
    $this->mock(FirebaseService::class)->shouldReceive('delete')->once()->with("/clubs/{$club->id}");

    cdDelete($club)->assertRedirect(route('dsa.clubs.index'));
});

it('still deletes the club when the Firebase cleanup fails', function () {
    $club = cdClub();
    $this->mock(FirebaseService::class)->shouldReceive('delete')->andThrow(new RuntimeException('offline'));

    cdDelete($club)->assertRedirect(route('dsa.clubs.index'));

    expect(Club::find($club->id))->toBeNull();
});

it('returns officers of no other club to the Member role, and leaves officers of another club alone', function () {
    $club    = cdClub();
    $officer = cdPopulate($club);

    $twoClubs = cdUser('treasurer');
    ClubOfficer::create(['club_id' => $club->id, 'user_id' => $twoClubs->id, 'position' => 'treasurer']);
    ClubOfficer::create(['club_id' => cdClub('BYTE', 'non_academic')->id, 'user_id' => $twoClubs->id, 'position' => 'treasurer']);

    cdDelete($club)->assertSessionHas('success', fn (string $m) => str_contains($m, '1 former officer of no other club now has the Member role.'));

    expect($officer->fresh()->role)->toBe('member')
        ->and($twoClubs->fresh()->role)->toBe('treasurer');
});

it('flags students whose course no other academic club covers as Pending Club Assignment', function () {
    $orphanCourse = Course::create(['slug' => 'cd-orphan', 'name' => 'Orphaned Program', 'department_id' => testCourse()->department_id]);
    $sharedCourse = testCourse();

    $club = cdClub('PSYMS', 'academic', $orphanCourse);
    $club->courses()->attach($sharedCourse->id);
    cdClub('OTHER', 'academic', $sharedCourse); // still covers the shared course

    $orphaned = cdUser('member', ['course_id' => $orphanCourse->id]);
    $covered  = cdUser('member', ['course_id' => $sharedCourse->id]);
    $adviser  = cdUser('adviser', ['course_id' => $orphanCourse->id]); // not a student

    cdDelete($club)->assertSessionHas('success', fn (string $m) => str_contains($m, '1 student whose course mapped only to this club is now flagged Pending Club Assignment.'));

    expect($orphaned->fresh()->pending_club_assignment)->toBeTrue()
        ->and($covered->fresh()->pending_club_assignment)->toBeFalse()
        ->and($adviser->fresh()->pending_club_assignment)->toBeFalse();
});

it('records the deletion in the audit log and keeps the club\'s earlier audit entries', function () {
    $club = cdClub();
    cdPopulate($club);
    AuditLog::record('club.updated', $club, ['changed' => ['name']]);
    $dsa = cdUser('dsa');

    cdDelete($club, $dsa);

    $entry = AuditLog::where('action', 'club.deleted')->sole();

    expect($entry->actor_id)->toBe($dsa->id)
        ->and($entry->resource_id)->toBe($club->id)
        ->and($entry->metadata['acronym'])->toBe('PSYMS')
        ->and($entry->metadata['removed']['activities'])->toBe(1)
        ->and(AuditLog::where('action', 'club.updated')->where('resource_id', $club->id)->exists())->toBeTrue();
});

it("queues the removal of the club's Google Calendar copies, for the records someone holds a copy of", function () {
    Queue::fake();
    $club     = cdClub();
    $officer  = cdPopulate($club);
    $activity = ClubActivity::where('club_id', $club->id)->value('id');
    GoogleCalendarEvent::create(['user_id' => $officer->id, 'source_type' => GoogleCalendarEvent::ACTIVITY, 'source_id' => $activity, 'slot' => 'main', 'google_event_id' => 'g1', 'payload_hash' => 'h1']);

    cdDelete($club);

    // The activity has a copy on someone's calendar; the fee has none, so it needs no job.
    Queue::assertPushed(SyncToGoogleCalendars::class, 1);
    Queue::assertPushed(SyncToGoogleCalendars::class, fn ($job) => (fn () => [$this->sourceType, $this->sourceId])->call($job) === [GoogleCalendarEvent::ACTIVITY, $activity]);
});

it('deletes nothing unless the acronym is typed, and accepts it in any case', function () {
    $club = cdClub();
    cdPopulate($club);
    $dsa = cdUser('dsa');

    cdDelete($club, $dsa, 'BYTE')->assertSessionHasErrors('confirm_acronym');
    test()->withSession(cdSession($dsa))->delete(route('dsa.clubs.destroy', $club))->assertSessionHasErrors('confirm_acronym');

    expect(Club::find($club->id))->not->toBeNull()
        ->and(AuditLog::where('action', 'club.deleted')->exists())->toBeFalse();

    cdDelete($club, $dsa, ' psyms ')->assertRedirect(route('dsa.clubs.index'));

    expect(Club::find($club->id))->toBeNull();
});

it('only the DSA can delete a club', function (string $role) {
    $club = cdClub();

    cdDelete($club, cdUser($role))->assertForbidden();

    expect(Club::find($club->id))->not->toBeNull();
})->with(['member', 'president', 'adviser']);

it("shows the DSA what deleting the club removes, on the club's page", function () {
    $club = cdClub();
    cdPopulate($club);

    test()->withSession(cdSession(cdUser('dsa')))->get(route('dsa.clubs.show', $club))
        ->assertOk()
        ->assertSee('Delete this club')
        ->assertSee('1 activity, with their attendance', false)
        ->assertSee('Type <span class="font-mono font-semibold text-gray-900">PSYMS</span> to confirm', false)
        ->assertSee(route('dsa.clubs.destroy', $club));
});
