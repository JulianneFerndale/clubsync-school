<?php

namespace App\Services;

use App\Jobs\SyncToGoogleCalendars;
use App\Models\Announcement;
use App\Models\AuditLog;
use App\Models\Club;
use App\Models\ClubActivity;
use App\Models\ClubMember;
use App\Models\ClubNotification;
use App\Models\ClubOfficer;
use App\Models\ClubReportSubmission;
use App\Models\Fee;
use App\Models\GoogleCalendarEvent;
use App\Models\SemesterNarrativeReport;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Permanently delete a club and everything that belongs to it.
 *
 * Most of a club's records go with the club row through ON DELETE CASCADE: members
 * (and their semester presence), officers, activities (attendance, change logs),
 * fees (payments and receipts), report photos, narratives, reports, compliance items, the AI queue
 * and the course mapping. Three do not, and are removed here first:
 *   - announcements and notifications are SET NULL, so a club's posts would stay on
 *     the campus-wide bulletin with no club behind them;
 *   - the retired ched_reports table is NO ACTION, which would block the delete.
 * Files, the Firebase mirror and Google Calendar copies live outside the database and
 * are cleared only once the delete has committed, so a failed delete loses nothing.
 *
 * The audit log is kept. It is hash-chained and POLICY.md requires a year's retention,
 * so the club's history stays there and the deletion itself is recorded.
 */
class ClubDeletion
{
    public const OFFICER_ROLES = ['president', 'treasurer', 'mmo'];

    public const STUDENT_ROLES = ['member', 'president', 'treasurer', 'mmo'];

    public function __construct(private FirebaseService $firebase) {}

    /** What the DSA types to confirm: the acronym, or the name for a club without one. */
    public static function confirmationWord(Club $club): string
    {
        return trim((string) ($club->acronym ?: $club->name));
    }

    /** What deleting the club removes, for the confirmation panel and the audit entry. */
    public static function inventory(Club $club): array
    {
        return [
            'members'       => ClubMember::where('club_id', $club->id)->count(),
            'officers'      => ClubOfficer::where('club_id', $club->id)->count(),
            'activities'    => ClubActivity::where('club_id', $club->id)->count(),
            'announcements' => Announcement::where('club_id', $club->id)->count(),
            'fees'          => Fee::where('club_id', $club->id)->count(),
            'reports'       => SemesterNarrativeReport::where('club_id', $club->id)->count()
                + ClubReportSubmission::where('club_id', $club->id)->count(),
        ];
    }

    /**
     * @return array{removed: array<string, int>, demoted: int, unassigned: int}
     */
    public function delete(Club $club): array
    {
        $inventory   = self::inventory($club);
        $activityIds = ClubActivity::where('club_id', $club->id)->pluck('id')->all();
        $feeIds      = Fee::where('club_id', $club->id)->pluck('id')->all();
        $officerIds  = ClubOfficer::where('club_id', $club->id)->pluck('user_id')->unique()->values()->all();
        $courseIds   = $club->courses()->pluck('courses.id')->all();
        $files       = $this->files($club, $activityIds);
        $reportDirs  = SemesterNarrativeReport::where('club_id', $club->id)->get()->map->documentDirectory()->all();

        [$recipientIds, $demoted, $unassigned] = DB::transaction(function () use ($club, $inventory, $officerIds, $courseIds) {
            if (Schema::hasTable('ched_reports')) {
                DB::table('ched_reports')->where('club_id', $club->id)->delete();
            }

            Announcement::where('club_id', $club->id)->delete();

            $recipientIds = ClubNotification::where('club_id', $club->id)->distinct()->pluck('recipient_id')->all();
            ClubNotification::where('club_id', $club->id)->delete();

            $club->delete();

            $demoted    = $this->demoteFormerOfficers($officerIds);
            $unassigned = $this->flagStudentsLeftWithoutAClub($courseIds);

            AuditLog::record('club.deleted', $club, [
                'name'                => $club->name,
                'acronym'             => $club->acronym,
                'type'                => $club->type,
                'removed'             => $inventory,
                'officers_demoted'    => $demoted,
                'students_unassigned' => $unassigned,
            ]);

            return [$recipientIds, $demoted, $unassigned];
        });

        foreach ($recipientIds as $recipientId) {
            ClubNotification::forgetUnreadCount((int) $recipientId);
        }

        Storage::disk('public')->delete($files['public']);
        Storage::disk('local')->delete($files['local']);
        Storage::disk('local')->deleteDirectory("club-reports/{$club->id}");
        Storage::disk('local')->deleteDirectory(ActivityPhotoStorage::ROOT . "/{$club->id}");
        foreach ($reportDirs as $directory) {
            Storage::disk('local')->deleteDirectory($directory);
        }

        try {
            $this->firebase->delete("/clubs/{$club->id}");
        } catch (\Throwable $e) {
            Log::warning("Firebase cleanup failed for deleted club #{$club->id}: " . $e->getMessage());
        }

        // The sync job removes a copy whose source no longer exists, so only the
        // records someone actually holds a copy of need one.
        foreach ([GoogleCalendarEvent::ACTIVITY => $activityIds, GoogleCalendarEvent::FEE => $feeIds] as $type => $ids) {
            GoogleCalendarEvent::where('source_type', $type)
                ->whereIn('source_id', $ids)
                ->distinct()
                ->pluck('source_id')
                ->each(fn ($id) => SyncToGoogleCalendars::dispatch($type, (int) $id));
        }

        return ['removed' => $inventory, 'demoted' => $demoted, 'unassigned' => $unassigned];
    }

    /** An officer of no other club keeps no officer role. */
    private function demoteFormerOfficers(array $userIds): int
    {
        if ($userIds === []) {
            return 0;
        }

        $stillOfficers = ClubOfficer::whereIn('user_id', $userIds)->pluck('user_id')->all();

        return User::whereIn('id', array_diff($userIds, $stillOfficers))
            ->whereIn('role', self::OFFICER_ROLES)
            ->update(['role' => 'member']);
    }

    /**
     * Students whose course now maps to no active academic club are flagged
     * Pending Club Assignment, as registration would flag them (POLICY.md §Registration).
     */
    private function flagStudentsLeftWithoutAClub(array $courseIds): int
    {
        if ($courseIds === []) {
            return 0;
        }

        $stillMapped = DB::table('club_course')
            ->join('clubs', 'clubs.id', '=', 'club_course.club_id')
            ->whereIn('club_course.course_id', $courseIds)
            ->where('clubs.club_type', 'Academic')
            ->where('clubs.is_active', true)
            ->pluck('club_course.course_id')
            ->all();

        $orphaned = array_values(array_diff($courseIds, $stillMapped));

        if ($orphaned === []) {
            return 0;
        }

        return User::whereIn('course_id', $orphaned)
            ->whereIn('role', self::STUDENT_ROLES)
            ->where('pending_club_assignment', false)
            ->update(['pending_club_assignment' => true]);
    }

    /** @return array{public: list<string>, local: list<string>} */
    private function files(Club $club, array $activityIds): array
    {
        $publicBase = rtrim((string) config('filesystems.disks.public.url'), '/') . '/';

        $public = collect([$club->profile_photo_url, $club->banner_image])
            ->filter()
            ->map(fn (string $url) => match (true) {
                Str::startsWith($url, '/storage/') => Str::after($url, '/storage/'),
                Str::startsWith($url, $publicBase) => Str::after($url, $publicBase),
                default                            => null, // hosted elsewhere, not ours to delete
            })
            ->filter()
            ->values()
            ->all();

        $local = array_merge(
            ClubActivity::whereIn('id', $activityIds)->whereNotNull('approval_letter_path')->pluck('approval_letter_path')->all(),
            ClubReportSubmission::where('club_id', $club->id)->whereNotNull('file_path')->pluck('file_path')->all(),
            SemesterNarrativeReport::where('club_id', $club->id)->whereNotNull('pdf_path')->pluck('pdf_path')->all(),
        );

        return ['public' => $public, 'local' => array_values(array_filter($local))];
    }
}
