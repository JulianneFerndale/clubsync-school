<?php

namespace App\Jobs;

use App\Models\ClubActivity;
use App\Models\Semester;
use App\Models\SemesterNarrativeReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Daily sweep: once a semester has ended, queue a narrative report for every club
 * that completed at least one activity during it.
 *
 * A semester is picked up from the day after its end date, so activities completed
 * on the last day are included. The sweep keeps looking for CATCH_UP_DAYS in case the
 * scheduler was down, and skips clubs that already have a report, so it is safe to
 * run any number of times.
 */
class DispatchSemesterNarrativeReportsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const CATCH_UP_DAYS = 14;

    public function handle(): void
    {
        $semesters = Semester::whereDate('end_date', '<', today()->toDateString())
            ->whereDate('end_date', '>=', today()->subDays(self::CATCH_UP_DAYS)->toDateString())
            ->get();

        foreach ($semesters as $semester) {
            static::dispatchFor($semester);
        }
    }

    /**
     * Queue a report for each club with completed activities in the semester that
     * does not have one yet.
     *
     * @param  list<int>  $clubIds  Limit to these clubs; empty means every club.
     * @return list<int>  The club ids a report was queued for.
     */
    public static function dispatchFor(Semester $semester, array $clubIds = [], bool $sync = false): array
    {
        $clubs = ClubActivity::completed()
            ->whereDate('date', '>=', $semester->start_date->toDateString())
            ->whereDate('date', '<=', $semester->end_date->toDateString())
            ->when($clubIds, fn ($query) => $query->whereIn('club_id', $clubIds))
            ->distinct()
            ->pluck('club_id')
            ->diff(SemesterNarrativeReport::where('semester_id', $semester->id)->pluck('club_id'))
            ->values();

        foreach ($clubs as $clubId) {
            $sync
                ? GenerateSemesterNarrativeReportJob::dispatchSync($clubId, $semester->id)
                : GenerateSemesterNarrativeReportJob::dispatch($clubId, $semester->id);
        }

        return $clubs->all();
    }
}
