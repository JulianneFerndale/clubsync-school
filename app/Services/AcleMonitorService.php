<?php

namespace App\Services;

use App\Models\Club;
use App\Models\ClubActivity;
use App\Models\Semester;
use Illuminate\Http\Request;

class AcleMonitorService
{
    public function build(Request $request): array
    {
        $currentSemester = Semester::current()->first();

        $acleQuery = ClubActivity::where('activity_type', 'acle')->with('club');

        if ($request->filled('semester_id')) {
            $semester = Semester::find($request->input('semester_id'));

            if ($semester) {
                $acleQuery->whereBetween('date', [$semester->start_date, $semester->end_date]);
            }
        }

        if ($request->filled('club_id')) {
            $acleQuery->where('club_id', $request->input('club_id'));
        }

        if ($request->filled('date_from')) {
            $acleQuery->whereDate('date', '>=', $request->input('date_from'));
        }

        if ($request->filled('date_to')) {
            $acleQuery->whereDate('date', '<=', $request->input('date_to'));
        }

        $upcomingAcleActivities = $acleQuery->orderBy('date')->orderBy('time_start')->get();

        // Flag overlapping ACLE activities at the same venue on the same date.
        foreach ($upcomingAcleActivities as $activity) {
            $activity->has_conflict = $upcomingAcleActivities->contains(function ($other) use ($activity) {
                return $other->id !== $activity->id
                    && $other->venue === $activity->venue
                    && $other->date->isSameDay($activity->date)
                    && $other->time_start < $activity->time_end
                    && $other->time_end > $activity->time_start;
            });
        }

        $acleBaseQuery = ClubActivity::where('activity_type', 'acle');

        $acleSummary = [
            'total'     => (clone $acleBaseQuery)->count(),
            'pending'   => (clone $acleBaseQuery)->where('approval_status', 'pending_approval')->count(),
            'approved'  => (clone $acleBaseQuery)->where('approval_status', 'approved')->count(),
            'completed' => (clone $acleBaseQuery)->where('status', 'completed')->count(),
        ];

        $acleThisSemester = $currentSemester
            ? (clone $acleBaseQuery)->whereBetween('date', [$currentSemester->start_date, $currentSemester->end_date])->count()
            : 0;

        return [
            'upcomingAcleActivities' => $upcomingAcleActivities,
            'acleSummary'            => $acleSummary,
            'acleThisSemester'       => $acleThisSemester,
            'clubs'                  => Club::orderBy('name')->get(),
            'semesters'              => Semester::orderByDesc('start_date')->get(),
        ];
    }
}
