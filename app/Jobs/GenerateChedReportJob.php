<?php

namespace App\Jobs;

use App\Exports\ChedReportExport;
use App\Models\Attendance;
use App\Models\ChedReport;
use App\Models\ClubActivity;
use App\Models\ClubMember;
use App\Models\Semester;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;

class GenerateChedReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private int $activityId) {}

    public function handle(): void
    {
        $activity = ClubActivity::with('club.adviserUser', 'creator')->find($this->activityId);

        if (! $activity || ! $activity->club) {
            return;
        }

        $reportType = $activity->activity_type === 'acle' ? 'acle' : 'community_service';

        $data = static::reportData($activity);

        $directory = 'reports/' . $activity->id;

        $pdfPath = $directory . '/ched-report.pdf';
        Pdf::loadView('reports.ched-pdf', $data)->save($pdfPath, 'local');

        $xlsxPath = $directory . '/ched-report.xlsx';
        Excel::store(new ChedReportExport($data), $xlsxPath, 'local');

        ChedReport::create([
            'club_activity_id' => $activity->id,
            'club_id'          => $activity->club_id,
            'report_type'      => $reportType,
            'pdf_path'         => $pdfPath,
            'xlsx_path'        => $xlsxPath,
        ]);
    }

    /**
     * Build the post-event report payload from the activity record itself.
     *
     * Participants are the people who were actually checked in on this activity's
     * attendance sheet — never the club roster. The roster is used only as the
     * denominator for the engagement figures (rate, absentees), so the report can
     * say how many of the eligible members turned up and how that compares with
     * what the officer expected when the activity was proposed.
     */
    public static function reportData(ClubActivity $activity): array
    {
        $semester = Semester::where('start_date', '<=', $activity->date)
            ->where('end_date', '>=', $activity->date)
            ->first();

        $eligible = ClubMember::where('club_id', $activity->club_id)
            ->where('registration_status', 'approved')
            ->get()
            ->filter(function (ClubMember $member) use ($semester) {
                if (! $semester) {
                    return true;
                }

                $status = $member->semesterStatuses()
                    ->where('semester_id', $semester->id)
                    ->orderByDesc('created_at')
                    ->first();

                return ! $status || $status->semester_status === 'active';
            });

        $sheet = Attendance::where('event_id', $activity->id)
            ->with('user')
            ->orderBy('time_in')
            ->get();

        // A row with no time_in was never checked in — registered, but a no-show.
        $present = $sheet->whereNotNull('time_in');

        $presentCount  = $present->count();
        $eligibleCount = $eligible->count();
        $expected      = (int) $activity->expected_participants;

        return [
            'club_name'         => $activity->club->name,
            'adviser_name'      => $activity->club->adviserUser?->name ?? 'Not assigned',
            'activity_title'    => $activity->title,
            'activity_type'     => $activity->activity_type,
            'date'              => $activity->date->format('F j, Y'),
            'time'              => $activity->time_start . ' - ' . $activity->time_end,
            'venue'             => $activity->venue,
            'objectives'        => $activity->purpose,
            'description'       => $activity->description,
            'officer_in_charge' => $activity->creator?->name ?? 'Unknown',

            // Attendance, straight off this activity's sheet.
            'attendance_recorded' => $sheet->isNotEmpty(),
            'participant_count'   => $presentCount,
            'no_show_count'       => $sheet->count() - $presentCount,
            'participants'        => $present->map(fn (Attendance $row) => [
                'name'     => $row->user?->name ?? 'Unknown',
                'time_in'  => $row->time_in?->format('g:i A') ?? '—',
                'time_out' => $row->time_out?->format('g:i A') ?? '—',
            ])->values(),

            // Engagement, measured against the club roster and the officer's estimate.
            'expected_participants' => $expected,
            'eligible_members'      => $eligibleCount,
            'absent_count'          => max($eligibleCount - $presentCount, 0),
            'attendance_rate'       => $eligibleCount > 0
                ? round($presentCount / $eligibleCount * 100, 1)
                : 0.0,
            'turnout_vs_expected'   => $expected > 0
                ? round($presentCount / $expected * 100, 1)
                : 0.0,
        ];
    }
}
